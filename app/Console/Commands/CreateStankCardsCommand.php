<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\ProductSourceEnricher;
use App\Services\Suppliers\StankPriceList;
use App\Services\Suppliers\StankTeplodvorCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cards for the S-TANK price-list models no card of ours carries (archived ones count): the title by the kind of
 * tank, the retail price of the list, its specs, the category of our S-TANK cards of the same kind. Photos, specs and
 * the description come from the teplodvor.by page of the model; without one, the photos of the same series there;
 * otherwise the card waits hidden for a photo. «По запросу» models are not made.
 *
 *   php artisan supplier:stank-create                       # plan
 *   php artisan supplier:stank-create --limit=20 --apply    # create and fill
 */
class CreateStankCardsCommand extends Command
{
    protected $signature = 'supplier:stank-create
        {--only=* : Only these models (BER2-200, SSHP-300; comma-separated or repeated)}
        {--limit=0 : At most this many cards}
        {--apply : Create, link and fill the cards}';

    protected $description = 'Create cards for the S-TANK price-list models missing on the site';

    private const SUPPLIER_CODE = 'stank';

    /** A kind of tank => [the model of our cards whose category it takes, the fallback category slug]. */
    private const KIND_CATEGORY = [
        'indirect' => ['BER-150', 'kosvennye'],
        'buffer' => ['AT-500', 'bufernye-emkosti'],
        'buffer_dhw' => ['HFWT-300', 'bufernye-emkosti'],
        'electric' => [null, 'electric'],
        'cold' => ['AT-500', 'bufernye-emkosti'],
        // Anodes, heating elements, thermostats, plugs: the row names its category.
        'accessory' => [null, null],
    ];

    public function handle(StankTeplodvorCatalog $site, ProductSourceEnricher $enricher): int
    {
        $supplier = Supplier::query()->where('code', self::SUPPLIER_CODE)->first();
        $brandId = (int) DB::table('brands')->where('name', 'S-TANK')->value('id');
        if (! $supplier || ! $brandId) {
            $this->line('metric.stank_create.supplier='.(int) (bool) $supplier);

            return self::FAILURE;
        }
        $only = collect((array) $this->option('only'))->flatMap(fn ($v) => explode(',', (string) $v))->map(fn ($v) => mb_strtoupper(trim($v)))->filter()->all();
        $cards = DB::table('products')->where('brand_id', $brandId)->get(['id', 'name', 'category_id', 'is_archived']);
        $linked = SupplierProduct::query()->where('supplier_id', $supplier->id)->whereNotNull('product_id')->pluck('supplier_article', 'product_id');
        $existing = [];
        $categoryOf = [];
        // The photos of our own card of the same series: AT-200 looks like AT-300.
        $seriesImages = [];
        foreach (Product::query()->where('brand_id', $brandId)->where('is_archived', false)->get(['id', 'name', 'images']) as $card) {
            $key = StankPriceList::key((string) $card->name);
            if ($key !== null && ($paths = $card->imagePaths()) !== []) {
                $seriesImages[StankPriceList::series($key)] ??= array_slice($paths, 0, 4);
            }
        }
        foreach ($cards as $card) {
            $key = isset($linked[$card->id]) && StankPriceList::row((string) $linked[$card->id]) ? (string) $linked[$card->id] : StankPriceList::key((string) $card->name);
            if ($key !== null) {
                $existing[$key] = true;
                if (! $card->is_archived) {
                    $categoryOf[$key] ??= (int) $card->category_id;
                }
            }
        }
        $categories = DB::table('categories')->pluck('id', 'slug');
        $stats = ['rows' => count(StankPriceList::rows()), 'planned' => 0, 'card_exists' => 0, 'on_request' => 0, 'no_category' => 0,
            'on_site' => 0, 'series_photos' => 0, 'created' => 0, 'with_photo' => 0, 'errors' => 0];
        $limit = (int) $this->option('limit');
        foreach (StankPriceList::rows() as $key => $row) {
            if ($only !== [] && ! in_array($key, $only, true)) {
                continue;
            }
            if (isset($existing[$key])) {
                $stats['card_exists']++;

                continue;
            }
            if ($row['price'] === null) {
                $stats['on_request']++;
                $this->line('audit.stank_create.on_request='.$key);

                continue;
            }
            [$model, $slug] = self::KIND_CATEGORY[$row['kind']];
            $slug ??= (string) ($row['category'] ?? '');
            $categoryId = (int) (($model ? ($categoryOf[$model] ?? 0) : 0) ?: ($categories[$slug] ?? 0));
            if (! $categoryId) {
                $stats['no_category']++;
                $this->line('audit.stank_create.no_category='.$key);

                continue;
            }
            $title = self::cardTitle($row);
            $accessory = $row['kind'] === 'accessory';
            $url = $accessory ? null : $site->find($title);
            $seriesUrl = $url || $accessory ? null : $site->findSeries($key);
            $ownImages = $url || $seriesUrl ? [] : ($seriesImages[StankPriceList::series($key)] ?? []);
            $stats['planned']++;
            $stats['on_site'] += (int) ($url !== null);
            $stats['series_photos'] += (int) ($seriesUrl !== null || $ownImages !== []);
            $this->line(sprintf('audit.stank_create.model=%s:%s:%s:%s', $key, DB::table('categories')->where('id', $categoryId)->value('slug') ?: $categoryId,
                $url ? 'teplodvor' : ($seriesUrl || $ownImages ? 'series' : 'price'), Str::slug($title)));
            if (! $this->option('apply') || ($limit > 0 && $stats['created'] >= $limit)) {
                continue;
            }
            try {
                $product = $this->createCard($row, $title, $categoryId, $brandId, $supplier);
                $existing[$key] = true;
                $stats['created']++;
                $page = $url ? $site->page($url) : null;
                $images = $page['images'] ?? ($seriesUrl ? ($site->page($seriesUrl)['images'] ?? []) : []);
                $siteSpecs = collect((array) ($page['specs'] ?? []))->map(fn ($value, $name) => ['key' => (string) $name, 'value' => (string) $value])->values()->all();
                $enricher->enrichFromParsed($product, $url ?: ($seriesUrl ?: 'https://s-tank.ru/'), [
                    'images' => [],
                    'specs' => CreateChistyBeregCardsCommand::withOwnSpecs($siteSpecs, (array) $product->specs),
                    'description' => (string) ($page['desc'] ?? '') ?: $title.'. '.collect($row['specs'])->map(fn ($s) => $s['key'].': '.$s['value'])->implode('; '),
                ], [
                    'verified_images' => array_slice($images, 0, 4), 'update_images' => $images !== [], 'update_specs' => true, 'replace_specs' => true,
                    'update_content' => true, 'min_specs_for_ai' => 2, 'update_service' => false, 'update_documents' => false, 'update_video' => false,
                ]);
                $product->refresh();
                if ($ownImages !== [] && $product->imagePaths() === []) {
                    $product->forceFill(['images' => $ownImages])->save();
                }
                if ($product->imagePaths() !== []) {
                    $product->forceFill(['is_active' => true, 'created_via' => null])->save();
                    $stats['with_photo']++;
                }
                $this->line(sprintf('audit.stank_create.created=%s:%d:%s', $key, $product->id, $product->slug));
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn($key.': '.$e->getMessage());
            }
        }
        $stats['site_pages'] = $site->indexSize();
        foreach ($stats as $name => $value) {
            $this->line("metric.stank_create.{$name}={$value}");
        }

        return self::SUCCESS;
    }

    /** «Бак косвенного нагрева S-TANK BER2-200», «Буферная ёмкость S-TANK FLAT 600», «Холодоаккумулятор S-TANK SS CT 300». */
    public static function cardTitle(array $row): string
    {
        if (filled($row['title'] ?? null)) {
            return (string) $row['title'];
        }
        $series = (string) $row['series'];
        $volume = (int) $row['volume'];
        $model = in_array($series, ['BER', 'BER2', 'PW', 'P Turbo', 'SS-HP', 'SOLAR SS', 'P Easy'], true) ? $series.'-'.$volume : $series.' '.$volume;
        if ($series === 'TA line') {
            $model = 'AT-'.$volume;
        }

        return match ($row['kind']) {
            'indirect' => 'Бак косвенного нагрева S-TANK '.$model.(str_contains($series, 'SS') ? ' (нерж. сталь)' : ''),
            'buffer' => 'Буферная ёмкость S-TANK '.$model.($series === 'SS' ? ' (нерж. сталь)' : ''),
            'buffer_dhw' => 'Теплоаккумулятор S-TANK '.$model.' с теплообменником ГВС',
            'electric' => 'Бак электронагрева ГВС S-TANK '.$model.(str_starts_with($series, 'SS') ? ' (нерж. сталь)' : ''),
            'cold' => 'Холодоаккумулятор S-TANK '.$model.(str_starts_with($series, 'SS') ? ' (нерж. сталь)' : ''),
            default => 'S-TANK '.$model,
        };
    }

    private function createCard(array $row, string $title, int $categoryId, int $brandId, Supplier $supplier): Product
    {
        $sku = 'STANK-'.$row['key'];
        $specs = array_merge([
            ['key' => 'Производитель', 'value' => 'S-TANK'],
            ['key' => $row['kind'] === 'accessory' ? 'Тип' : 'Серия', 'value' => (string) $row['series']],
            ['key' => $row['kind'] === 'accessory' ? 'Артикул производителя' : 'Модель', 'value' => (string) $row['label']],
        ], (array) $row['specs']);
        if ($row['price_titanium'] !== null && $row['price_magnesium'] !== null) {
            $specs[] = ['key' => 'Анод', 'value' => (float) $row['price'] === (float) $row['price_titanium']
                ? 'титановый (активный)'
                : 'магниевый; с титановым анодом — '.number_format((float) $row['price_titanium'], 2, ',', ' ').' BYN'];
        }
        $product = Product::query()->create([
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'name' => $title,
            'h1' => $title,
            'slug' => $this->uniqueSlug($title),
            'sku' => $sku,
            'price' => (float) $row['price'],
            'currency' => 'BYN',
            'short_description' => Str::limit($title.'. Официальная поставка в Беларусь, гарантия производителя.', 250, ''),
            'images' => [],
            'specs' => $specs,
            'unit' => 'шт',
            'is_active' => false,
            'created_via' => Product::CREATED_VIA_AWAITING_PHOTOS,
            'is_archived' => false,
            'is_new' => true,
            'availability_status' => Product::AVAILABILITY_CHECK,
            'meta_title' => Str::limit($title, 60, '').' купить в %city%',
            'meta_description' => Str::limit($title.' — цена, характеристики и доставка по Беларуси.', 175, ''),
        ]);
        SupplierProduct::query()->updateOrCreate(['supplier_id' => $supplier->id, 'supplier_article' => $row['key']], [
            'product_id' => $product->id,
            'product_sku' => $sku,
            'supplier_article_normalized' => strtolower((string) $row['key']),
            'supplier_article_compact' => preg_replace('/[^a-z0-9]/', '', strtolower((string) $row['key'])),
            'supplier_name' => $title,
            'price' => (float) $row['price'],
            'currency' => 'BYN',
            'currency_rate' => 1,
            'price_byn' => (float) $row['price'],
            'match_status' => 'matched',
            'match_confidence' => 'created_from_price',
            'raw' => ['label' => $row['label'], 'series' => $row['series'], 'sheet' => $row['sheet'], 'retail' => $row['price']],
            'last_synced_at' => now(),
        ]);

        return $product;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        for ($i = 2; Product::query()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}

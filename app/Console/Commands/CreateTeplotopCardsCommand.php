<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\ProductSourceEnricher;
use App\Services\SupplierProductAvailabilityService;
use App\Services\Suppliers\LemaxTeplodvorCatalog;
use App\Services\Suppliers\TeplotopPriceList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cards for the ТеплоТоп-Бел rows no card of ours carries, a group at a time: LEMAX boilers and gas water heaters
 * first, then the other goods, the radiators last. The category comes from the sheet, the price is the РРЦ, the
 * specs are those of the price list. Photos, specs and the description come from the teplodvor.by page of the
 * model when there is one; otherwise the description is written from the specs and the card waits for a photo
 * (hidden). A card is never made where one of the same brand and model exists.
 *
 *   php artisan supplier:teplotop-create --group=boilers                      # plan
 *   php artisan supplier:teplotop-create --group=boilers --limit=20 --apply   # create and fill
 */
class CreateTeplotopCardsCommand extends Command
{
    protected $signature = 'supplier:teplotop-create
        {--group=boilers : boilers (boilers and gas water heaters), other, radiators}
        {--limit=0 : At most this many cards}
        {--apply : Create, link and fill the cards}';

    protected $description = 'Create cards for ТеплоТоп-Бел offers (LEMAX boilers and gas water heaters first) with teplodvor.by photos';

    /** Sheets of each group. */
    private const GROUPS = [
        'boilers' => '/котл|водоногрев|водонагрев/iu',
        'radiators' => '/lemax-(боков|нижн)/iu',
    ];

    public function handle(LemaxTeplodvorCatalog $site, ProductSourceEnricher $enricher): int
    {
        $supplier = Supplier::query()->where('code', SyncTeplotopCommand::SUPPLIER_CODE)->first();
        if (! $supplier) {
            $this->line('metric.teplotop_create.supplier=0');

            return self::FAILURE;
        }
        $group = (string) $this->option('group');
        $categories = DB::table('categories')->pluck('id', 'slug');
        $lemax = (int) DB::table('brands')->get(['id', 'name'])->first(fn ($b) => in_array(Str::slug((string) $b->name, ''), ['lemaks', 'lemax', 'лемакс'], true))?->id;
        $offers = SupplierProduct::query()->where('supplier_id', $supplier->id)->whereNull('product_id')->where('match_status', 'unmatched')
            ->where('stock_status', '!=', 'out_of_stock')->orderBy('id')->get()
            ->filter(fn (SupplierProduct $offer): bool => (float) ($offer->raw['retail'] ?? 0) > 0 && empty($offer->raw['no_auto_link'])
                && self::groupOf((string) ($offer->raw['sheet'] ?? '')) === $group)
            ->values();
        // A card of the same model exists (two of them left the row unlinked): never a third one.
        $existing = [];
        // Within one category: the gas water heater «Classic-20» is not the boiler «Classic 20».
        foreach (DB::table('products')->where('brand_id', $lemax)->where('is_archived', false)->get(['name', 'category_id']) as $card) {
            $existing[$card->category_id.'|'.TeplotopPriceList::modelKey((string) $card->name)] = true;
        }
        $stats = ['offers' => $offers->count(), 'planned' => 0, 'card_exists' => 0, 'on_site' => 0, 'no_category' => 0, 'created' => 0, 'with_photo' => 0, 'errors' => 0];
        $plan = [];
        $limit = (int) $this->option('limit');
        foreach ($offers as $offer) {
            $raw = (array) $offer->raw;
            $name = trim((string) preg_replace('/^'.preg_quote((string) ($raw['brand'] ?? ''), '/').'\s+/u', '', (string) $offer->supplier_name));
            $code = Str::slug((string) $offer->supplier_article);
            $slug = collect(self::targetCategories((string) ($raw['sheet'] ?? ''), $name))->first(fn (string $slug) => isset($categories[$slug]));
            if ($slug !== null && ($raw['brand'] ?? '') === 'LEMAX' && isset($existing[$categories[$slug].'|'.TeplotopPriceList::modelKey($name)])) {
                $stats['card_exists']++;
                $this->line(sprintf('audit.teplotop_create.card_exists=%s', $code));

                continue;
            }
            if ($slug === null) {
                $stats['no_category']++;
                $this->line(sprintf('audit.teplotop_create.no_category=%s', $code));

                continue;
            }
            $stats['planned']++;
            $plan[$slug] = ($plan[$slug] ?? 0) + 1;
            $url = ($raw['brand'] ?? '') === 'LEMAX' ? $site->find($name) : null;
            $stats['on_site'] += (int) ($url !== null);
            $this->line(sprintf('audit.teplotop_create.offer=%s:%s:%s', $code, $slug, $url ? 'teplodvor' : 'price'));
            if (! $this->option('apply') || ($limit > 0 && $stats['created'] >= $limit)) {
                continue;
            }
            try {
                $page = $url ? $site->page($url) : null;
                $product = $this->createCard($offer, $name, $slug, (int) $categories[$slug], ($raw['brand'] ?? '') === 'LEMAX' ? $lemax : 0);
                $stats['created']++;
                $siteSpecs = collect((array) ($page['specs'] ?? []))->map(fn ($value, $key) => ['key' => (string) $key, 'value' => (string) $value])->values()->all();
                $images = $page['images'] ?? [];
                $enricher->enrichFromParsed($product, $url ?: 'https://ttop.by/', [
                    'images' => [],
                    'specs' => CreateChistyBeregCardsCommand::withOwnSpecs($siteSpecs, (array) $product->specs),
                    'description' => (string) ($page['desc'] ?? '') ?: trim($product->name.'. '.collect((array) ($raw['specs'] ?? []))->map(fn ($v, $k) => $k.': '.$v)->implode('; ')),
                ], [
                    'verified_images' => $images, 'update_images' => $images !== [], 'update_specs' => true, 'replace_specs' => true,
                    'update_content' => true, 'min_specs_for_ai' => 2, 'update_service' => false, 'update_documents' => false, 'update_video' => false,
                ]);
                $product->refresh();
                if ($product->imagePaths() !== []) {
                    $product->forceFill(['is_active' => true, 'created_via' => null])->save();
                    $stats['with_photo']++;
                }
                app(SupplierProductAvailabilityService::class)->refreshMany([$product->id]);
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn($offer->supplier_article.': '.$e->getMessage());
            }
        }
        foreach ($plan as $slug => $count) {
            $this->line(sprintf('audit.teplotop_create.category=%s:%d', $slug, $count));
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.teplotop_create.{$key}={$value}");
        }

        return self::SUCCESS;
    }

    public static function groupOf(string $sheet): string
    {
        // «Сопутствующие к котлам» is the other goods.
        if (preg_match('/сопутств/iu', $sheet)) {
            return 'other';
        }
        foreach (self::GROUPS as $group => $pattern) {
            if (preg_match($pattern, $sheet)) {
                return $group;
            }
        }

        return 'other';
    }

    /** Our categories of a row, the first existing one wins. */
    public static function targetCategories(string $sheet, string $name): array
    {
        $s = mb_strtolower($sheet.' '.$name);

        return match (true) {
            // The «Патриот» flue system of the boiler sheet is not a boiler.
            str_contains($s, 'дымоудален') => [],
            str_contains($s, 'водоногрев') || str_contains($s, 'водонагрев') => ['vodogreynaya-kolonka'],
            str_contains($s, 'электрокотл') || str_contains($s, 'proplus') => ['elektricheskie'],
            str_contains($s, 'форвард') => ['tverdotoplivnye'],
            str_contains($s, 'котл') => ['gazovye'],
            str_contains($s, 'lemax-') => ['stalnye-radiatory', 'stalnye-panelnye-radiatory', 'radiatory-stalnye'],
            default => [],
        };
    }

    /** «UNO-20» → «Газовый котел Лемакс UNO 20»; «LMX TURBO-24» → «Газовая колонка Лемакс Turbo 24». */
    public static function cardTitle(string $sheet, string $name): string
    {
        $model = trim((string) preg_replace(['/^lmx\s+/iu', '/\s*\(без усд\)/iu', '/(\p{L})-(\d)/u'], ['', ' без УСД', '$1 $2'], $name));
        $model = (string) preg_replace_callback('/\b([A-Z]{4,})\b/u', fn (array $m) => match ($m[1]) {
            'PRIME', 'UNO' => $m[1],
            'PROPLUS' => 'ProPlus',
            default => Str::title(mb_strtolower($m[1])),
        }, $model);
        $s = mb_strtolower($sheet);
        $type = match (true) {
            str_contains($s, 'водоногрев') || str_contains($s, 'водонагрев') => 'Газовая колонка',
            str_contains($s, 'электрокотл') => 'Электрический котел',
            str_contains($s, 'форвард') || str_contains(mb_strtolower($name), 'форвард') => 'Твердотопливный котел',
            str_contains($s, 'напольн') => 'Котел газовый напольный',
            default => 'Газовый котел',
        };

        return trim($type.' Лемакс '.$model);
    }

    private function createCard(SupplierProduct $offer, string $name, string $slug, int $categoryId, int $brandId): Product
    {
        $raw = (array) $offer->raw;
        $title = ($raw['brand'] ?? '') === 'LEMAX' ? self::cardTitle((string) ($raw['sheet'] ?? ''), $name) : $name;
        $sku = 'TEPLOTOP-'.Str::upper(Str::limit(Str::slug((string) $offer->supplier_article), 60, ''));
        $specs = collect((array) ($raw['specs'] ?? []))->map(fn ($value, $key) => ['key' => (string) $key, 'value' => (string) $value])->values()->all();
        $product = Product::query()->create([
            'category_id' => $categoryId,
            'brand_id' => $brandId ?: null,
            'name' => $title,
            'h1' => $title,
            'slug' => $this->uniqueSlug($title),
            'sku' => $sku,
            'price' => (float) ($raw['retail'] ?? 0),
            'currency' => 'BYN',
            'short_description' => Str::limit($title.'. Официальная поставка в Беларусь, гарантия производителя.', 250, ''),
            'images' => [],
            'specs' => array_merge($specs, [['key' => 'Производитель', 'value' => ($raw['brand'] ?? '') === 'LEMAX' ? 'Лемакс' : (string) ($raw['brand'] ?? '')]]),
            'unit' => 'шт',
            'is_active' => false,
            'created_via' => Product::CREATED_VIA_AWAITING_PHOTOS,
            'is_archived' => false,
            'is_new' => true,
            'availability_status' => Product::AVAILABILITY_CHECK,
            'meta_title' => Str::limit($title, 60, '').' купить в %city%',
            'meta_description' => Str::limit($title.' — цена, наличие и доставка по Беларуси.', 175, ''),
        ]);
        $offer->forceFill(['product_id' => $product->id, 'product_sku' => $sku, 'match_status' => 'matched', 'match_confidence' => 'created_from_price'])->save();

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

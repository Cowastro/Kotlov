<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\ProductSourceEnricher;
use App\Services\SupplierProductAvailabilityService;
use App\Services\Suppliers\Blk7Catalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cards for the БЛК7 items no card of ours carries (Lavoro boilers): the title of the site, the retail price, the
 * photos and «Ключ: значение» specs of the item page on b2b.blk7.by, the description written from them. Pellet
 * boilers go to «Котлы на пеллетах», the others to «Твердотопливные». A card is never made where the brand has one
 * of the same model; an item without a retail price is skipped.
 *
 *   php artisan supplier:blk7-create                       # plan
 *   php artisan supplier:blk7-create --limit=20 --apply    # create and fill
 */
class CreateBlk7CardsCommand extends Command
{
    protected $signature = 'supplier:blk7-create
        {--brand=lavoro : Brand of the site}
        {--limit=0 : At most this many cards}
        {--apply : Create, link and fill the cards}';

    protected $description = 'Create cards for БЛК7 items (Lavoro) with the photos and specs of b2b.blk7.by';

    public function handle(Blk7Catalog $catalog, ProductSourceEnricher $enricher): int
    {
        $supplier = Supplier::query()->where('code', SyncBlk7Command::SUPPLIER_CODE)->first();
        $brand = (string) $this->option('brand');
        $brandIds = SyncBlk7Command::brandIds($brand);
        if (! $supplier || $brandIds === []) {
            $this->line('metric.blk7_create.supplier='.(int) (bool) $supplier);
            $this->line('metric.blk7_create.brand='.count($brandIds));

            return self::FAILURE;
        }
        $categories = DB::table('categories')->whereIn('slug', ['kotly-na-pelletah', 'tverdotoplivnye'])->pluck('id', 'slug');
        $existing = DB::table('products')->whereIn('brand_id', $brandIds)->where('is_archived', false)->pluck('name')
            ->map(fn ($n) => Blk7Catalog::modelKey((string) $n))->filter()->flip();
        $offers = SupplierProduct::query()->where('supplier_id', $supplier->id)->whereNull('product_id')->where('stock_status', '!=', 'out_of_stock')
            ->orderBy('id')->get()->filter(fn (SupplierProduct $o) => empty($o->raw['no_auto_link']))->values();

        $stats = ['offers' => $offers->count(), 'planned' => 0, 'card_exists' => 0, 'no_price' => 0, 'no_category' => 0, 'created' => 0, 'with_photo' => 0, 'errors' => 0];
        $limit = (int) $this->option('limit');
        foreach ($offers as $offer) {
            $raw = (array) $offer->raw;
            $code = mb_strtolower((string) $offer->supplier_article);
            $key = (string) ($raw['model'] ?? Blk7Catalog::modelKey((string) $offer->supplier_name));
            if ($key === '' || isset($existing[$key])) {
                $stats['card_exists']++;
                $this->line('audit.blk7_create.card_exists='.$code);

                continue;
            }
            if ((float) ($raw['retail'] ?? 0) <= 0) {
                $stats['no_price']++;
                $this->line('audit.blk7_create.no_price='.$code);

                continue;
            }
            $slug = str_starts_with(mb_strtolower((string) $offer->supplier_name), 'пеллет') ? 'kotly-na-pelletah' : 'tverdotoplivnye';
            if (! isset($categories[$slug])) {
                $stats['no_category']++;
                $this->line('audit.blk7_create.no_category='.$code);

                continue;
            }
            $stats['planned']++;
            $this->line(sprintf('audit.blk7_create.offer=%s:%s', $code, $slug));
            if (! $this->option('apply') || ($limit > 0 && $stats['created'] >= $limit)) {
                continue;
            }
            try {
                $page = $catalog->page((string) $offer->source_url);
                $product = $this->createCard($offer, (int) $categories[$slug], $brandIds[0], $page['specs']);
                $existing[$key] = true;
                $stats['created']++;
                $images = $page['images'] ?: array_filter([(string) ($raw['image'] ?? '')]);
                $specs = collect($page['specs'])->map(fn ($v, $k) => ['key' => (string) $k, 'value' => (string) $v])->values()->all();
                $enricher->enrichFromParsed($product, (string) $offer->source_url, [
                    'images' => [],
                    'specs' => $specs,
                    'description' => trim($product->name.'. '.collect($page['specs'])->map(fn ($v, $k) => $k.': '.$v)->implode('; ')),
                ], [
                    'verified_images' => array_values($images), 'update_images' => $images !== [], 'update_specs' => true, 'replace_specs' => true,
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
        foreach ($stats as $k => $v) {
            $this->line("metric.blk7_create.{$k}={$v}");
        }

        return self::SUCCESS;
    }

    private function createCard(SupplierProduct $offer, int $categoryId, int $brandId, array $siteSpecs): Product
    {
        $raw = (array) $offer->raw;
        $title = trim((string) $offer->supplier_name);
        $sku = 'BLK7-'.Str::upper((string) $offer->supplier_article);
        $specs = [['key' => 'Производитель', 'value' => 'Lavoro'], ['key' => 'Модель', 'value' => (string) preg_replace('/^.*?(Lavoro)/u', '$1', $title)]];
        $product = Product::query()->create([
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'name' => $title,
            'h1' => $title,
            'slug' => $this->uniqueSlug($title),
            'sku' => $sku,
            'price' => (float) ($raw['retail'] ?? 0),
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

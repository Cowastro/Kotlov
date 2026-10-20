<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\ProductSourceEnricher;
use App\Services\SupplierProductAvailabilityService;
use App\Services\Suppliers\EurotermCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EnrichEurotermCatalogCommand extends Command
{
    protected $signature = 'supplier:enrich-euroterm
        {--apply : Save exact official URLs and product content}
        {--create : Create missing catalog cards after a confident official match}
        {--limit=0 : Maximum supplier offers to process; 0 means all}
        {--offset=0 : Skip supplier offers}
        {--force : Replace existing photos, characteristics and source content}
        {--sleep=250 : Delay between product-card requests, milliseconds}';

    protected $description = 'Евротерм: link official cards, create missing products and fill photos, specs and descriptions.';

    public function handle(EurotermCatalog $catalog, ProductSourceEnricher $enricher): int
    {
        $lock = Cache::lock('supplier:enrich-euroterm', 7200);
        if (! $lock->get()) {
            $this->error('Наполнение Евротерма уже выполняется.');

            return self::FAILURE;
        }

        try {
            return $this->executeEnrichment($catalog, $enricher);
        } finally {
            $lock->release();
        }
    }

    private function executeEnrichment(EurotermCatalog $catalog, ProductSourceEnricher $enricher): int
    {
        $apply = (bool) $this->option('apply');
        $create = (bool) $this->option('create');
        $force = (bool) $this->option('force');
        $supplier = Supplier::query()->where('code', 'euroterm')->firstOrFail();
        $query = SupplierProduct::query()
            ->where('supplier_id', $supplier->id)
            ->orderBy('id');
        $offset = max(0, (int) $this->option('offset'));
        $limit = max(0, (int) $this->option('limit'));
        if ($offset > 0) {
            $query->offset($offset);
        }
        if ($limit > 0) {
            $query->limit($limit);
        }
        $offers = $query->get();
        $indexes = [];
        $stats = [
            'offers' => $offers->count(),
            'official_matched' => 0,
            'official_unmatched' => 0,
            'existing_cards' => 0,
            'created_cards' => 0,
            'enriched_cards' => 0,
            'images_saved' => 0,
            'specs_found' => 0,
            'content_found' => 0,
            'crawl_errors' => 0,
            'preview_errors' => 0,
            'write_errors' => 0,
            'errors' => 0,
        ];

        foreach ($offers as $index => $offer) {
            $row = is_array($offer->raw) ? $offer->raw : [];
            $row += [
                'article' => $offer->supplier_article,
                'name' => $offer->supplier_name,
                'source_url' => $offer->source_url,
            ];
            $categoryUrl = $catalog->categoryUrl($row);
            if ($categoryUrl === '' || $categoryUrl === EurotermCatalog::BASE_URL.'/') {
                $stats['official_unmatched']++;
                continue;
            }

            $phase = 'crawl';
            try {
                if (! isset($indexes[$categoryUrl])) {
                    $indexes[$categoryUrl] = $catalog->crawl($categoryUrl);
                }
                $phase = 'match';
                $match = $catalog->match($row, $indexes[$categoryUrl]);
                $card = $match['card'];
                if (! $card) {
                    $stats['official_unmatched']++;
                    continue;
                }
                $stats['official_matched']++;
                $product = $offer->product_id ? Product::query()->find($offer->product_id) : null;
                if ($product) {
                    $stats['existing_cards']++;
                }

                $phase = 'preview';
                $parsed = $enricher->preview($card['url']);
                $usable = $parsed['images'] !== []
                    && ($parsed['specs'] !== [] || trim((string) $parsed['description']) !== '');
                if (! $usable) {
                    $stats['errors']++;
                    continue;
                }

                $phase = 'write';
                if ($apply) {
                    $product = DB::transaction(function () use ($offer, $row, $card, $match, $product, $supplier, $create): ?Product {
                        $product ??= $create ? $this->createProduct($row, $card, $supplier) : null;
                        $raw = is_array($offer->raw) ? $offer->raw : [];
                        $raw['official_url'] = $card['url'];
                        $raw['official_title'] = $card['title'];
                        $raw['official_match_score'] = $match['score'];
                        $raw['official_match_margin'] = $match['margin'];
                        $offer->forceFill([
                            'product_id' => $product?->id,
                            'product_sku' => $product?->sku,
                            'source_url' => $card['url'],
                            'match_status' => $product ? 'matched' : $offer->match_status,
                            'match_confidence' => $product ? 'official_catalog' : $offer->match_confidence,
                            'raw' => $raw,
                            'last_synced_at' => now(),
                        ])->save();

                        return $product;
                    });
                }

                if (! $product) {
                    if ($create) {
                        $stats['created_cards']++;
                    }
                    continue;
                }

                if ($apply && $product->wasRecentlyCreated) {
                    $stats['created_cards']++;
                }

                $result = $enricher->enrichFromParsed($product, $card['url'], $parsed, [
                    'preview_only' => ! $apply,
                    'replace_images' => $force || empty($product->images),
                    'update_images' => true,
                    'replace_specs' => $force || empty($product->specs),
                    'min_specs_to_replace' => 1,
                    'update_specs' => true,
                    'update_service' => true,
                    'update_documents' => true,
                    'update_video' => true,
                    'update_content' => true,
                    'source_content' => true,
                    'fallback_remote_images' => true,
                ]);
                $stats['enriched_cards']++;
                $stats['images_saved'] += (int) ($result['images_saved'] ?? 0);
                $stats['specs_found'] += (int) ($result['specs_found'] ?? 0);
                $stats['content_found'] += (int) ($result['content_found'] ?? 0);

                if ($apply) {
                    app(SupplierProductAvailabilityService::class)->refreshMany([$product->id]);
                }
            } catch (\Throwable $exception) {
                $stats['errors']++;
                $errorKey = match ($phase) {
                    'crawl', 'match' => 'crawl_errors',
                    'preview' => 'preview_errors',
                    default => 'write_errors',
                };
                $stats[$errorKey]++;
                report($exception);
            }

            if ($index < $offers->count() - 1) {
                usleep(max(0, (int) $this->option('sleep')) * 1000);
            }
        }

        foreach ($stats as $key => $value) {
            $this->line("metric.euroterm_enrich.{$key}={$value}");
        }

        return $stats['errors'] > 0 && $stats['enriched_cards'] === 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @param array<string,mixed> $row @param array{title:string,url:string,image:string} $card */
    private function createProduct(array $row, array $card, Supplier $supplier): Product
    {
        $article = trim((string) ($row['article'] ?? ''));
        $articleKey = preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtoupper($article)) ?: Str::upper(Str::random(8));
        $sku = 'EUROTERM-'.$articleKey;
        if ($existing = Product::query()->where('sku', $sku)->first()) {
            return $existing;
        }

        $category = $this->category($row);
        $brandName = trim((string) ($row['brand'] ?? '')) ?: 'PROFLine';
        $brand = Brand::query()->firstOrCreate(
            ['slug' => Str::slug($brandName)],
            ['name' => $brandName, 'h1' => $brandName, 'is_active' => true],
        );
        $name = trim((string) ($card['title'] ?? $row['name'] ?? ''));
        $baseSlug = Str::slug($name) ?: 'euroterm-'.Str::lower($articleKey);
        $slug = $baseSlug;
        if (Product::query()->where('slug', $slug)->exists()) {
            $slug .= '-'.Str::lower($articleKey);
        }
        $stock = (int) ($row['stock_quantity'] ?? 0);

        return Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'supplier_id' => $supplier->id,
            'name' => $name,
            'h1' => $name,
            'slug' => $slug,
            'sku' => $sku,
            'price' => (float) ($row['retail'] ?? 0),
            'currency' => 'BYN',
            'unit' => trim((string) ($row['unit'] ?? '')) ?: 'шт',
            'content' => '',
            'short_description' => '',
            'images' => [],
            'specs' => [],
            'service_info' => [],
            'documents' => [],
            'content_source_url' => $card['url'],
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => $stock > 0,
            'stock_qty' => $stock,
            'availability_status' => $stock > 0 ? Product::AVAILABILITY_IN_STOCK : Product::AVAILABILITY_CHECK,
            'requires_price_confirmation' => false,
            'manual_stock_management' => false,
            'meta_title' => $name.' купить в %city% | KOTLOV.BY',
            'meta_description' => $name.'. Цена, наличие, характеристики и доставка по Беларуси на KOTLOV.BY.',
        ]);
    }

    /** @param array<string,mixed> $row */
    private function category(array $row): Category
    {
        $category = mb_strtolower((string) ($row['category'] ?? ''));
        $name = mb_strtolower((string) ($row['name'] ?? ''));
        $lookup = match (true) {
            str_contains($category, 'газовые') => ['id' => 53],
            str_contains($category, 'электрические') => ['id' => 55],
            str_contains($category, 'косвенного') => ['id' => 100],
            str_contains($category, 'коллекторы') => ['id' => 93],
            str_contains($category, 'дымоудаления') && preg_match('/отвод|колен/u', $name) => ['slug' => 'kolena-mono'],
            str_contains($category, 'дымоудаления') && preg_match('/труб|удлин/u', $name) => ['slug' => 'truby-mono'],
            str_contains($category, 'дымоудаления') && preg_match('/адаптер|переход/u', $name) => ['slug' => 'perehody-adaptery-dymohod'],
            str_contains($category, 'дымоудаления') && preg_match('/конденсат/u', $name) => ['slug' => 'kondensatootvody'],
            str_contains($category, 'дымоудаления') && preg_match('/хомут|креплен|фланец|проклад|уплотнен|наклад|втулк/u', $name) => ['slug' => 'krepleniya-dymohod'],
            str_contains($category, 'дымоудаления') => ['slug' => 'prochie-dymohod'],
            default => ['id' => 58],
        };
        $resolved = isset($lookup['id'])
            ? Category::query()->find($lookup['id'])
            : Category::query()->where('slug', $lookup['slug'])->first();

        return $resolved ?: Category::query()->where('slug', 'komplektuyushhie-dlya-otopleniya')->firstOrFail();
    }
}

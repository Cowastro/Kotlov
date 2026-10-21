<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\ProductSourceEnricher;
use App\Services\SupplierProductAvailabilityService;
use App\Services\Suppliers\TeplodvorOfferMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuditEurotermTeplodvorCommand extends Command
{
    protected $signature = 'supplier:audit-euroterm-teplodvor
        {--unlinked-only : Check only Euroterm offers without a product card}
        {--verify-pages : Fetch accepted Teplodvor pages and require usable content}
        {--apply : Save exact unlinked matches and enrich the created cards}
        {--create : Create missing product cards; requires --apply and --unlinked-only}
        {--sleep=250 : Delay between accepted page requests, milliseconds}';

    protected $description = 'Cross-check Euroterm offers against Teplodvor and optionally create exact unlinked matches.';

    public function handle(TeplodvorOfferMatcher $matcher, ProductSourceEnricher $enricher): int
    {
        $apply = (bool) $this->option('apply');
        $create = (bool) $this->option('create');
        if ($apply && (! $create || ! $this->option('unlinked-only'))) {
            $this->error('Safe apply requires both --create and --unlinked-only.');

            return self::FAILURE;
        }

        $supplier = Supplier::query()->where('code', 'euroterm')->firstOrFail();
        $indexPath = storage_path('teplodvor_index.json');
        $index = is_file($indexPath) ? json_decode((string) file_get_contents($indexPath), true) : [];
        if (! is_array($index) || $index === []) {
            $this->error('Teplodvor index is missing or empty.');

            return self::FAILURE;
        }

        $query = SupplierProduct::query()->where('supplier_id', $supplier->id)->orderBy('id');
        if ($this->option('unlinked-only')) {
            $query->whereNull('product_id');
        }

        $stats = [
            'index_urls' => count($index),
            'offers' => 0,
            'linked_offers' => 0,
            'unlinked_offers' => 0,
            'matched' => 0,
            'matched_linked' => 0,
            'matched_unlinked' => 0,
            'article_matches' => 0,
            'token_matches' => 0,
            'verified_pages' => 0,
            'usable_pages' => 0,
            'unusable_pages' => 0,
            'created_cards' => 0,
            'enriched_cards' => 0,
            'images_saved' => 0,
            'specs_found' => 0,
            'content_found' => 0,
            'write_errors' => 0,
            'no_match' => 0,
            'errors' => 0,
        ];

        foreach ($query->get() as $offer) {
            $stats['offers']++;
            $linked = (int) $offer->product_id > 0;
            $stats[$linked ? 'linked_offers' : 'unlinked_offers']++;
            $raw = is_array($offer->raw) ? $offer->raw : [];
            $match = $matcher->match([
                'article' => $offer->supplier_article,
                'name' => $offer->supplier_name,
                'brand' => $raw['brand'] ?? '',
            ], $index);

            if (! $match['url']) {
                $stats['no_match']++;
                continue;
            }

            $stats['matched']++;
            $stats[$linked ? 'matched_linked' : 'matched_unlinked']++;
            $stats[$match['method'] === 'article' ? 'article_matches' : 'token_matches']++;
            if (! $linked) {
                $article = preg_replace('/[^\p{L}\p{N}._ -]+/u', '', (string) $offer->supplier_article) ?: 'no-article';
                $this->line('candidate.euroterm_teplodvor.unlinked='.$article.'|'.$match['url']);
            }

            if (! $this->option('verify-pages') && ! $apply) {
                continue;
            }

            try {
                $parsed = $enricher->preview($match['url']);
                $usable = ($parsed['images'] ?? []) !== []
                    && (($parsed['specs'] ?? []) !== [] || trim((string) ($parsed['description'] ?? '')) !== '');
                $stats['verified_pages']++;
                $stats[$usable ? 'usable_pages' : 'unusable_pages']++;

                if (! $apply || ! $usable || $linked || $match['method'] !== 'article') {
                    continue;
                }

                $product = DB::transaction(function () use ($offer, $raw, $match, $supplier): Product {
                    $product = $this->createProduct($offer, $raw, $match['url'], $supplier);
                    $offerRaw = is_array($offer->raw) ? $offer->raw : [];
                    $offerRaw['content_source_url'] = $match['url'];
                    $offerRaw['content_match_method'] = $match['method'];
                    $offerRaw['content_match_score'] = $match['score'];
                    $offerRaw['content_match_margin'] = $match['margin'];
                    $offer->forceFill([
                        'product_id' => $product->id,
                        'product_sku' => $product->sku,
                        'match_status' => 'matched',
                        'match_confidence' => 'teplodvor_exact_article',
                        'raw' => $offerRaw,
                        'last_synced_at' => now(),
                    ])->save();

                    return $product;
                });
                if ($product->wasRecentlyCreated) {
                    $stats['created_cards']++;
                }

                $result = $enricher->enrichFromParsed($product, $match['url'], $parsed, [
                    'preview_only' => false,
                    'replace_images' => empty($product->images),
                    'update_images' => true,
                    'replace_specs' => empty($product->specs),
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
                app(SupplierProductAvailabilityService::class)->refreshMany([$product->id]);
            } catch (\Throwable $exception) {
                $stats['errors']++;
                if ($apply) {
                    $stats['write_errors']++;
                }
                report($exception);
            }

            usleep(max(0, (int) $this->option('sleep')) * 1000);
        }

        foreach ($stats as $key => $value) {
            $this->line("metric.euroterm_teplodvor.{$key}={$value}");
        }

        if ($apply && $stats['write_errors'] > 0) {
            return self::FAILURE;
        }

        return $stats['errors'] > 0 && $stats['verified_pages'] === 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @param array<string,mixed> $raw */
    private function createProduct(SupplierProduct $offer, array $raw, string $contentSourceUrl, Supplier $supplier): Product
    {
        $article = trim((string) $offer->supplier_article);
        $articleKey = preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtoupper($article)) ?: Str::upper(Str::random(8));
        $sku = 'EUROTERM-'.$articleKey;
        if ($existing = Product::query()->where('sku', $sku)->first()) {
            return $existing;
        }

        $brandName = trim((string) ($raw['brand'] ?? '')) ?: 'PROFLine';
        $brand = Brand::query()->firstOrCreate(
            ['slug' => Str::slug($brandName)],
            ['name' => $brandName, 'h1' => $brandName, 'is_active' => true],
        );
        $name = $this->productName($offer);
        $baseSlug = Str::slug($name) ?: 'euroterm-'.Str::lower($articleKey);
        $slug = Product::query()->where('slug', $baseSlug)->exists()
            ? $baseSlug.'-'.Str::lower($articleKey)
            : $baseSlug;
        $stock = (int) ($raw['stock_quantity'] ?? $offer->stock_quantity ?? 0);

        return Product::query()->create([
            'category_id' => Category::query()->findOrFail(93)->id,
            'brand_id' => $brand->id,
            'supplier_id' => $supplier->id,
            'name' => $name,
            'h1' => $name,
            'slug' => $slug,
            'sku' => $sku,
            'price' => (float) ($raw['retail'] ?? 0),
            'currency' => 'BYN',
            'unit' => trim((string) ($raw['unit'] ?? '')) ?: 'шт',
            'content' => '',
            'short_description' => '',
            'images' => [],
            'specs' => [],
            'service_info' => [],
            'documents' => [],
            'content_source_url' => $contentSourceUrl,
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

    private function productName(SupplierProduct $offer): string
    {
        $article = trim((string) $offer->supplier_article);
        if (preg_match('/^(XF\d+[A-Z]*)\s+1[\"″]\((\d+)way\)$/iu', $article, $matches)) {
            return sprintf(
                'Коллектор распределительный латунный PROFLine %s 1″, %d выходов',
                mb_strtoupper($matches[1]),
                (int) $matches[2],
            );
        }

        return trim((string) $offer->supplier_name) ?: 'Товар Евротерм '.$article;
    }
}

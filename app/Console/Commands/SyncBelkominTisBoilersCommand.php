<?php

namespace App\Console\Commands;

use App\Services\AiContentEnricher;
use App\Services\Pricing\CurrencyPriceConverter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncBelkominTisBoilersCommand extends Command
{
    protected $signature = 'supplier:sync-belkomin-tis-boilers
        {--apply : Write changes to the database}
        {--dry-run : Preview without writing changes to the database}
        {--limit= : Limit number of products for testing}
        {--no-images : Do not download product images}
        {--enrich : Generate unique SEO descriptions via Claude API (requires ANTHROPIC_API_KEY)}
        {--sleep=150 : Delay between product requests in milliseconds}
        {--purge-badge-images : Delete already-downloaded image files that are actually belkomin.com\'s site-wide "4% credit" promo badge (image2026/4procent.webp), which an old bug saved as the main product photo}';

    protected $description = 'Scrape belkomin.com TIS boilers and sync prices, cards, photos and attributes.';

    private const SUPPLIER_CODE = 'belkomin';
    private const SYNC_KEY = 'belkomin_tis_boilers';
    private const CATEGORY_ID = 54;
    private const BRAND_ID = 211;
    private const SOURCE_URL = 'https://www.belkomin.com/katalog/kotly/';
    private const BASE_URL = 'https://www.belkomin.com/';

    private const SERIES_URLS = [
        'katalog/kotlyi-tis-small/',
        'katalog/kotlyi-tis-comfort-pro/',
        'katalog/kotlyi-tis-pro/',
        'katalog/kotlyi-tis-pro-dr/',
        'katalog/kotlyi-tis-comfort-plus/',
        'katalog/kotlyi-tis-plus/',
        'katalog/kotlyi-tis-plus-dr/',
        'katalog/kotlyi-tis-uni-n-(new)/',
        'katalog/kotlyi-tis-uni/',
        'katalog/kotlyi-tis-hard-plus/',
        'katalog/kotlyi-tisnew-pellet/',
        'katalog/kotlyi-tisnew-duo-pellet/',
        'katalog/kotlyi-tis-hard-pellet/',
        'katalog/kotlyi-tis-hard-duo-pellet/',
        'katalog/tverdotoplivnyie-kotlyi-tis-hard-bio/',
        'katalog/kotlyi-tis-hard-eko/',
    ];

    private string $supplierCurrency = CurrencyPriceConverter::BASE_CURRENCY;
    private float $supplierRate = 1.0;

    public function handle(): int
    {
        $apply          = (bool) $this->option('apply');
        $limit          = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $downloadImages = ! (bool) $this->option('no-images');
        $enrichContent  = (bool) $this->option('enrich');

        $enricher = new AiContentEnricher();
        if ($enrichContent && ! $enricher->isAvailable()) {
            $this->warn('--enrich: no AI provider configured, enrichment skipped.');
            $enrichContent = false;
        }

        if ($this->option('purge-badge-images')) {
            $this->purgeBadgeImages($apply);
        }

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: database will be updated.</>'
            : '<fg=yellow;options=bold>DRY RUN: database will not be changed.</>');

        try {
            $items = $this->scrapeCatalog();
        } catch (\Throwable $e) {
            $this->error('Catalog scrape failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        if ($limit !== null && $limit > 0) {
            $items = array_slice($items, 0, $limit);
        }

        $this->info('Found TIS boilers: ' . count($items));

        try {
            $this->loadSupplierCurrency();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->line(sprintf('Supplier currency: %s, rate to BYN: %s', $this->supplierCurrency, $this->supplierRate));

        if (! $apply) {
            $previewSupplierId = (int) (DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id') ?? 0);
            $preview = [];
            foreach ($items as $item) {
                $priceByn = CurrencyPriceConverter::convertToByn($item['price'], $this->supplierCurrency, $this->supplierRate);

                try {
                    $detail = $this->scrapeProduct($item['url']);
                    $preview[] = [
                        'article' => $item['article'],
                        'price' => $item['price'] !== null
                            ? number_format($item['price'], 2, '.', '') . ' ' . $this->supplierCurrency
                            : 'no price',
                        'price_byn' => $priceByn !== null ? number_format($priceByn, 2, '.', '') : '—',
                        'product' => $this->previewPriceAction($item, $priceByn, $previewSupplierId),
                        'images' => count(array_unique(array_filter(array_merge([$item['listing_image']], $detail['images_remote'] ?? [])))),
                        'attributes' => count(($item['attributes'] ?? []) + ($detail['attributes'] ?? []) + $this->derivedSpecs($item)),
                        'name' => mb_substr($item['name'], 0, 48),
                    ];
                } catch (\Throwable $e) {
                    $preview[] = [
                        'article' => $item['article'],
                        'price' => 'error',
                        'price_byn' => '—',
                        'product' => 'error',
                        'images' => 'error',
                        'attributes' => 'error',
                        'name' => mb_substr($item['name'], 0, 48),
                    ];
                }

                usleep(max(0, (int) $this->option('sleep')) * 1000);
            }

            $this->table(['article', 'price', 'price_byn', 'product', 'images', 'attributes', 'name'], $preview);
            $this->line('Run with --apply to update products, attributes and images.');
            return self::SUCCESS;
        }

        $now = now();
        $supplierId = $this->ensureSupplier($now);
        $syncId = $this->ensureSupplierSync($now);
        $this->ensureBrand($now);
        $this->ensureCategory($now);

        $stats = [
            'created' => 0,
            'updated' => 0,
            'attributes' => 0,
            'images' => 0,
            'errors' => 0,
        ];

        foreach ($items as $i => $item) {
            $this->line(sprintf('[%d/%d] %s', $i + 1, count($items), $item['article']));

            try {
                $detail = $this->scrapeProduct($item['url']);
                $merged = array_merge($item, $detail);
                $merged['attributes'] = ($item['attributes'] ?? []) + ($detail['attributes'] ?? []);
                $merged['price_byn'] = CurrencyPriceConverter::convertToByn($merged['price'], $this->supplierCurrency, $this->supplierRate);
                $merged['price_old_byn'] = CurrencyPriceConverter::convertToByn($merged['price_old'], $this->supplierCurrency, $this->supplierRate);

                $product = $this->findProduct($merged, $supplierId);
                $isNew = ! $product;
                $images = [];

                if ($isNew && $enrichContent) {
                    $aiText = $enricher->enrich($item['name'], 'TIS', $merged['content'] ?? null, $merged['attributes'] ?? []);
                    if ($aiText) {
                        $merged['content'] = $aiText;
                        $this->line('  <fg=cyan>AI content generated.</>');
                    }
                }

                if ($downloadImages) {
                    $images = $this->downloadImages($merged);
                    $stats['images'] += count($images);
                }

                $productId = $this->upsertProduct($merged, $product, $images, $now);
                $productSku = (string) DB::table('products')->where('id', $productId)->value('sku');

                $this->upsertSupplierProduct($merged, $productId, $productSku, $supplierId, $syncId, $now);
                $stats['attributes'] += $this->syncAttributes($productId, $merged, $now);

                $stats[$isNew ? 'created' : 'updated']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn('  failed: ' . $e->getMessage());
            }

            usleep(max(0, (int) $this->option('sleep')) * 1000);
        }

        $this->table(['metric', 'count'], array_map(
            fn($key, $value) => [$key, $value],
            array_keys($stats),
            array_values($stats)
        ));

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function scrapeCatalog(): array
    {
        $items = [];

        foreach (self::SERIES_URLS as $path) {
            $url = $this->absoluteUrl($path);
            $html = $this->fetch($url);
            $series = $this->cleanText($this->match('/<h1[^>]*>(.*?)<\/h1>/u', $html) ?? '');

            foreach ($this->productNodes($html) as $nodeHtml) {
                $item = $this->parseListItem($nodeHtml, $url, $series);
                if (! $item) {
                    continue;
                }

                $items[$item['article_normalized']] = $item;
            }
        }

        return array_values($items);
    }

    private function productNodes(string $html): array
    {
        preg_match_all('/<div class="product-item_new_kot">([\s\S]*?)(?=<div class="product-item_new_kot">|<\/div>\s*<\/div>\s*<\/div>\s*<\/div>\s*<div class="clearfix"|<div class="pagination"|<footer>)/u', $html, $matches);

        return $matches[0] ?? [];
    }

    private function parseListItem(string $html, string $seriesUrl, string $series): ?array
    {
        $name = $this->cleanText($this->match('/<a[^>]+class="h4"[^>]+>(.*?)<\/a>/u', $html) ?? '');
        $url = $this->match('/<a[^>]+class="h4"[^>]+href="([^"]+)"/u', $html)
            ?: $this->match('/<div class="full-link"><a[^>]+href="([^"]+)"/u', $html);
        $image = $this->match('/<img[^>]+src="([^"]+)"/u', $html);

        if ($name === '' || ! $url) {
            return null;
        }

        $attrs = [];
        preg_match_all('/<p>\s*([^:<]+):\s*<span>([\s\S]*?)<\/span>\s*<\/p>/u', $html, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $attrs[$this->normalizeAttributeName($this->cleanText($match[1]))] = $this->cleanText($match[2]);
        }

        $priceBlock = $this->match('/<div class="price">([\s\S]*?)<\/div>/u', $html) ?? '';
        $salePrice = $this->parsePrice($this->match('/<span class="oldp">([\s\S]*?)<\/span>/u', $priceBlock));
        $regularPrice = $this->parsePrice($this->match('/<span class="actp">([\s\S]*?)<\/span>/u', $priceBlock));
        $current = $salePrice ?: $regularPrice;
        $old = $regularPrice && $current && $regularPrice > $current ? $regularPrice : null;
        $absoluteUrl = $this->absoluteUrl($url);
        $article = $this->articleFromUrl($absoluteUrl);

        return [
            'article' => $article,
            'article_normalized' => $this->normalizeSupplierArticle($article),
            'name' => $name,
            'url' => $absoluteUrl,
            'series_url' => $seriesUrl,
            'series' => $series,
            'price' => $current,
            'price_old' => $old,
            'listing_image' => $image ? $this->absoluteUrl($image) : null,
            'attributes' => $attrs,
        ];
    }

    private function scrapeProduct(string $url): array
    {
        $html = $this->fetch($url);
        $h1 = $this->cleanText($this->match('/<h1[^>]*>(.*?)<\/h1>/u', $html) ?? '');
        $descriptionHtml = $this->extractDescriptionHtml($html);
        $description = $this->extractDescription($html);

        return [
            'h1' => $h1 ?: null,
            'content' => $descriptionHtml ?: ($description ? $this->descriptionToHtml($description) : null),
            'meta_title' => $this->cleanText($this->match('/<title>(.*?)<\/title>/u', $html) ?? ''),
            'meta_description' => $this->cleanText($this->match('/<meta name="description" content="([^"]*)"/u', $html) ?? ''),
            'images_remote' => $this->extractImages($html),
            'attributes' => $this->parseDetailAttributes($html),
        ];
    }

    private function parseDetailAttributes(string $html): array
    {
        $attrs = [];
        preg_match_all('/<tr>\s*<td[^>]*class="text-left"[^>]*>\s*<span>([\s\S]*?)<\/span>\s*<\/td>\s*<td[^>]*class="text-right"[^>]*>\s*<span>([\s\S]*?)<\/span>/u', $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $name = $this->normalizeAttributeName($this->cleanText($match[1]));
            $value = $this->cleanText(preg_replace('/<span class="tooltips"><\/span>/u', '', $match[2]) ?? $match[2]);

            if ($name !== '' && $value !== '') {
                $attrs[$name] = $value;
            }
        }

        return $attrs;
    }

    private function extractImages(string $html): array
    {
        $images = [];
        preg_match_all('/<a[^>]+itemprop="contentUrl"[^>]+href="([^"]+)"/u', $html, $matches);
        foreach ($matches[1] ?? [] as $url) {
            $images[] = $this->absoluteUrl($url);
        }

        preg_match_all('/<a[^>]+href="([^"]+)"[^>]+itemprop="contentUrl"/u', $html, $reverseMatches);
        foreach ($reverseMatches[1] ?? [] as $url) {
            $images[] = $this->absoluteUrl($url);
        }

        preg_match_all('/<meta itemprop="image" content="([^"]+)"/u', $html, $metaMatches);
        foreach ($metaMatches[1] ?? [] as $url) {
            $images[] = $this->absoluteUrl($url);
        }

        return array_values(array_unique(array_filter($images)));
    }

    private function extractDescription(string $html): string
    {
        $block = $this->match('/<div class="desc-lvl3">([\s\S]*?)<div class="btn-lvl3/u', $html)
            ?: $this->match('/<div class="desc-lvl3">([\s\S]*?)<\/div>\s*<\/div>/u', $html)
            ?: '';

        return $this->cleanDescription($block);
    }

    private function extractDescriptionHtml(string $html): ?string
    {
        $block = $this->rawMatch('/<div id="bx0"[\s\S]*?<div class="short-desc full">([\s\S]*?)(?=<div class="container">\s*<div id="bx1"|<div id="bx1")/u', $html)
            ?: $this->rawMatch('/<span class="h2">\s*Описание\s*<\/span>([\s\S]*?)(?=<div id="bx1"|<span class="h2">\s*Характеристики)/u', $html);

        if (! $block) {
            return null;
        }

        return $this->cleanDescriptionHtml($block);
    }

    private function upsertProduct(array $item, ?object $product, array $images, $now): int
    {
        if (empty($images) && $product?->images) {
            $images = is_string($product->images) ? (json_decode($product->images, true) ?: []) : (array) $product->images;
        }

        $attrs = ($item['attributes'] ?? []) + $this->derivedSpecs($item);

        $payload = [
            'category_id' => self::CATEGORY_ID,
            'brand_id' => self::BRAND_ID,
            'supplier_id' => null,
            'name' => $item['name'],
            'h1' => $item['h1'] ?: $item['name'],
            'price' => $item['price_byn'] ?? 0,
            'price_old' => $item['price_old_byn'] ?? null,
            'currency' => 'BYN',
            'content' => $item['content'] ?: null,
            'short_description' => null,
            'images' => json_encode($images, JSON_UNESCAPED_UNICODE),
            'specs' => json_encode($attrs, JSON_UNESCAPED_UNICODE),
            'video_url' => null,
            'weight' => $this->parseNumber($attrs['Вес'] ?? $attrs['Масса котла'] ?? null),
            'unit' => 'шт',
            'warranty' => $attrs['Гарантия'] ?? null,
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => $item['price'] !== null,
            'stock_qty' => null,
            'is_featured' => false,
            'is_new' => false,
            'is_sale' => $item['price_old'] !== null && $item['price'] !== null && $item['price_old'] > $item['price'],
            'sort_order' => 0,
            'meta_title' => $item['meta_title'] ?: $item['name'] . ' купить в %city%',
            'meta_keywords' => 'котел TIS, твердотопливный котел, БелКомин, ' . $item['name'],
            'meta_description' => $item['meta_description'] ?: $item['name'],
            'rating' => 0,
            'reviews_count' => 0,
            'views_count' => 0,
            'updated_at' => $now,
        ];

        if ($product) {
            DB::table('products')->where('id', $product->id)->update($payload);
            return (int) $product->id;
        }

        $payload['sku'] = $this->nextKotlovSku();
        $payload['slug'] = $this->uniqueSlug($item['name']);
        $payload['created_at'] = $now;

        return (int) DB::table('products')->insertGetId($payload);
    }

    private function syncAttributes(int $productId, array $item, $now): int
    {
        $count = 0;
        $attrs = ($item['attributes'] ?? []) + $this->derivedSpecs($item);

        foreach ($attrs as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $name = $this->normalizeAttributeName((string) $name);
            $attributeId = $this->ensureAttribute($name, $now);

            DB::table('product_attribute_values')->updateOrInsert(
                ['product_id' => $productId, 'attribute_id' => $attributeId],
                [
                    'option_id' => null,
                    'is_checked' => null,
                    'value' => (string) $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $count++;
        }

        return $count;
    }

    private function ensureAttribute(string $name, $now): int
    {
        $name = $this->normalizeAttributeName($name);

        $existing = DB::table('attributes')
            ->where('category_id', self::CATEGORY_ID)
            ->where('name', $name)
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        return (int) DB::table('attributes')->insertGetId([
            'category_id' => self::CATEGORY_ID,
            'group_id' => 0,
            'sort_order' => 500,
            'type' => 'value',
            'name' => $name,
            'suffix' => null,
            'in_filter' => false,
            'in_sort' => false,
            'in_product' => true,
            'in_brief' => in_array($name, ['Мощность', 'Вид топлива', 'Площадь обогрева', 'Тип котла'], true),
            'is_comparable' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function findProduct(array $item, int $supplierId): ?object
    {
        $supplierProduct = DB::table('supplier_products')
            ->where('supplier_id', $supplierId)
            ->where(function ($query) use ($item) {
                $query->where('supplier_article', $item['article'])
                    ->orWhere('supplier_article_normalized', $item['article_normalized']);
            })
            ->whereNotNull('product_id')
            ->first();

        if ($supplierProduct) {
            $product = DB::table('products')->where('id', $supplierProduct->product_id)->first();
            if ($product) {
                return $product;
            }
        }

        $normalizedName = $this->normalizeProductName($item['name']);
        $candidates = DB::table('products')
            ->where('category_id', self::CATEGORY_ID)
            ->where(function ($query) {
                $query->where('brand_id', self::BRAND_ID)
                    ->orWhere('name', 'like', '%TIS%');
            })
            ->get(['id', 'sku', 'name', 'images', 'price']);

        foreach ($candidates as $candidate) {
            if ($this->normalizeProductName($candidate->name) === $normalizedName) {
                return $candidate;
            }
        }

        return null;
    }

    private function upsertSupplierProduct(array $item, int $productId, string $productSku, int $supplierId, ?int $syncId, $now): void
    {
        DB::table('supplier_products')->updateOrInsert(
            [
                'supplier_id' => $supplierId,
                'supplier_article' => $item['article'],
            ],
            [
                'supplier_article_normalized' => $item['article_normalized'],
                'supplier_sync_id' => $syncId,
                'product_id' => $productId,
                'product_sku' => $productSku,
                'supplier_name' => $item['name'],
                'source_url' => $item['url'],
                'source_wp_id' => null,
                'price' => $item['price'],
                'currency' => $this->supplierCurrency,
                'currency_rate' => $this->supplierRate,
                'price_byn' => $item['price_byn'] ?? null,
                'in_stock' => $item['price'] !== null,
                'match_status' => 'matched',
                'match_confidence' => 'auto_name',
                'raw' => json_encode([
                    'series' => $item['series'] ?? null,
                    'series_url' => $item['series_url'] ?? null,
                    'attributes' => $item['attributes'] ?? [],
                    'images_remote' => $item['images_remote'] ?? [],
                ], JSON_UNESCAPED_UNICODE),
                'last_synced_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    private function downloadImages(array $item): array
    {
        // belkomin.com puts a site-wide "4% credit" promo badge
        // (/image2026/4procent.webp) as the FIRST <img> in every catalog
        // listing card — it is never a real product photo. Real photos
        // only ever come from the product detail page (images_remote).
        // listing_image is kept purely as a last-resort fallback for the
        // rare case a detail page exposes no images at all.
        $remoteImages = $item['images_remote'] ?? [];
        $urls = array_values(array_unique(array_filter(
            $remoteImages !== [] ? $remoteImages : [$item['listing_image'] ?? null]
        )));

        $paths = [];
        $dir = public_path('img/products/belkomin-tis');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        foreach ($urls as $index => $url) {
            try {
                $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION)) ?: 'jpg';
                if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    $ext = 'jpg';
                }

                $filename = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $item['article']) . '-' . ($index + 1) . '.' . $ext;
                $target = $dir . DIRECTORY_SEPARATOR . $filename;

                if (! file_exists($target)) {
                    file_put_contents($target, $this->fetch($url));
                }

                $paths[] = 'img/products/belkomin-tis/' . $filename;
            } catch (\Throwable $e) {
                $this->warn('  image skipped: ' . $url);
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * One-time cleanup for the listing/detail-image ordering bug fixed
     * above: every "…-1.<ext>" file downloaded by an earlier run of this
     * command is the site-wide "4% credit" promo badge, not a product
     * photo (see downloadImages()). Verify by content hash against the
     * live badge before deleting, so a legitimate file is never touched.
     */
    private function purgeBadgeImages(bool $apply): void
    {
        $dir = public_path('img/products/belkomin-tis');
        if (! is_dir($dir)) {
            $this->info('purge-badge-images: directory does not exist, nothing to do.');
            return;
        }

        try {
            $badgeBytes = $this->fetch($this->absoluteUrl('image2026/4procent.webp'));
        } catch (\Throwable $e) {
            $this->error('purge-badge-images: could not fetch reference badge image, aborting purge: ' . $e->getMessage());
            return;
        }
        $badgeHash = md5($badgeBytes);

        $candidates = glob($dir . DIRECTORY_SEPARATOR . '*-1.*') ?: [];
        $toDelete = [];
        foreach ($candidates as $path) {
            if (md5_file($path) === $badgeHash) {
                $toDelete[] = $path;
            }
        }

        $this->info(sprintf(
            'purge-badge-images: %d candidate "-1.*" files, %d match the promo badge by content hash.',
            count($candidates),
            count($toDelete)
        ));

        if (! $apply) {
            foreach ($toDelete as $path) {
                $this->line('  would delete: ' . basename($path));
            }
            return;
        }

        foreach ($toDelete as $path) {
            unlink($path);
        }
        $this->info('purge-badge-images: deleted ' . count($toDelete) . ' badge files.');
    }

    private function ensureSupplier($now): int
    {
        $existing = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->first();

        // Валюту и курс не трогаем — ими управляет админка (Filament SupplierResource).
        if ($existing) {
            DB::table('suppliers')->where('id', $existing->id)->update([
                'name' => 'БелКомин',
                'contact' => self::SOURCE_URL,
                'is_active' => true,
                'updated_at' => $now,
            ]);

            return (int) $existing->id;
        }

        return (int) DB::table('suppliers')->insertGetId([
            'code' => self::SUPPLIER_CODE,
            'name' => 'БелКомин',
            'currency' => 'BYN',
            'currency_rate' => 1,
            'contact' => self::SOURCE_URL,
            'notes' => 'Официальный сайт производителя котлов TIS.',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function loadSupplierCurrency(): void
    {
        $supplier = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->first();

        $this->supplierCurrency = CurrencyPriceConverter::normalizeCurrency($supplier->currency ?? null);
        $this->supplierRate = CurrencyPriceConverter::rateFor($this->supplierCurrency, $supplier->currency_rate ?? 1);
    }

    private function previewPriceAction(array $item, ?float $priceByn, int $supplierId): string
    {
        $product = $this->findProduct($item, $supplierId);

        if (! $product) {
            return 'create';
        }

        if ($priceByn === null) {
            return 'keep price';
        }

        $currentPrice = (float) ($product->price ?? 0);

        if (abs($currentPrice - $priceByn) < 0.01) {
            return 'no change';
        }

        return sprintf('%.2f → %.2f', $currentPrice, $priceByn);
    }

    private function ensureSupplierSync($now): ?int
    {
        DB::table('supplier_syncs')->updateOrInsert(
            ['key' => self::SYNC_KEY],
            [
                'name' => 'БелКомин',
                'code' => self::SUPPLIER_CODE,
                'title' => 'БелКомин: котлы TIS',
                'description' => 'Обновляет цены, характеристики, описания и фотографии твердотопливных котлов TIS.',
                'command' => 'supplier:sync-belkomin-tis-boilers',
                'source_url' => self::SOURCE_URL,
                'image_disk_path' => 'img/products/belkomin-tis',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        return DB::table('supplier_syncs')->where('key', self::SYNC_KEY)->value('id');
    }

    private function ensureBrand($now): void
    {
        DB::table('brands')->updateOrInsert(
            ['id' => self::BRAND_ID],
            [
                'name' => 'TIS',
                'slug' => 'tis-2',
                'h1' => 'TIS',
                'country' => 'Беларусь',
                'is_active' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    private function ensureCategory($now): void
    {
        DB::table('categories')->where('id', self::CATEGORY_ID)->update([
            'is_active' => true,
            'updated_at' => $now,
        ]);
    }

    private function derivedSpecs(array $item): array
    {
        $name = $item['name'];
        $attrs = $item['attributes'] ?? [];

        preg_match('/\bTIS\s+(.+?)\s+([0-9]+)\b/ui', $name, $seriesMatch);

        return array_filter([
            'Производитель' => 'TIS',
            'Тип котла' => str_contains(mb_strtolower($name), 'пеллет') ? 'пеллетный' : 'твердотопливный',
            'Модель/серия' => isset($seriesMatch[1]) ? 'TIS ' . trim($seriesMatch[1]) : null,
            'Мощность' => $attrs['Мощность'] ?? $this->powerFromName($name),
            'Основной вид топлива' => $attrs['Вид топлива'] ?? null,
            'Площадь обогрева' => $attrs['Отапливаемая площадь'] ?? null,
        ], fn($value) => $value !== null && $value !== '');
    }

    private function normalizeAttributeName(string $name): string
    {
        $name = trim(str_replace("\u{A0}", ' ', $name));
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
        $name = preg_replace('/,\s*(кВт|%|мм|см|кг|литр|л|Па|°C)$/ui', '', $name) ?? $name;

        return match ($name) {
            'Мощность номинальная' => 'Мощность номинальная',
            'Мощность' => 'Мощность',
            'Отапливаемая площадь',
            'Обогреваемая площадь',
            'Обогреваемая площадь (m2)' => 'Площадь обогрева',
            'Объем топки' => 'Объем топки',
            'Объем бункера' => 'Объем бункера',
            'КПД' => 'Максимальный КПД',
            'Масса котла',
            'Масса' => 'Вес',
            'Ширина котла',
            'Ширина' => 'Ширина',
            'Длина котла',
            'Длина' => 'Длина',
            'Высота котла',
            'Высота' => 'Высота',
            'Диаметр дымохода' => 'Диаметр дымохода',
            'Максимальная рабочая температура в котле' => 'Максимальная температура в контуре отопления',
            default => $name,
        };
    }

    private function normalizeProductName(string $name): string
    {
        $name = mb_strtoupper($this->cleanText($name));
        $name = str_replace(['ТВЕРДОТОПЛИВНЫЙ', 'ПЕЛЛЕТНЫЙ', 'КОТЕЛ', 'КОТЁЛ'], '', $name);
        $name = preg_replace('/\bTIS\s+NEW\s+/u', 'TIS ', $name) ?? $name;
        $name = preg_replace('/[^A-ZА-ЯЁ0-9]+/u', ' ', $name) ?? $name;

        return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    }

    private function normalizeSupplierArticle(string $article): string
    {
        $article = str_replace(['–', '—', '−'], '-', $article);
        $article = preg_replace('/\s+/u', '', $article) ?? $article;

        return mb_strtoupper(trim($article));
    }

    private function articleFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $base = basename($path, '.html');

        return $base ?: Str::slug($url);
    }

    private function powerFromName(string $name): ?string
    {
        return preg_match('/\b([0-9]+)\s*$/u', $name, $match) ? $match[1] . ' кВт' : null;
    }

    private function parsePrice(?string $raw): ?float
    {
        if ($raw === null) {
            return null;
        }

        $text = $this->cleanText($raw);
        $text = preg_replace('/[^\d,.]/u', '', $text) ?? '';

        if ($text === '') {
            return null;
        }

        return round((float) str_replace(',', '.', $text), 2);
    }

    private function parseNumber(?string $value): ?float
    {
        if (! $value || ! preg_match('/([0-9]+(?:[,.][0-9]+)?)/u', $value, $match)) {
            return null;
        }

        return (float) str_replace(',', '.', $match[1]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'tis-boiler';
        $slug = $base;
        $i = 2;

        while (DB::table('products')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    private function nextKotlovSku(): string
    {
        $max = DB::table('products')
            ->where('sku', 'like', 'KOTLOV-%')
            ->pluck('sku')
            ->map(fn($sku) => preg_match('/^KOTLOV-(\d+)$/', (string) $sku, $match) ? (int) $match[1] : 0)
            ->max() ?? 0;

        $next = max(0, (int) $max) + 1;

        do {
            $sku = sprintf('KOTLOV-%06d', $next++);
        } while (DB::table('products')->where('sku', $sku)->exists());

        return $sku;
    }

    private function absoluteUrl(string $url): string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return rtrim(self::BASE_URL, '/') . '/' . ltrim($url, '/');
    }

    private function fetch(string $url): string
    {
        $url = str_replace(' ', '%20', $url);

        // The server's system CA bundle is missing belkomin.com's current
        // intermediate ("GlobalSign GCC R6 AlphaSSL CA 2025", issued Feb
        // 2026 — confirmed via `debug:ssl-check`: the cert itself is valid,
        // not expired, correct domain — it's genuinely "unable to get local
        // issuer certificate", not a WAF/anti-bot cert swap). Pin a fresh
        // official CA bundle (curl.se, Mozilla-sourced) instead of touching
        // system-wide trust or disabling verification.
        $sslOptions = [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ];
        $caBundle = resource_path('certs/cacert.pem');
        if (file_exists($caBundle)) {
            $sslOptions['cafile'] = $caBundle;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Mozilla/5.0 (compatible; KotlovBot/1.0)\r\nAccept-Language: ru,en;q=0.8\r\n",
                'timeout' => 30,
            ],
            'ssl' => $sslOptions,
        ]);

        $body = file_get_contents($url, false, $context);
        if ($body === false) {
            throw new \RuntimeException('Could not fetch ' . $url);
        }

        return $body;
    }

    private function match(string $pattern, string $subject): ?string
    {
        return preg_match($pattern, $subject, $match) ? html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;
    }

    private function rawMatch(string $pattern, string $subject): ?string
    {
        return preg_match($pattern, $subject, $match) ? $match[1] : null;
    }

    private function cleanText(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function cleanDescription(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace("/\n{3,}/u", "\n\n", $value) ?? $value;
        return trim($value);
    }

    private function cleanDescriptionHtml(string $value): ?string
    {
        $value = preg_replace('/<(script|style|svg|form|button|iframe|table)\b[\s\S]*?<\/\1>/iu', '', $value) ?? $value;
        $value = preg_replace('/<span class="h2">\s*Описание\s*<\/span>/iu', '', $value) ?? $value;
        $value = preg_replace('/<\/?div\b[^>]*>/iu', '', $value) ?? $value;
        $value = preg_replace('/<a\b[^>]*>([\s\S]*?)<\/a>/iu', '$1', $value) ?? $value;
        $value = strip_tags($value, '<p><ul><ol><li><strong><b><em><i><br>');
        $value = preg_replace('/<([a-z0-9]+)\b[^>]*>/iu', '<$1>', $value) ?? $value;
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace("/[ \t]+\n/u", "\n", $value) ?? $value;
        $value = preg_replace("/\n{3,}/u", "\n\n", $value) ?? $value;
        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function descriptionToHtml(string $description): ?string
    {
        if ($description === '') {
            return null;
        }

        $paragraphs = array_filter(array_map('trim', preg_split("/\n{2,}/u", $description) ?: []));
        return implode("\n", array_map(
            fn(string $paragraph) => '<p>' . nl2br(e($paragraph), false) . '</p>',
            $paragraphs
        ));
    }
}

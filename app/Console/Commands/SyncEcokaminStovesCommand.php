<?php

namespace App\Console\Commands;

use App\Services\AiContentEnricher;
use App\Services\Pricing\CurrencyPriceConverter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncEcokaminStovesCommand extends Command
{
    protected $signature = 'supplier:sync-ecokamin-stoves
        {--apply : Write changes to the database}
        {--dry-run : Preview without writing changes to the database}
        {--limit= : Limit number of products for testing}
        {--no-images : Do not download product images}
        {--enrich : Generate AI descriptions for products that have none (DeepSeek)}
        {--sleep=150 : Delay between product requests in milliseconds}';

    protected $description = 'Scrape ecokamin.ru pechi-kaminy (кроме Invicta) and sync prices, cards, photos and attributes.';

    private const SUPPLIER_CODE    = 'ecokamin';
    private const SYNC_KEY        = 'ecokamin_stoves';
    private const CATEGORY_ID     = 61;    // Печи → Печи-камины
    private const BRAND_ID        = 231;   // ЭкоКамин
    private const SOURCE_URL      = 'https://ecokamin.ru/catalog/pechi_kaminy/';
    private const EXTRA_SECTIONS  = [
        'https://ecokamin.ru/catalog/kaminy/',
    ];
    private const BASE_URL        = 'https://ecokamin.ru/';
    private const MAX_PAGES       = 15;

    private const SERVICE_INFO = [
        'Производитель'  => 'ЭкоКамин, Россия',
        'Импортер в РБ'  => 'ООО СанБизнесГруп',
        'Сервисный центр'=> 'ООО СанБизнесГруп',
        'Гарантия'       => '1 год',
    ];

    private string $supplierCurrency = 'RUB';
    private float  $supplierRate     = 1.0;

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

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: database will be updated.</>'
            : '<fg=yellow;options=bold>DRY RUN: database will not be changed.</>');

        try {
            $this->loadSupplierCurrency();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->line(sprintf('Supplier currency: %s, rate to BYN: %s', $this->supplierCurrency, $this->supplierRate));

        if ($apply && $this->supplierCurrency !== CurrencyPriceConverter::BASE_CURRENCY && abs($this->supplierRate - 1.0) < 0.0001) {
            $this->error(sprintf(
                'У поставщика валюта %s, но курс к BYN = 1 (заглушка). Задайте реальный курс в админке /admin/suppliers и повторите.',
                $this->supplierCurrency
            ));
            return self::FAILURE;
        }

        try {
            [$items, $skippedInvicta] = $this->scrapeCatalog();
        } catch (\Throwable $e) {
            $this->error('Catalog scrape failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info(sprintf('Found stoves: %d (skipped Invicta: %d)', count($items), count($skippedInvicta)));

        if ($limit !== null && $limit > 0) {
            $items = array_slice($items, 0, $limit);
        }

        if (! $apply) {
            return $this->dryRun($items, $skippedInvicta);
        }

        $now        = now();
        $supplierId = $this->ensureSupplier($now);
        $syncId     = $this->ensureSupplierSync($now);
        $this->ensureBrand($now);
        $this->ensureCategory($now);

        $stats = [
            'created'          => 0,
            'updated'          => 0,
            'attributes'       => 0,
            'images'           => 0,
            'skipped_invicta'  => count($skippedInvicta),
            'errors'           => 0,
        ];

        foreach ($items as $i => $item) {
            $this->line(sprintf('[%d/%d] %s', $i + 1, count($items), $item['article']));

            try {
                $detail           = $this->scrapeProduct($item['url']);
                $merged           = array_merge($item, $detail);
                $merged['article']    = $detail['article'] ?: $item['article'];
                $merged['attributes'] = $detail['attributes'] ?? [];
                $merged['price_byn']     = CurrencyPriceConverter::convertToByn($merged['price'], $this->supplierCurrency, $this->supplierRate);
                $merged['price_old_byn'] = CurrencyPriceConverter::convertToByn($merged['price_old'], $this->supplierCurrency, $this->supplierRate);

                $product = $this->findProduct($merged, $supplierId);
                $isNew   = ! $product;
                $images  = [];

                // Enrich with AI only if product has no description yet
                $productHasContent = $product
                    && is_string($product->content)
                    && trim($product->content) !== '';
                $productHasShortDesc = $product
                    && is_string($product->short_description)
                    && trim($product->short_description) !== '';

                if ($enrichContent && ! $productHasContent) {
                    $aiText = $enricher->enrich($item['name'], 'ЭкоКамин', $merged['content'] ?? null, $merged['attributes'] ?? []);
                    if ($aiText) {
                        $merged['content'] = $aiText;
                        $this->line('  <fg=cyan>AI content generated.</>');
                    }
                }

                if ($enrichContent && ! $productHasShortDesc) {
                    $aiShort = $enricher->shortDescription($item['name'], 'ЭкоКамин', $merged['attributes'] ?? []);
                    if ($aiShort) {
                        $merged['short_description'] = $aiShort;
                        $this->line('  <fg=cyan>AI short description generated.</>');
                    }
                }

                // Download images only for products that have none yet
                $productHasImages = $product
                    && ! empty(json_decode($product->images ?? '[]', true));

                if ($downloadImages && ! $productHasImages) {
                    $images = $this->downloadImages($merged);
                    $stats['images'] += count($images);
                }

                $productId  = $this->upsertProduct($merged, $product, $images, $now);
                $productSku = (string) DB::table('products')->where('id', $productId)->value('sku');

                $this->upsertSupplierProduct($merged, $productId, $productSku, $supplierId, $syncId, $now);

                // Write attributes only once — skip if this product already has any
                $alreadyHasAttrs = DB::table('product_attribute_values')->where('product_id', $productId)->exists();
                if (! $alreadyHasAttrs) {
                    $stats['attributes'] += $this->syncAttributes($productId, $merged, $now);
                }

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

    private function dryRun(array $items, array $skippedInvicta): int
    {
        $previewSupplierId = (int) (DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id') ?? 0);
        $preview = [];

        foreach ($items as $item) {
            $priceByn = CurrencyPriceConverter::convertToByn($item['price'], $this->supplierCurrency, $this->supplierRate);

            try {
                $detail  = $this->scrapeProduct($item['url']);
                $article = $detail['article'] ?: $item['article'];
                $preview[] = [
                    'article'   => $article,
                    'brand'     => $item['brand'],
                    'price'     => $item['price'] !== null
                        ? number_format($item['price'], 2, '.', '') . ' ' . $this->supplierCurrency
                        : 'no price',
                    'price_byn' => $priceByn !== null ? number_format($priceByn, 2, '.', '') : '—',
                    'action'    => $this->previewPriceAction(['article' => $article] + $item, $priceByn, $previewSupplierId),
                    'name'      => mb_substr($item['name'], 0, 44),
                    'url'       => $this->shortUrl($item['url']),
                ];
            } catch (\Throwable $e) {
                $preview[] = [
                    'article'   => $item['article'],
                    'brand'     => $item['brand'],
                    'price'     => 'error',
                    'price_byn' => '—',
                    'action'    => 'error',
                    'name'      => mb_substr($item['name'], 0, 44),
                    'url'       => $this->shortUrl($item['url']),
                ];
            }

            usleep(max(0, (int) $this->option('sleep')) * 1000);
        }

        foreach ($skippedInvicta as $item) {
            $preview[] = [
                'article'   => $item['article'],
                'brand'     => 'Invicta',
                'price'     => $item['price'] !== null
                    ? number_format($item['price'], 2, '.', '') . ' ' . $this->supplierCurrency
                    : 'no price',
                'price_byn' => '—',
                'action'    => 'skipped Invicta',
                'name'      => mb_substr($item['name'], 0, 44),
                'url'       => $this->shortUrl($item['url']),
            ];
        }

        $this->table(['article', 'brand', 'price', 'price_byn', 'action', 'name', 'url'], $preview);
        $this->line('Run with --apply to update products, attributes and images.');

        return self::SUCCESS;
    }

    // ── Scraping ───────────────────────────────────────────────────────────────

    private function scrapeCatalog(): array
    {
        $items   = [];
        $skipped = [];

        // Discover non-Invicta subfolders of pechi_kaminy + extra sections
        $sections = $this->discoverSections();

        foreach ($sections as $sectionUrl) {
            $this->line('  Раздел: ' . $sectionUrl);
            $this->scrapeSection($sectionUrl, $items, $skipped);
        }

        return [array_values($items), array_values($skipped)];
    }

    private function discoverSections(): array
    {
        $sections = [];

        try {
            $html = $this->fetch(self::SOURCE_URL);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Cannot fetch main catalog: ' . $e->getMessage());
        }

        // Find subfolder links: /catalog/pechi_kaminy/SUBFOLDER/ (slug-only, no query string, no numeric IDs)
        preg_match_all('~href="(/catalog/pechi_kaminy/([a-z0-9_-]+)/)"~ui', $html, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $subfolder = $m[2];
            if ($subfolder === '' || preg_match('/^\d+$/', $subfolder)) {
                continue; // skip empty or product-id links
            }

            $subUrl = $this->absoluteUrl($m[1]);

            if ($this->isInvictaUrl($subUrl) || isset($sections[$subUrl])) {
                continue;
            }

            $sections[$subUrl] = $subUrl;
        }

        // Add extra sections
        foreach (self::EXTRA_SECTIONS as $extra) {
            $sections[$extra] = $extra;
        }

        return array_values($sections);
    }

    private function scrapeSection(string $sectionUrl, array &$items, array &$skipped): void
    {
        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $url = $page === 1 ? $sectionUrl : $sectionUrl . '?PAGEN_7=' . $page;

            try {
                $html = $this->fetch($url);
            } catch (\Throwable $e) {
                if ($page > 1) {
                    break;
                }

                $this->warn('  Cannot fetch section: ' . $sectionUrl . ' — ' . $e->getMessage());
                return;
            }

            $found = 0;

            foreach ($this->productNodes($html) as $nodeHtml) {
                $item = $this->parseListItem($nodeHtml);
                if (! $item) {
                    continue;
                }

                $found++;

                if (isset($items[$item['bitrix_id']]) || isset($skipped[$item['bitrix_id']])) {
                    continue;
                }

                if ($this->isInvicta($item)) {
                    $skipped[$item['bitrix_id']] = $item;
                    continue;
                }

                $items[$item['bitrix_id']] = $item;
            }

            if ($found === 0) {
                break;
            }
        }
    }

    private function productNodes(string $html): array
    {
        preg_match_all('/<div class="item_block[\s\S]*?(?=<div class="item_block|<div class="module-pagination|<div class="bottom_nav|<footer)/u', $html, $matches);

        return $matches[0] ?? [];
    }

    private function parseListItem(string $html): ?array
    {
        if (! preg_match('/<a href="(\/catalog\/[^"]+\/(\d+)\/)" class="dark_link"><span>([\s\S]+?)<\/span>/u', $html, $link)) {
            return null;
        }

        $url      = $this->absoluteUrl($link[1]);
        $bitrixId = $link[2];
        $name     = $this->cleanText($link[3]);

        if ($name === '') {
            return null;
        }

        $article = $this->match('/article_block"[^>]*data-value="([^"]*)"/u', $html) ?: $bitrixId;
        $image   = $this->match('/<img[^>]+src="(\/upload\/[^"]+)"/u', $html);

        $current = null;
        $old     = null;

        if (preg_match('/<div class="price"\s+data-currency="[A-Z]+"\s+data-value="([\d.]+)"/u', $html, $priceMatch)) {
            $current = round((float) $priceMatch[1], 2);
        }

        if (preg_match('/<div class="price discount"\s+data-currency="[A-Z]+"\s+data-value="([\d.]+)"/u', $html, $oldMatch)) {
            $oldValue = round((float) $oldMatch[1], 2);
            if ($current !== null && $oldValue > $current) {
                $old = $oldValue;
            }
        }

        return [
            'bitrix_id'          => $bitrixId,
            'article'            => $this->normalizeSupplierArticle($article),
            'article_normalized' => $this->normalizeSupplierArticle($article),
            'name'               => $name,
            'brand'              => $this->detectBrand($name, $url),
            'url'                => $url,
            'price'              => $current,
            'price_old'          => $old,
            'listing_image'      => $image ? $this->absoluteUrl($image) : null,
        ];
    }

    private function scrapeProduct(string $url): array
    {
        $html = $this->fetch($url);

        return [
            'h1'               => $this->cleanText($this->match('/<h1[^>]*>([\s\S]*?)<\/h1>/u', $html) ?? '') ?: null,
            'article'          => $this->normalizeSupplierArticle($this->match('/class="article__value">([^<]+)</u', $html) ?? ''),
            'content'          => $this->extractDescriptionHtml($html),
            'meta_title'       => $this->cleanText($this->match('/<title>([\s\S]*?)<\/title>/u', $html) ?? ''),
            'meta_description' => $this->cleanText($this->match('/<meta name="description" content="([^"]*)"/u', $html) ?? ''),
            'images_remote'    => $this->extractImages($html),
            'attributes'       => $this->parseDetailAttributes($html),
            'in_stock'         => $this->parseStock($html),
        ];
    }

    private function parseDetailAttributes(string $html): array
    {
        $attrs = [];
        preg_match_all('/<span itemprop="name">([\s\S]*?)<\/span>[\s\S]*?<span itemprop="value">([\s\S]*?)<\/span>/u', $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $name  = $this->normalizeAttributeName($this->cleanText($match[1]));
            $value = $this->cleanText($match[2]);

            if ($name !== '' && $value !== '' && mb_strlen($name) <= 120) {
                $attrs[$name] = $value;
            }
        }

        return $attrs;
    }

    private function extractImages(string $html): array
    {
        $images = [];

        // 1. Full-size gallery links: <a href="/upload/iblock/..."> (gif excluded — animated demos)
        preg_match_all('/href="(\/upload\/iblock\/[^"]+\.(?:jpg|jpeg|png|webp))"/iu', $html, $m1);
        foreach ($m1[1] ?? [] as $path) {
            $images[] = $this->absoluteUrl($path);
        }

        // 2. Fallback: <img src="/upload/iblock/..."> (skip resize_cache thumbnails)
        if (empty($images)) {
            preg_match_all('/<img[^>]+src="(\/upload\/iblock\/[^"]+\.(?:jpg|jpeg|png|webp))"/iu', $html, $m2);
            foreach ($m2[1] ?? [] as $path) {
                if (str_contains($path, '/resize_cache/')) continue;
                $images[] = $this->absoluteUrl($path);
            }
        }

        return array_slice(array_values(array_unique($images)), 0, 8);
    }

    private function extractDescriptionHtml(string $html): ?string
    {
        $block = $this->rawMatch('/<div class="detail_text">([\s\S]*?)<\/div>/u', $html);

        if (! $block) {
            return null;
        }

        return $this->cleanDescriptionHtml($block);
    }

    private function parseStock(string $html): bool
    {
        $value = $this->match('/store_view\'>([^<]+)</u', $html)
            ?: $this->match('/store_view">([^<]+)</u', $html);

        if ($value === null) {
            return true;
        }

        return ! preg_match('/^нет/ui', trim($value));
    }

    private function isInvicta(array $item): bool
    {
        return $this->isInvictaUrl($item['url'])
            || preg_match('/invicta|инвикта/ui', $item['name']) === 1;
    }

    private function isInvictaUrl(string $url): bool
    {
        return str_contains(mb_strtolower($url), 'invicta');
    }

    private function detectBrand(string $name, string $url): string
    {
        if (str_contains(mb_strtolower($url), 'invicta') || preg_match('/invicta|инвикта/ui', $name)) {
            return 'Invicta';
        }

        return 'ЭкоКамин';
    }

    // ── Persistence ────────────────────────────────────────────────────────────

    private function upsertProduct(array $item, ?object $product, array $images, $now): int
    {
        if (empty($images) && $product?->images) {
            $images = is_string($product->images) ? (json_decode($product->images, true) ?: []) : (array) $product->images;
        }

        $attrs = $item['attributes'] ?? [];

        $existingContent = $product
            ? (is_string($product->content) ? trim($product->content) : null)
            : null;

        $existingShortDesc = $product
            ? (is_string($product->short_description) ? trim($product->short_description) : null)
            : null;

        $existingVideoUrl = $product
            ? (is_string($product->video_url) ? trim($product->video_url) : null)
            : null;

        $existingSpecs = $product
            ? (is_string($product->specs) ? json_decode($product->specs, true) : null)
            : null;
        $hasSpecs = ! empty($existingSpecs);

        $payload = [
            'category_id'       => self::CATEGORY_ID,
            'brand_id'          => self::BRAND_ID,
            'supplier_id'       => null,
            'name'              => $item['name'],
            'h1'                => $item['h1'] ?: $item['name'],
            'price'             => $item['price_byn'] ?? 0,
            'price_old'         => $item['price_old_byn'] ?? null,
            'currency'          => 'BYN',
            // Preserve existing description; write only if product has none yet
            'content'           => ($existingContent !== null && $existingContent !== '')
                                        ? $existingContent
                                        : ($item['content'] ?: null),
            'short_description' => ($existingShortDesc !== null && $existingShortDesc !== '')
                                        ? $existingShortDesc
                                        : ($item['short_description'] ?? null),
            'images'            => json_encode($images, JSON_UNESCAPED_UNICODE),
            // Preserve existing specs; write only if product has none yet
            'specs'             => $hasSpecs
                                        ? json_encode($existingSpecs, JSON_UNESCAPED_UNICODE)
                                        : json_encode($attrs, JSON_UNESCAPED_UNICODE),
            'service_info'      => json_encode(self::SERVICE_INFO, JSON_UNESCAPED_UNICODE),
            'video_url'         => ($existingVideoUrl !== null && $existingVideoUrl !== '') ? $existingVideoUrl : null,
            'weight'            => $this->parseNumber($attrs['Вес'] ?? $attrs['Масса'] ?? null),
            'unit'              => 'шт',
            'warranty'          => $attrs['Гарантия'] ?? null,
            'is_active'         => true,
            'is_archived'       => false,
            'in_stock'          => ($item['in_stock'] ?? true) && $item['price'] !== null,
            'stock_qty'         => null,
            'is_featured'       => false,
            'is_new'            => false,
            'is_sale'           => $item['price_old_byn'] !== null,
            'sort_order'        => 0,
            'meta_title'        => $item['meta_title'] ?: $item['name'] . ' купить в %city%',
            'meta_keywords'     => 'печь-камин, ЭкоКамин, ' . $item['name'],
            'meta_description'  => $item['meta_description'] ?: $item['name'],
            'rating'            => 0,
            'reviews_count'     => 0,
            'views_count'       => 0,
            'updated_at'        => $now,
        ];

        if ($product) {
            DB::table('products')->where('id', $product->id)->update($payload);
            return (int) $product->id;
        }

        $payload['sku']        = $this->nextKotlovSku();
        $payload['slug']       = $this->uniqueSlug($item['name']);
        $payload['created_at'] = $now;

        return (int) DB::table('products')->insertGetId($payload);
    }

    private function upsertSupplierProduct(array $item, int $productId, string $productSku, int $supplierId, ?int $syncId, $now): void
    {
        DB::table('supplier_products')->updateOrInsert(
            [
                'supplier_id'      => $supplierId,
                'supplier_article' => $item['article'],
            ],
            [
                'supplier_article_normalized' => $this->normalizeSupplierArticle($item['article']),
                'supplier_sync_id'  => $syncId,
                'product_id'        => $productId,
                'product_sku'       => $productSku,
                'supplier_name'     => $item['name'],
                'source_url'        => $item['url'],
                'source_wp_id'      => $item['bitrix_id'] ?? null,
                'price'             => $item['price'],
                'currency'          => $this->supplierCurrency,
                'currency_rate'     => $this->supplierRate,
                'price_byn'         => $item['price_byn'] ?? null,
                'in_stock'          => ($item['in_stock'] ?? true) && $item['price'] !== null,
                'match_status'      => 'matched',
                'match_confidence'  => 'auto_name',
                'raw'               => json_encode([
                    'bitrix_id'      => $item['bitrix_id'] ?? null,
                    'brand'          => $item['brand'] ?? null,
                    'attributes'     => $item['attributes'] ?? [],
                    'images_remote'  => $item['images_remote'] ?? [],
                ], JSON_UNESCAPED_UNICODE),
                'last_synced_at'   => $now,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]
        );
    }

    private function syncAttributes(int $productId, array $item, $now): int
    {
        $count = 0;

        foreach ($item['attributes'] ?? [] as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $name        = $this->normalizeAttributeName((string) $name);
            $attributeId = $this->ensureAttribute($name, $now);

            DB::table('product_attribute_values')->updateOrInsert(
                ['product_id' => $productId, 'attribute_id' => $attributeId],
                [
                    'option_id'  => null,
                    'is_checked' => null,
                    'value'      => (string) $value,
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
        $existing = DB::table('attributes')
            ->where('category_id', self::CATEGORY_ID)
            ->where('name', $name)
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        return (int) DB::table('attributes')->insertGetId([
            'category_id'   => self::CATEGORY_ID,
            'group_id'      => 0,
            'sort_order'    => 500,
            'type'          => 'value',
            'name'          => $name,
            'suffix'        => null,
            'in_filter'     => false,
            'in_sort'       => false,
            'in_product'    => true,
            'in_brief'      => in_array($name, ['Мощность', 'Площадь обогрева', 'Вид топлива', 'Материал корпуса'], true),
            'is_comparable' => true,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
    }

    private function findProduct(array $item, int $supplierId): ?object
    {
        $supplierProduct = DB::table('supplier_products')
            ->where('supplier_id', $supplierId)
            ->where(function ($query) use ($item) {
                $query->where('supplier_article', $item['article'])
                    ->orWhere('supplier_article_normalized', $this->normalizeSupplierArticle($item['article']));
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
        $candidates     = DB::table('products')
            ->where('category_id', self::CATEGORY_ID)
            ->get(['id', 'sku', 'name', 'images', 'price', 'content', 'short_description', 'specs']);

        foreach ($candidates as $candidate) {
            if ($this->normalizeProductName($candidate->name) === $normalizedName) {
                return $candidate;
            }
        }

        return null;
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

    private function downloadImages(array $item): array
    {
        $detail = array_values(array_filter($item['images_remote'] ?? []));
        $urls   = $detail !== []
            ? array_values(array_unique($detail))
            : array_values(array_filter([$item['listing_image'] ?? null]));

        $paths = [];
        $dir   = public_path('img/products/ecokamin');
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
                $target   = $dir . DIRECTORY_SEPARATOR . $filename;

                if (! file_exists($target)) {
                    file_put_contents($target, $this->fetch($url));
                }

                $paths[] = 'img/products/ecokamin/' . $filename;
            } catch (\Throwable $e) {
                $this->warn('  image skipped: ' . $url);
            }
        }

        return array_values(array_unique($paths));
    }

    // ── Supplier / sync registration ───────────────────────────────────────────

    private function ensureSupplier($now): int
    {
        $existing = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->first();

        if ($existing) {
            DB::table('suppliers')->where('id', $existing->id)->update([
                'name'       => 'EcoKamin',
                'contact'    => self::SOURCE_URL,
                'is_active'  => true,
                'updated_at' => $now,
            ]);

            return (int) $existing->id;
        }

        return (int) DB::table('suppliers')->insertGetId([
            'code'          => self::SUPPLIER_CODE,
            'name'          => 'EcoKamin',
            'currency'      => 'RUB',
            'currency_rate' => 1,
            'contact'       => self::SOURCE_URL,
            'notes'         => 'Печи-камины ecokamin.ru (кроме Invicta). Перед боевым запуском задать курс RUB → BYN.',
            'is_active'     => true,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
    }

    private function ensureSupplierSync($now): ?int
    {
        DB::table('supplier_syncs')->updateOrInsert(
            ['key' => self::SYNC_KEY],
            [
                'name'            => 'EcoKamin',
                'code'            => self::SUPPLIER_CODE,
                'title'           => 'EcoKamin: печи-камины',
                'description'     => 'Обновляет цены и карточки печей-каминов с EcoKamin, кроме Invicta.',
                'command'         => 'supplier:sync-ecokamin-stoves',
                'source_url'      => self::SOURCE_URL,
                'image_disk_path' => 'img/products/ecokamin',
                'is_active'       => true,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]
        );

        return DB::table('supplier_syncs')->where('key', self::SYNC_KEY)->value('id');
    }

    private function ensureBrand($now): void
    {
        DB::table('brands')->updateOrInsert(
            ['id' => self::BRAND_ID],
            [
                'name'       => 'ЭкоКамин',
                'slug'       => 'ecokamin',
                'h1'         => 'ЭкоКамин',
                'country'    => 'Россия',
                'is_active'  => true,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    private function ensureCategory($now): void
    {
        DB::table('categories')->where('id', self::CATEGORY_ID)->update([
            'is_active'  => true,
            'updated_at' => $now,
        ]);
    }

    private function loadSupplierCurrency(): void
    {
        $supplier = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->first();

        $this->supplierCurrency = CurrencyPriceConverter::normalizeCurrency($supplier->currency ?? 'RUB');
        $this->supplierRate     = CurrencyPriceConverter::rateFor($this->supplierCurrency, $supplier->currency_rate ?? 1);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function normalizeAttributeName(string $name): string
    {
        $name = trim(str_replace("\u{A0}", ' ', $name));
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return match ($name) {
            'Мощность, кВт',
            'Мощность номинальная'  => 'Мощность',
            'Отапливаемая площадь',
            'Обогреваемая площадь'  => 'Площадь обогрева',
            'Масса', 'Вес, кг'      => 'Вес',
            'Диаметр дымохода, мм'  => 'Диаметр дымохода',
            default                  => $name,
        };
    }

    private function normalizeProductName(string $name): string
    {
        $name = mb_strtoupper($this->cleanText($name));
        $name = str_replace(['ПЕЧЬ-КАМИН', 'КАМИН-ПЕЧЬ', 'КАМИННАЯ', 'ТОПКА', 'ЧУГУННАЯ', 'ПЕЧЬ', 'КАМИН', 'ЭКОКАМИН', 'ECOKAMIN'], '', $name);
        $name = preg_replace('/[^A-ZА-ЯЁ0-9]+/u', ' ', $name) ?? $name;

        return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    }

    private function normalizeSupplierArticle(string $article): string
    {
        $article = html_entity_decode($article, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $article = str_replace(['–', '—', '−'], '-', $article);
        $article = preg_replace('/\s+/u', '', $article) ?? $article;

        return mb_strtoupper(trim($article));
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
        $base = Str::slug($name) ?: 'ecokamin-stove';
        $slug = $base;
        $i    = 2;

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

    private function shortUrl(string $url): string
    {
        return Str::limit(str_replace(self::BASE_URL, '/', $url), 44);
    }

    private function fetch(string $url): string
    {
        $url = str_replace(' ', '%20', $url);

        $context = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'header'  => "User-Agent: Mozilla/5.0 (compatible; KotlovBot/1.0)\r\nAccept-Language: ru,en;q=0.8\r\n",
                'timeout' => 30,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
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

    private function cleanDescriptionHtml(string $value): ?string
    {
        $value = preg_replace('/<(script|style|svg|form|button|iframe|table)\b[\s\S]*?<\/\1>/iu', '', $value) ?? $value;
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
}

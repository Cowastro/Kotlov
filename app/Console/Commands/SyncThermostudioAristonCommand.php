<?php

namespace App\Console\Commands;

use App\Services\AiContentEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncThermostudioAristonCommand extends Command
{
    protected $signature = 'supplier:sync-thermostudio-ariston
        {--apply : Write changes to the database}
        {--dry-run : Preview without writing changes}
        {--limit= : Limit number of products for testing}
        {--no-images : Skip product images}
        {--enrich : Generate unique SEO descriptions via AI}
        {--sleep=500 : Delay between requests in milliseconds}';

    protected $description = 'Scrape Ariston gas boilers from teplo.by and sync prices, cards, service info, documents and attributes.';

    protected const SUPPLIER_CODE = 'thermostudio';
    protected const SYNC_KEY = 'thermostudio_ariston_gas_boilers';
    protected const SOURCE_URL = 'https://teplo.by/catalog/gazovye-kotly/?jsf=jet-woo-products-grid&tax=product_cat:553';
    protected const SOURCE_SITE_NAME = 'teplo.by';
    protected const CATALOG_PAGE_QUERY = '?jsf=jet-woo-products-grid&tax=product_cat:553';
    protected const CATALOG_BASE_URL = 'https://teplo.by/catalog/gazovye-kotly/';
    protected const BASE_URL = 'https://teplo.by';
    protected const CATEGORY_SLUG = 'gazovye';
    protected const BRAND_NAME = 'Ariston';
    protected const BRAND_SLUG = 'ariston';
    protected const BRAND_COUNTRY = 'Италия';
    protected const PRODUCT_URL_HINTS = ['ariston'];
    protected const IMAGE_DISK_PATH = 'img/products/thermostudio/ariston';
    protected const MAX_PAGES = 20;

    protected const CHARACTERISTIC_LABELS = [
        'Тип газового котла',
        'Тепловая мощность, кВт',
        'Площадь обогрева, м²',
        'Количество контуров',
        'Камера сгорания',
        'Установка',
        'КПД',
        'Максимальный расход природного газа, м³/ч',
        'Объём расширительного бака, л.',
        'Диаметр дымохода, мм.',
        'Подключение контура отопления, «',
        'Подключение контура ГВС, “',
        'Подключение газа, “',
        'Габаритные размеры ВхШхГ, мм.',
        'Вес, кг.',
    ];

    protected const SERVICE_LABELS = [
        'Гарантия',
        'Срок службы',
        'Страна изготовления',
        'Завод изготовитель',
        'Сервисный центр',
        'Импортер',
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $downloadImages = ! (bool) $this->option('no-images');
        $enrichContent = (bool) $this->option('enrich');
        $sleepMs = max(0, (int) ($this->option('sleep') ?? 500));

        $enricher = new AiContentEnricher();
        if ($enrichContent && ! $enricher->isAvailable()) {
            $this->warn('--enrich: no AI provider configured, content enrichment skipped.');
            $enrichContent = false;
        }

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: database will be updated.</>'
            : '<fg=yellow;options=bold>DRY RUN: database will not be changed.</>');

        try {
            $items = $this->scrapeCatalog($sleepMs);
        } catch (\Throwable $e) {
            $this->error('Catalog scrape failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        if ($limit !== null && $limit > 0) {
            $items = array_slice($items, 0, $limit);
        }

        $this->info(sprintf('Found %d unique %s products on %s.', count($items), static::BRAND_NAME, static::SOURCE_SITE_NAME));

        if (! $apply) {
            return $this->dryRun($items, $sleepMs);
        }

        $now = now();
        $categoryId = $this->categoryId();
        $brandId = $this->ensureBrand($now);
        $supplierId = $this->ensureSupplier($now);
        $syncId = $this->ensureSupplierSync($now);

        $stats = [
            'created' => 0,
            'updated' => 0,
            'no_change' => 0,
            'seo' => 0,
            'documents' => 0,
            'promo_flags' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        foreach ($items as $i => $item) {
            $this->line(sprintf('[%d/%d] %s', $i + 1, count($items), $item['url']));

            try {
                $detail = $this->scrapeProduct($item['url']);
                if (($detail['price_byn'] ?? null) === null) {
                    $stats['skipped']++;
                    $this->warn('  skipped: no price on product page.');
                    continue;
                }

                $merged = array_merge($item, $detail);
                if (! $downloadImages) {
                    $merged['images'] = [];
                }

                $product = $this->findProduct($merged, $supplierId, $brandId);
                $isNew = ! $product;

                if ($enrichContent) {
                    $aiText = $enricher->enrich($merged['name'], static::BRAND_NAME, $merged['content'] ?? null, $merged['attributes'] ?? []);
                    if ($aiText) {
                        $merged['content'] = $aiText;
                        $stats['seo']++;
                        $this->line('  <fg=cyan>AI content generated.</>');
                    } elseif ($product && ! empty($product->content)) {
                        $merged['content'] = $product->content;
                    }
                }

                $productId = $this->upsertProduct($merged, $product, $brandId, $categoryId, $now);
                $productSku = (string) DB::table('products')->where('id', $productId)->value('sku');

                $this->upsertSupplierProduct($merged, $productId, $productSku, $supplierId, $syncId, $now);
                $this->syncAttributes($productId, $merged['attributes'] ?? [], $categoryId, $now);

                $stats['documents'] += count($merged['documents'] ?? []);
                $stats['promo_flags'] += count($merged['promo_flags'] ?? []);
                $stats[$isNew ? 'created' : (abs((float)($product->price ?? 0) - (float)$merged['price_byn']) > 0.01 ? 'updated' : 'no_change')]++;
                usleep($sleepMs * 1000);
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn('  failed: ' . $e->getMessage());
            }
        }

        $this->table(
            ['action', 'count'],
            array_map(fn ($k, $v) => [$k, $v], array_keys($stats), array_values($stats))
        );

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function dryRun(array $items, int $sleepMs): int
    {
        $supplierId = (int) (DB::table('suppliers')->where('code', static::SUPPLIER_CODE)->value('id') ?? 0);
        $brandId = (int) (DB::table('brands')->where('slug', static::BRAND_SLUG)->value('id') ?? 0);
        $rows = [];

        foreach ($items as $item) {
            try {
                $detail = $this->scrapeProduct($item['url']);
                $merged = array_merge($item, $detail);
                $product = $this->findProduct($merged, $supplierId, $brandId);
                $price = $merged['price_byn'] ?? null;
                $action = $price === null
                    ? 'skip_no_price'
                    : (! $product
                        ? 'create'
                        : (abs((float)($product->price ?? 0) - (float)$price) > 0.01
                            ? sprintf('update_price %.2f->%.2f', (float)$product->price, (float)$price)
                            : 'no_change'));

                $rows[] = [
                    $action,
                    $price !== null ? number_format((float)$price, 2) : '—',
                    count($merged['attributes'] ?? []),
                    count($merged['documents'] ?? []),
                    count($merged['promo_flags'] ?? []),
                    mb_substr($merged['name'] ?? $item['url'], 0, 46),
                ];
            } catch (\Throwable $e) {
                $rows[] = ['error', '—', '—', '—', '—', mb_substr($item['url'], 0, 46)];
            }

            usleep($sleepMs * 1000);
        }

        $this->table(['action', 'price_byn', 'attrs', 'docs', 'promo', 'name'], $rows);
        $this->line('Run with --apply to update the database.');

        return self::SUCCESS;
    }

    protected function scrapeCatalog(int $sleepMs): array
    {
        $items = [];

        for ($page = 1; $page <= static::MAX_PAGES; $page++) {
            $url = $page === 1
                ? static::SOURCE_URL
                : static::CATALOG_BASE_URL . 'page/' . $page . '/' . static::CATALOG_PAGE_QUERY;

            $html = $this->fetch($url);
            preg_match_all('/https:\/\/teplo\.by\/product\/[^"\s<]+/u', $html, $matches);

            foreach (array_unique($matches[0] ?? []) as $productUrl) {
                $productUrl = strtok($productUrl, '?#') ?: $productUrl;
                if (! $this->isBrandProductUrl($productUrl)) {
                    continue;
                }

                $items[$productUrl] = [
                    'url' => $productUrl,
                    'source_key' => trim(parse_url($productUrl, PHP_URL_PATH) ?: $productUrl, '/'),
                ];
            }

            $hasNext = str_contains($html, 'next page-numbers')
                || preg_match('/\/page\/' . ($page + 1) . '\//', $html);

            if (! $hasNext) {
                break;
            }

            usleep($sleepMs * 1000);
        }

        return array_values($items);
    }

    protected function scrapeProduct(string $url): array
    {
        $html = $this->fetch($url);
        $body = str_contains($html, '<body') ? substr($html, strpos($html, '<body')) : $html;

        $name = $this->cleanText($this->match('/<h1[^>]*>([\s\S]*?)<\/h1>/u', $html) ?? '');
        $price = $this->match('/property="product:price:amount"\s+content="([^"]+)"/u', $html);
        $retailerId = $this->match('/property="product:retailer_item_id"\s+content="([^"]+)"/u', $html);
        $availability = $this->match('/property="product:availability"\s+content="([^"]+)"/u', $html);

        $attributes = [];
        $serviceInfo = [];
        foreach ($this->extractDynamicBlocks($body) as $text) {
            foreach (static::CHARACTERISTIC_LABELS as $label) {
                if (str_starts_with($text, $label . ' ')) {
                    $attributes[$label] = trim(mb_substr($text, mb_strlen($label)));
                    continue 2;
                }
            }

            foreach (static::SERVICE_LABELS as $label) {
                if (str_starts_with($text, $label . ' ')) {
                    $serviceInfo[$label] = trim(mb_substr($text, mb_strlen($label)));
                    continue 2;
                }
            }

            if (preg_match('/^Гарантия:\s*(.+)$/u', $text, $m)) {
                $serviceInfo['Гарантия'] = trim($m[1]);
            }
        }

        $documents = $this->extractDocuments($body);
        $promoFlags = $this->extractPromoFlags($body, $url, $name);
        $images = $this->extractImages($html);
        $content = $this->extractDescription($body);
        $videoUrl = $this->extractVideoUrl($body);

        return [
            'name' => $name,
            'h1' => $name,
            'price_byn' => $price !== null && $price !== '' ? round((float) str_replace(',', '.', $price), 2) : null,
            'source_wp_id' => $retailerId ?: md5($url),
            'in_stock' => $availability ? $availability === 'instock' : true,
            'content' => $content,
            'attributes' => $attributes,
            'service_info' => $serviceInfo,
            'documents' => $documents,
            'promo_flags' => $promoFlags,
            'images' => $images,
            'video_url' => $videoUrl,
        ];
    }

    protected function isBrandProductUrl(string $productUrl): bool
    {
        foreach (static::PRODUCT_URL_HINTS as $hint) {
            if (str_contains($productUrl, $hint)) {
                return true;
            }
        }

        return false;
    }

    protected function extractDynamicBlocks(string $body): array
    {
        preg_match_all(
            '/<div class="elementor-element[\s\S]{0,2500}?jet-listing-dynamic-field__content[\s\S]{0,2500}?<\/div>\s*<\/div>/u',
            $body,
            $matches
        );

        $blocks = [];
        foreach ($matches[0] ?? [] as $block) {
            $text = $this->cleanText($block);
            if ($text !== '') {
                $blocks[] = $text;
            }
        }

        return array_values(array_unique($blocks));
    }

    protected function extractDocuments(string $body): array
    {
        preg_match_all('/<a[^>]+href="([^"]+\.pdf[^"]*)"[^>]*>([\s\S]*?)<\/a>/iu', $body, $matches, PREG_SET_ORDER);

        $documents = [];
        foreach ($matches as $match) {
            $url = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $label = $this->cleanText($match[2]) ?: basename(parse_url($url, PHP_URL_PATH) ?: 'Документ');

            $documents[$url] = [
                'label' => $label,
                'url' => $url,
            ];
        }

        return array_values($documents);
    }

    protected function extractPromoFlags(string $body, string $url, string $name): array
    {
        $plain = mb_strtolower($this->cleanText($body . ' ' . $url . ' ' . $name));
        $flags = [];

        if (preg_match('/дымоход\s+в\s+подарок/u', $plain) || str_contains($url, 'dymohod')) {
            $flags[] = ['key' => 'chimney_gift', 'label' => 'Дымоход в подарок'];
        }

        if (str_contains($plain, 'бесплатная доставка')) {
            $flags[] = ['key' => 'free_delivery', 'label' => 'Бесплатная доставка'];
        }

        return $flags;
    }

    protected function extractImages(string $html): array
    {
        $images = [];

        if ($og = $this->match('/property="og:image"\s+content="([^"]+)"/u', $html)) {
            $images[] = html_entity_decode($og, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        preg_match_all('/data-large_image="(https:\/\/teplo\.by\/wp-content\/uploads\/[^"]+\.(?:jpg|jpeg|png|webp))"/iu', $html, $largeMatches);
        foreach ($largeMatches[1] ?? [] as $image) {
            $images[] = html_entity_decode($image, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return array_values(array_unique($images));
    }

    protected function extractDescription(string $body): ?string
    {
        if (preg_match('/<div[^>]+class="[^"]*elementor-tab-title[^"]*"[^>]*>\s*Описание\s*<\/div>\s*<div[^>]+class="[^"]*elementor-tab-content[^"]*"[^>]*>([\s\S]*?)<\/div>/iu', $body, $m)) {
            $content = $this->sanitizeHtml($m[1]);
            return $content !== '' ? $content : null;
        }

        if (preg_match('/class="woocommerce-product-details__short-description"[^>]*>([\s\S]*?)<\/div>/iu', $body, $m)) {
            $content = $this->sanitizeHtml($m[1]);
            return $content !== '' ? $content : null;
        }

        return null;
    }

    protected function extractVideoUrl(string $body): ?string
    {
        if (preg_match('/https?:\/\/(?:www\.)?(?:youtube\.com|youtu\.be)\/[^"\s<]+/iu', $body, $m)) {
            return html_entity_decode($m[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return null;
    }

    protected function findProduct(array $item, int $supplierId, int $brandId): ?object
    {
        if ($supplierId > 0) {
            $sp = DB::table('supplier_products')
                ->where('supplier_id', $supplierId)
                ->where(function ($query) use ($item) {
                    $query->where('source_wp_id', $item['source_wp_id'])
                        ->orWhere('supplier_article', $item['source_wp_id'])
                        ->orWhere('source_url', $item['url']);
                })
                ->whereNotNull('product_id')
                ->first();

            if ($sp) {
                return DB::table('products')->where('id', $sp->product_id)->first();
            }
        }

        if ($brandId > 0 && ! empty($item['name'])) {
            $norm = $this->normalizeName($item['name']);
            $candidates = DB::table('products')
                ->where('brand_id', $brandId)
                ->where('is_archived', false)
                ->get(['id', 'sku', 'name', 'price', 'content', 'images']);

            foreach ($candidates as $candidate) {
                if ($this->normalizeName($candidate->name) === $norm) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    protected function upsertProduct(array $item, ?object $product, int $brandId, int $categoryId, $now): int
    {
        $images = $item['images'] ?? [];
        if ($product && empty($images)) {
            $images = is_string($product->images) ? (json_decode($product->images, true) ?: []) : [];
        }

        $payload = [
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'supplier_id' => null,
            'name' => $item['name'],
            'h1' => $item['h1'] ?: $item['name'],
            'price' => $item['price_byn'],
            'price_old' => null,
            'currency' => 'BYN',
            'content' => $item['content'] ?? null,
            'short_description' => null,
            'images' => json_encode($images, JSON_UNESCAPED_UNICODE),
            'specs' => json_encode($item['attributes'] ?? [], JSON_UNESCAPED_UNICODE),
            'service_info' => json_encode($item['service_info'] ?? [], JSON_UNESCAPED_UNICODE),
            'documents' => json_encode($item['documents'] ?? [], JSON_UNESCAPED_UNICODE),
            'promo_flags' => json_encode($item['promo_flags'] ?? [], JSON_UNESCAPED_UNICODE),
            'video_url' => $item['video_url'] ?? null,
            'unit' => 'шт',
            'warranty' => $item['service_info']['Гарантия'] ?? null,
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => (bool) ($item['in_stock'] ?? true),
            'stock_qty' => null,
            'is_featured' => false,
            'is_new' => false,
            'is_sale' => false,
            'sort_order' => 0,
            'meta_title' => $item['name'] . ' купить в %city%',
            'meta_keywords' => static::BRAND_NAME . ', газовый котел, ' . $item['name'],
            'meta_description' => $item['name'] . ' — купить по лучшей цене с доставкой по Беларуси.',
            'rating' => 0,
            'reviews_count' => 0,
            'views_count' => 0,
            'updated_at' => $now,
        ];

        if ($product) {
            if ($product->content && ! ($item['content'] ?? null)) {
                unset($payload['content']);
            }
            if (! empty(json_decode((string) $product->images, true) ?: []) && empty($images)) {
                unset($payload['images']);
            }

            DB::table('products')->where('id', $product->id)->update($payload);
            return (int) $product->id;
        }

        $payload['sku'] = $this->nextKotlovSku();
        $payload['slug'] = $this->uniqueSlug($item['name']);
        $payload['created_at'] = $now;

        return (int) DB::table('products')->insertGetId($payload);
    }

    protected function syncAttributes(int $productId, array $attributes, int $categoryId, $now): void
    {
        foreach ($attributes as $name => $value) {
            if (! $name || ! $value) {
                continue;
            }

            $attrId = $this->ensureAttribute((string) $name, $categoryId, $now);
            DB::table('product_attribute_values')->updateOrInsert(
                ['product_id' => $productId, 'attribute_id' => $attrId],
                [
                    'option_id' => null,
                    'is_checked' => null,
                    'value' => (string) $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    protected function ensureAttribute(string $name, int $categoryId, $now): int
    {
        $existing = DB::table('attributes')
            ->where('category_id', $categoryId)
            ->where('name', $name)
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        $briefName = mb_strtolower($name);
        $inBrief = str_contains($briefName, 'мощность')
            || str_contains($briefName, 'площадь')
            || str_contains($briefName, 'контур')
            || str_contains($briefName, 'камера')
            || str_contains($briefName, 'кпд')
            || str_contains($briefName, 'расход')
            || str_contains($briefName, 'дымоход');

        return (int) DB::table('attributes')->insertGetId([
            'category_id' => $categoryId,
            'group_id' => 0,
            'sort_order' => 500,
            'type' => 'value',
            'name' => $name,
            'suffix' => null,
            'in_filter' => false,
            'in_sort' => false,
            'in_product' => true,
            'in_brief' => $inBrief,
            'is_comparable' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected function upsertSupplierProduct(array $item, int $productId, string $productSku, int $supplierId, ?int $syncId, $now): void
    {
        DB::table('supplier_products')->updateOrInsert(
            ['supplier_id' => $supplierId, 'supplier_article' => $item['source_wp_id']],
            [
                'supplier_article_normalized' => $item['source_wp_id'],
                'supplier_sync_id' => $syncId,
                'product_id' => $productId,
                'product_sku' => $productSku,
                'supplier_name' => $item['name'],
                'source_url' => $item['url'],
                'source_wp_id' => $item['source_wp_id'],
                'price' => $item['price_byn'],
                'currency' => 'BYN',
                'currency_rate' => 1.0,
                'price_byn' => $item['price_byn'],
                'in_stock' => (bool) ($item['in_stock'] ?? true),
                'match_status' => 'matched',
                'match_confidence' => 'auto_source',
                'raw' => json_encode([
                    'documents' => $item['documents'] ?? [],
                    'promo_flags' => $item['promo_flags'] ?? [],
                    'service_info' => $item['service_info'] ?? [],
                ], JSON_UNESCAPED_UNICODE),
                'last_synced_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    protected function ensureBrand($now): int
    {
        $existing = DB::table('brands')->where('slug', static::BRAND_SLUG)->first();
        if ($existing) {
            return (int) $existing->id;
        }

        return (int) DB::table('brands')->insertGetId([
            'name' => static::BRAND_NAME,
            'slug' => static::BRAND_SLUG,
            'h1' => static::BRAND_NAME,
            'country' => static::BRAND_COUNTRY,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected function ensureSupplier($now): int
    {
        $existing = DB::table('suppliers')->where('code', static::SUPPLIER_CODE)->first();
        if ($existing) {
            DB::table('suppliers')->where('id', $existing->id)->update([
                'name' => 'Термостудия',
                'contact' => static::SOURCE_URL,
                'is_active' => true,
                'updated_at' => $now,
            ]);
            return (int) $existing->id;
        }

        return (int) DB::table('suppliers')->insertGetId([
            'code' => static::SUPPLIER_CODE,
            'name' => 'Термостудия',
            'currency' => 'BYN',
            'currency_rate' => 1,
            'contact' => static::SOURCE_URL,
            'notes' => 'Газовые котлы ' . static::BRAND_NAME . ' с teplo.by. Цены BYN.',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected function ensureSupplierSync($now): ?int
    {
        DB::table('supplier_syncs')->updateOrInsert(
            ['key' => static::SYNC_KEY],
            [
                'name' => 'Термостудия ' . static::BRAND_NAME,
                'code' => static::SUPPLIER_CODE,
                'title' => 'Термостудия: газовые котлы ' . static::BRAND_NAME,
                'description' => 'Скрапит газовые котлы ' . static::BRAND_NAME . ' с teplo.by: цены BYN, характеристики, сервис, документы, фото и промо-флаги.',
                'command' => $this->getName(),
                'source_url' => static::SOURCE_URL,
                'image_disk_path' => static::IMAGE_DISK_PATH,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        return DB::table('supplier_syncs')->where('key', static::SYNC_KEY)->value('id');
    }

    protected function categoryId(): int
    {
        $categoryId = DB::table('categories')->where('slug', static::CATEGORY_SLUG)->value('id');
        if (! $categoryId) {
            throw new \RuntimeException('Category not found by slug: ' . static::CATEGORY_SLUG);
        }

        return (int) $categoryId;
    }

    protected function normalizeName(string $name): string
    {
        $name = mb_strtoupper($name);
        $name = preg_replace('/\b(ГАЗОВЫЙ|КОТЕЛ|КОТЁЛ|ТРАДИЦИОННЫЙ|КОНДЕНСАЦИОННЫЙ|ARISTON|АРИСТОН|В\s+КОМПЛЕКТЕ\s+С\s+ДЫМОХОДОМ)\b/u', '', $name) ?? $name;
        $brand = preg_quote(mb_strtoupper(static::BRAND_NAME), '/');
        $name = preg_replace('/\b' . $brand . '\b/u', '', $name) ?? $name;
        $name = preg_replace('/[^А-ЯЁA-Z0-9().+\- ]+/u', ' ', $name) ?? $name;
        return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    }

    protected function nextKotlovSku(): string
    {
        $max = DB::table('products')
            ->where('sku', 'like', 'KOTLOV-%')
            ->pluck('sku')
            ->map(fn ($sku) => preg_match('/^KOTLOV-(\d+)$/', (string) $sku, $m) ? (int) $m[1] : 0)
            ->max() ?? 0;

        $next = max(0, (int) $max) + 1;
        do {
            $sku = sprintf('KOTLOV-%06d', $next++);
        } while (DB::table('products')->where('sku', $sku)->exists());

        return $sku;
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: static::BRAND_SLUG . '-gas-boiler';
        $slug = $base;
        $i = 2;

        while (DB::table('products')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    protected function fetch(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Mozilla/5.0 (compatible; KotlovBot/1.0)\r\nAccept-Language: ru,en;q=0.8\r\n",
                'timeout' => 30,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);

        $body = file_get_contents($url, false, $context);
        if ($body === false) {
            throw new \RuntimeException('Could not fetch ' . $url);
        }

        return $body;
    }

    protected function match(string $pattern, string $subject): ?string
    {
        return preg_match($pattern, $subject, $m)
            ? html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')
            : null;
    }

    protected function cleanText(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    protected function sanitizeHtml(string $value): string
    {
        $value = preg_replace('/<(script|style)\b[\s\S]*?<\/\1>/iu', '', $value) ?? $value;
        $value = preg_replace('/<a\b[^>]*>([\s\S]*?)<\/a>/iu', '$1', $value) ?? $value;
        $value = strip_tags($value, '<p><ul><ol><li><strong><b><em><i><br>');
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\n{3,}/u', "\n\n", $value) ?? $value);
    }
}

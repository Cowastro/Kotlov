<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncEliconGasMetersCommand extends Command
{
    protected $signature = 'supplier:sync-elicon-gas-meters
        {--apply : Write changes to the database}
        {--dry-run : Preview without writing changes to the database}
        {--limit= : Limit number of products for testing}
        {--no-images : Do not download product images}
        {--sleep=200 : Delay between product requests in milliseconds}';

    protected $description = 'Scrape elicon.by gas meters and sync prices, cards, images, specs and product attributes.';

    private const SUPPLIER_CODE = 'elicon';
    private const CATEGORY_ID = 96;
    private const BRAND_ID = 112;
    private const CATEGORY_URL = 'https://elicon.by/product-category/bitovie_schetchiki_gaza/';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $downloadImages = ! (bool) $this->option('no-images');

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: database will be updated.</>'
            : '<fg=yellow;options=bold>DRY RUN: database will not be changed.</>');

        try {
            $items = $this->scrapeListing();
        } catch (\Throwable $e) {
            $this->error('Listing scrape failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        if ($limit !== null && $limit > 0) {
            $items = array_slice($items, 0, $limit);
        }

        $this->info('Found gas meters: ' . count($items));

        if (! $apply) {
            $preview = [];
            foreach ($items as $item) {
                try {
                    $detail = $this->scrapeProduct($item['url']);
                    $preview[] = [
                        'article' => $item['article'],
                        'price' => $item['price'] !== null ? number_format($item['price'], 2, '.', '') : 'no price',
                        'images' => count(array_unique(array_filter(array_merge(
                            [$item['listing_image'] ?? null],
                            $detail['images_remote'] ?? []
                        )))),
                        'attributes' => count($detail['attributes'] ?? []),
                        'name' => mb_substr($item['name'], 0, 58),
                    ];
                } catch (\Throwable $e) {
                    $preview[] = [
                        'article' => $item['article'],
                        'price' => $item['price'] !== null ? number_format($item['price'], 2, '.', '') : 'no price',
                        'images' => 'error',
                        'attributes' => 'error',
                        'name' => mb_substr($item['name'], 0, 58),
                    ];
                }

                usleep(max(0, (int) $this->option('sleep')) * 1000);
            }

            $this->table(
                ['article', 'price', 'images', 'attributes', 'name'],
                $preview
            );
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

                $product = $this->findProduct($merged);
                $isNew = ! $product;
                $images = [];

                if ($downloadImages) {
                    $images = $this->downloadImages($merged);
                    $stats['images'] += count($images);
                }

                $productId = $this->upsertProduct($merged, $product, $images, $now);
                $productSku = (string) DB::table('products')->where('id', $productId)->value('sku');

                $this->upsertSupplierProduct($merged, $productId, $productSku, $supplierId, $syncId, $now);
                $this->upsertMapping($merged, $productId, $productSku, $now);
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

    private function scrapeListing(): array
    {
        $items = [];

        for ($page = 1; $page <= 4; $page++) {
            $url = $page === 1 ? self::CATEGORY_URL : self::CATEGORY_URL . 'page/' . $page . '/';
            $html = $this->fetch($url);

            foreach ($this->productNodes($html) as $nodeHtml) {
                $item = $this->parseListingItem($nodeHtml);
                if (! $item || ! preg_match('/^Счетчик(и)? газа/u', $item['name'])) {
                    continue;
                }

                $items[$item['article']] = $item;
            }
        }

        return array_values($items);
    }

    private function productNodes(string $html): array
    {
        preg_match_all('/<li class="[^"]*product[^"]*"[\s\S]*?<\/li>/u', $html, $matches);
        return $matches[0] ?? [];
    }

    private function parseListingItem(string $html): ?array
    {
        $name = $this->match('/<h2[^>]*class="[^"]*woocommerce-loop-product__title[^"]*"[^>]*>(.*?)<\/h2>/u', $html);
        $url = $this->match('/<a[^>]+href="([^"]+)"[^>]*class="[^"]*woocommerce-LoopProduct-link/u', $html)
            ?: $this->match('/<a[^>]+href="([^"]+)"/u', $html);
        $article = $this->match('/data-product_sku="([^"]*)"/u', $html);
        $wpId = $this->match('/post-([0-9]+)/u', $html);
        $image = $this->bestImageFromHtml($html);
        $priceText = $this->match('/<span class="price">([\s\S]*?)<\/span>/u', $html);

        $name = $this->cleanText($name ?? '');
        $url = html_entity_decode($url ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $article = html_entity_decode($article ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($name === '' || $url === '' || $article === '') {
            return null;
        }

        return [
            'wp_id' => $wpId ?: '',
            'article' => $article,
            'name' => $name,
            'url' => $url,
            'price' => $this->parsePrice($priceText),
            'listing_image' => $image,
        ];
    }

    private function scrapeProduct(string $url): array
    {
        $html = $this->fetch($url);
        $schema = $this->extractProductSchema($html);
        $description = $this->cleanDescription((string) ($schema['description'] ?? ''));
        $attrs = $this->parseAttributesFromDescription($description);
        $images = $this->extractImages($html, $schema);

        return [
            'h1' => $this->cleanText($this->match('/<h1[^>]*>(.*?)<\/h1>/u', $html) ?? '') ?: null,
            'content' => $this->descriptionToHtml($description),
            'description_text' => $description,
            'meta_title' => $this->cleanText($this->match('/<title>(.*?)<\/title>/u', $html) ?? ''),
            'meta_description' => $this->cleanText($this->match('/<meta name="description" content="([^"]*)"/u', $html) ?? ''),
            'images_remote' => $images,
            'attributes' => $attrs,
        ];
    }

    private function extractProductSchema(string $html): array
    {
        preg_match_all('/<script[^>]+type="application\/ld\+json"[^>]*>([\s\S]*?)<\/script>/u', $html, $matches);

        foreach ($matches[1] ?? [] as $json) {
            $decoded = json_decode(html_entity_decode(trim($json), ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
            $product = $this->findSchemaProduct($decoded);
            if ($product) {
                return $product;
            }
        }

        return [];
    }

    private function findSchemaProduct(mixed $node): ?array
    {
        if (! is_array($node)) {
            return null;
        }

        $type = $node['@type'] ?? null;
        if ($type === 'Product' || (is_array($type) && in_array('Product', $type, true))) {
            return $node;
        }

        foreach ($node as $value) {
            $found = $this->findSchemaProduct($value);
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    private function extractImages(string $html, array $schema): array
    {
        $images = [];

        if (! empty($schema['image'])) {
            $images[] = is_array($schema['image']) ? reset($schema['image']) : $schema['image'];
        }

        preg_match_all('/<meta property="og:image" content="([^"]+)"/u', $html, $ogMatches);
        foreach ($ogMatches[1] ?? [] as $url) {
            $images[] = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        preg_match_all('/<a[^>]+href="([^"]+)"[^>]*woocommerce-product-gallery__image/u', $html, $galleryMatches);
        foreach ($galleryMatches[1] ?? [] as $url) {
            $images[] = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return array_values(array_unique(array_filter($images, fn($url) => str_starts_with((string) $url, 'https://'))));
    }

    private function parseAttributesFromDescription(string $description): array
    {
        $attrs = [];
        $lines = preg_split('/\R/u', $description) ?: [];
        $inCharacteristics = false;

        foreach ($lines as $line) {
            $line = trim(preg_replace('/^[\s\-\*•]+/u', '', $line));

            if ($line === '') {
                continue;
            }

            if (str_contains(mb_strtolower($line), 'основные характеристики')) {
                $inCharacteristics = true;
                continue;
            }

            if (! $inCharacteristics || ! str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = array_map('trim', explode(':', $line, 2));
            if ($name !== '' && $value !== '' && mb_strlen($name) <= 120) {
                $attrs[$name] = $value;
            }
        }

        return $attrs;
    }

    private function upsertProduct(array $item, ?object $product, array $images, $now): int
    {
        $price = $item['price'];
        $attrs = $item['attributes'] ?? [];

        if (empty($images) && $product?->images) {
            $images = is_string($product->images) ? (json_decode($product->images, true) ?: []) : (array) $product->images;
        }

        $payload = [
            'category_id' => self::CATEGORY_ID,
            'brand_id' => self::BRAND_ID,
            'supplier_id' => null,
            'name' => $item['name'],
            'h1' => $item['h1'] ?: $item['name'],
            'price' => $price ?? 0,
            'price_old' => null,
            'currency' => 'BYN',
            'content' => $item['content'] ?: null,
            'short_description' => null,
            'images' => json_encode($images, JSON_UNESCAPED_UNICODE),
            'specs' => json_encode($attrs + $this->derivedSpecs($item), JSON_UNESCAPED_UNICODE),
            'video_url' => null,
            'weight' => $this->parseWeight($attrs['Вес'] ?? null),
            'unit' => 'шт',
            'warranty' => $attrs['Гарантийный срок'] ?? null,
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => $price !== null,
            'stock_qty' => null,
            'is_featured' => false,
            'is_new' => false,
            'is_sale' => false,
            'sort_order' => 0,
            'meta_title' => $item['meta_title'] ?: $item['name'] . ' купить в Минске',
            'meta_keywords' => 'счетчик газа, БелОМО, Эликон, ' . $item['article'],
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

            $attributeId = $this->ensureAttribute((string) $name, $now);
            DB::table('product_attribute_values')->updateOrInsert(
                ['product_id' => $productId, 'attribute_id' => $attributeId],
                [
                    'option_id' => null,
                    'is_checked' => null,
                    'value' => is_bool($value) ? ($value ? 'Да' : 'Нет') : (string) $value,
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
            'category_id' => self::CATEGORY_ID,
            'group_id' => 0,
            'sort_order' => 500,
            'type' => 'value',
            'name' => $name,
            'suffix' => null,
            'in_filter' => false,
            'in_sort' => false,
            'in_product' => true,
            'in_brief' => in_array($name, ['Тип', 'Номинальный расход', 'Максимальный расход', 'Габариты'], true),
            'is_comparable' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function downloadImages(array $item): array
    {
        $urls = array_values(array_unique(array_filter(array_merge(
            [$item['listing_image'] ?? null],
            $item['images_remote'] ?? []
        ))));

        $paths = [];
        $dir = public_path('img/products/elicon');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        foreach ($urls as $index => $url) {
            try {
                $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION)) ?: 'jpg';
                if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    $ext = 'jpg';
                }

                $base = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $item['article']);
                $filename = $base . '-' . ($index + 1) . '.' . $ext;
                $target = $dir . DIRECTORY_SEPARATOR . $filename;

                if (! file_exists($target)) {
                    file_put_contents($target, $this->fetch($url));
                }

                $paths[] = 'img/products/elicon/' . $filename;
            } catch (\Throwable $e) {
                $this->warn('  image skipped: ' . $url);
            }
        }

        return array_values(array_unique($paths));
    }

    private function findProduct(array $item): ?object
    {
        $supplierProduct = DB::table('supplier_products')
            ->where('supplier_article', $item['article'])
            ->whereNotNull('product_id')
            ->first();

        if ($supplierProduct) {
            $product = DB::table('products')->where('id', $supplierProduct->product_id)->first();
            if ($product) {
                return $product;
            }
        }

        $mapping = DB::table('supplier_product_mappings')
            ->where('supplier_code', self::SUPPLIER_CODE)
            ->where('supplier_article', $item['article'])
            ->whereNotNull('product_id')
            ->first();

        if ($mapping) {
            $product = DB::table('products')->where('id', $mapping->product_id)->first();
            if ($product) {
                return $product;
            }
        }

        $legacySku = array_flip($this->legacySkuToArticle())[$item['article']] ?? null;
        if ($legacySku) {
            $product = DB::table('products')->where('sku', $legacySku)->first();
            if ($product) {
                return $product;
            }
        }

        return DB::table('products')->where('sku', 'ELICON-' . $item['article'])->first();
    }

    private function upsertSupplierProduct(array $item, int $productId, string $productSku, int $supplierId, ?int $syncId, $now): void
    {
        DB::table('supplier_products')->updateOrInsert(
            [
                'supplier_id' => $supplierId,
                'supplier_article' => $item['article'],
            ],
            [
                'supplier_sync_id' => $syncId,
                'product_id' => $productId,
                'product_sku' => $productSku,
                'supplier_name' => $item['name'],
                'source_url' => $item['url'],
                'source_wp_id' => $item['wp_id'] ?? null,
                'price' => $item['price'],
                'currency' => 'BYN',
                'in_stock' => $item['price'] !== null,
                'match_status' => 'matched',
                'match_confidence' => 'manual',
                'raw' => json_encode([
                    'article' => $item['article'],
                    'name' => $item['name'],
                    'attributes' => $item['attributes'] ?? [],
                    'images_remote' => $item['images_remote'] ?? [],
                ], JSON_UNESCAPED_UNICODE),
                'last_synced_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    private function upsertMapping(array $item, int $productId, ?string $productSku, $now): void
    {
        DB::table('supplier_product_mappings')->updateOrInsert(
            [
                'supplier_code' => self::SUPPLIER_CODE,
                'supplier_article' => $item['article'],
            ],
            [
                'product_id' => $productId,
                'product_sku' => $productSku,
                'supplier_name' => $item['name'],
                'confidence' => 'manual',
                'is_active' => true,
                'notes' => sprintf('url=%s; wp_id=%s', $item['url'], $item['wp_id'] ?? ''),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    private function ensureSupplier($now): int
    {
        DB::table('suppliers')->updateOrInsert(
            ['code' => self::SUPPLIER_CODE],
            [
                'name' => 'Эликон',
                'currency' => 'BYN',
                'currency_rate' => 1,
                'contact' => self::CATEGORY_URL,
                'notes' => 'Каталог бытовых счетчиков газа БелОМО.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        return (int) DB::table('suppliers')
            ->where('code', self::SUPPLIER_CODE)
            ->value('id');
    }

    private function ensureSupplierSync($now): ?int
    {
        DB::table('supplier_syncs')->updateOrInsert(
            ['key' => 'elicon_gas_meters'],
            [
                'name' => 'Эликон',
                'code' => self::SUPPLIER_CODE,
                'title' => 'Эликон: счетчики газа',
                'description' => 'Обновляет цены, наличие, описания, характеристики и фотографии бытовых счетчиков газа.',
                'command' => 'supplier:sync-elicon-gas-meters',
                'source_url' => self::CATEGORY_URL,
                'image_disk_path' => 'img/products/elicon',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        return DB::table('supplier_syncs')
            ->where('key', 'elicon_gas_meters')
            ->value('id');
    }

    private function ensureBrand($now): void
    {
        $payload = [
            'name' => 'БелОМО',
            'slug' => 'belomo',
            'h1' => 'БелОМО',
            'country' => 'Беларусь',
            'producer' => 'ОАО "ММЗ имени С.И. Вавилова - управляющая компания холдинга БелОМО"',
            'is_active' => true,
            'updated_at' => $now,
        ];

        $existing = DB::table('brands')->where('id', self::BRAND_ID)->orWhere('slug', 'belomo')->first();
        $existing
            ? DB::table('brands')->where('id', $existing->id)->update($payload)
            : DB::table('brands')->insert($payload + ['id' => self::BRAND_ID, 'created_at' => $now]);
    }

    private function ensureCategory($now): void
    {
        $payload = [
            'parent_id' => 301,
            'name' => 'Счетчики газа',
            'slug' => 'schetchiki-gaza',
            'h1' => 'Счетчики газа',
            'type' => 'child',
            'sort_order' => 160,
            'is_active' => true,
            'updated_at' => $now,
        ];

        $existing = DB::table('categories')->where('id', self::CATEGORY_ID)->orWhere('slug', 'schetchiki-gaza')->first();
        $existing
            ? DB::table('categories')->where('id', $existing->id)->update($payload)
            : DB::table('categories')->insert($payload + ['id' => self::CATEGORY_ID, 'created_at' => $now]);
    }

    private function fetch(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Mozilla/5.0 (compatible; KotlovBot/1.0)\r\nAccept-Language: ru,en;q=0.8\r\n",
                'timeout' => 30,
            ],
            'ssl' => [
                'verify_peer' => true,
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

    private function bestImageFromHtml(string $html): ?string
    {
        $srcset = $this->match('/srcset="([^"]+)"/u', $html);
        if ($srcset) {
            $best = null;
            $bestWidth = 0;
            foreach (explode(',', $srcset) as $candidate) {
                if (preg_match('/(https?:\/\/\S+)\s+([0-9]+)w/u', trim($candidate), $match) && (int) $match[2] > $bestWidth) {
                    $best = $match[1];
                    $bestWidth = (int) $match[2];
                }
            }
            if ($best) {
                return $best;
            }
        }

        return $this->match('/<img[^>]+src="([^"]+)"/u', $html);
    }

    private function parsePrice(?string $raw): ?float
    {
        if ($raw === null) {
            return null;
        }

        $text = $this->cleanText($raw);
        $text = str_replace(["\xc2\xa0", ' ', 'BYN'], '', $text);
        $text = preg_replace('/[^\d,.]/u', '', $text);

        if ($text === '') {
            return null;
        }

        return round((float) str_replace(',', '.', $text), 2);
    }

    private function cleanText(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $value));
    }

    private function cleanDescription(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace("/\n{3,}/u", "\n\n", $value);
        return trim($value);
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

    private function derivedSpecs(array $item): array
    {
        $name = $item['name'];

        preg_match('/\bG\s*([0-9]+(?:[,.][0-9]+)?)/u', $name, $gMatch);
        preg_match('/L\s*=\s*([0-9]+)/u', $name, $lengthMatch);
        preg_match('/\((левый|правый)\)/ui', $name, $sideMatch);

        return array_filter([
            'Тип счетчика' => str_contains(mb_strtolower($name), 'ультразвуковой') ? 'ультразвуковой' : 'диафрагменный',
            'Типоразмер' => isset($gMatch[1]) ? 'G' . str_replace(',', '.', $gMatch[1]) : null,
            'Исполнение' => $sideMatch[1] ?? null,
            'Монтажная длина' => isset($lengthMatch[1]) ? $lengthMatch[1] . ' мм' : null,
            'Термокомпенсация' => str_contains($name, 'термокомпенсатором') ? 'Да' : null,
        ], fn($value) => $value !== null);
    }

    private function parseWeight(?string $value): ?float
    {
        if (! $value || ! preg_match('/([0-9]+(?:[,.][0-9]+)?)/u', $value, $match)) {
            return null;
        }

        return (float) str_replace(',', '.', $match[1]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'elicon-gas-meter';
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

    private function legacySkuToArticle(): array
    {
        return [
            'PS-002.021' => '8181-20',
            'PS-002.022' => '8181-00',
            'PS-007.727' => '8181-21',
            'PS-007.728' => '8181-01',
            'PS-007.759' => '8181-04',
            'PS-007.760' => '8181-05',
            'PS-007.761' => '8181-22',
            'PS-007.762' => '8181-23',
        ];
    }
}

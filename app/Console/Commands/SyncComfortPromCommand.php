<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncComfortPromCommand extends Command
{
    protected $signature = 'supplier:sync-comfortprom
        {--apply : Write changes to the database}
        {--limit= : Limit number of products for testing}
        {--no-images : Skip downloading product images}
        {--sleep=300 : Delay between requests in milliseconds}';

    protected $description = 'Scrape ComfortProm bath stoves from teplodvor.by and sync prices, cards, photos and attributes.';

    private const SUPPLIER_CODE = 'comfortprom';
    private const SUPPLIER_NAME = 'ComfortProm';
    private const SOURCE_URL = 'https://www.teplodvor.by/shop/pech-dlya-bani/comfortprom/';
    private const BASE_URL = 'https://www.teplodvor.by';
    private const IMAGE_DISK_PATH = 'img/products/comfortprom';
    private const CATEGORY_ID = 69;
    private const BRAND_SLUG = 'comfortprom';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $downloadImages = ! (bool) $this->option('no-images');
        $sleepMs = max(0, (int) ($this->option('sleep') ?? 300));

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

        $this->info(sprintf('Found %d ComfortProm products on teplodvor.by.', count($items)));

        if (! $apply) {
            return $this->dryRun($items, $sleepMs);
        }

        $now = now();
        $brandId = $this->ensureBrand($now);
        $supplierId = $this->ensureSupplier($now);
        $syncId = $this->ensureSupplierSync($supplierId, $now);

        $stats = [
            'created' => 0,
            'updated' => 0,
            'linked' => 0,
            'images' => 0,
            'attributes' => 0,
            'errors' => 0,
        ];

        DB::table('supplier_syncs')->where('id', $syncId)->update([
            'last_run_at' => $now,
            'last_status' => 'running',
            'updated_at' => $now,
        ]);

        foreach ($items as $i => $item) {
            $this->line(sprintf('[%d/%d] %s', $i + 1, count($items), mb_substr($item['name'], 0, 72)));

            try {
                $detail = $this->scrapeProduct($item['url']);
                $merged = array_merge($item, $detail);
                $merged['supplier_article'] = $this->supplierArticle($merged);

                $images = [];
                if ($downloadImages && ! empty($merged['images_remote'])) {
                    $images = $this->downloadImages($merged);
                    $stats['images'] += count($images);
                }

                $product = $this->findProduct($merged, $brandId, $supplierId);
                $productId = $this->upsertProduct($merged, $product, $images, $brandId, $now);
                $this->upsertSupplierProduct($merged, $supplierId, $syncId, $productId, $now);
                $stats[$product ? 'updated' : 'created']++;
                $stats['linked']++;
                $stats['attributes'] += $this->syncAttributes($productId, $merged['attributes'] ?? [], $now);

                usleep($sleepMs * 1000);
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn('  failed: ' . $e->getMessage());
            }
        }

        DB::table('supplier_syncs')->where('id', $syncId)->update([
            'last_status' => $stats['errors'] > 0 ? 'failed' : 'success',
            'last_exit_code' => $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS,
            'updated_at' => now(),
        ]);

        $this->table(['action', 'count'], array_map(
            fn ($key, $value) => [$key, $value],
            array_keys($stats),
            array_values($stats)
        ));

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function dryRun(array $items, int $sleepMs): int
    {
        $brandId = (int) (DB::table('brands')->where('slug', self::BRAND_SLUG)->value('id') ?? 0);
        $supplierId = (int) (DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id') ?? 0);
        $rows = [];

        foreach ($items as $item) {
            $detail = [];
            try {
                $detail = $this->scrapeProduct($item['url']);
                usleep($sleepMs * 1000);
            } catch (\Throwable $e) {
                // Keep catalog-level row visible in the report.
            }

            $merged = array_merge($item, $detail);
            $merged['supplier_article'] = $this->supplierArticle($merged);
            $product = $this->findProduct($merged, $brandId, $supplierId);

            $rows[] = [
                $product ? 'update' : 'create',
                $product->sku ?? '—',
                $merged['supplier_article'] ?? '—',
                $merged['price_byn'] ?? '—',
                mb_substr($merged['name'], 0, 68),
                count($merged['images_remote'] ?? []),
                count($merged['attributes'] ?? []),
            ];
        }

        $this->table(['action', 'sku', 'article', 'price', 'name', 'imgs', 'attrs'], $rows);
        $this->line('Run with --apply to update the database.');

        return self::SUCCESS;
    }

    private function scrapeCatalog(int $sleepMs): array
    {
        $items = [];
        $page = 1;

        do {
            $url = $page === 1 ? self::SOURCE_URL : self::SOURCE_URL . 'page' . $page . '/';
            $html = $this->fetch($url);
            $found = $this->parseListingPage($html, $items);
            $hasNext = str_contains($html, 'class="next_page"')
                || preg_match('/href="[^"]*comfortprom\/page' . ($page + 1) . '\/"/', $html);

            $page++;
            usleep($sleepMs * 1000);
        } while ($found > 0 && $hasNext && $page <= 20);

        return array_values($items);
    }

    private function parseListingPage(string $html, array &$items): int
    {
        $found = 0;

        preg_match_all(
            '/<div class="[^"]*\bjs_shop\b[^"]*\bproduct\b[^"]*">([\s\S]*?)(?=<div class="[^"]*\bjs_shop\b[^"]*\bproduct\b|<div class="previous_next|$)/u',
            $html,
            $blocks
        );

        foreach ($blocks[1] ?? [] as $block) {
            if (! preg_match('/name="good_id" value="(\d+)"/', $block, $idMatch)) {
                continue;
            }

            $goodId = $idMatch[1];
            if (isset($items[$goodId])) {
                continue;
            }

            if (! preg_match('/<a href="(https?:\/\/[^"]+)" class="shop-item-link">([\s\S]*?)<\/a>/u', $block, $linkMatch)) {
                continue;
            }

            if (! preg_match('/class="js_shop_price">([\d.,]+)</', $block, $priceMatch)) {
                continue;
            }

            $thumb = null;
            if (preg_match('/(?:src|data-lazy)="(\/userfls\/shop\/small\/[^"]+)"/', $block, $imgMatch)) {
                $thumb = self::BASE_URL . $imgMatch[1];
            }

            $items[$goodId] = [
                'good_id' => $goodId,
                'name' => $this->cleanText($linkMatch[2]),
                'url' => $linkMatch[1],
                'price_byn' => round((float) str_replace(',', '.', $priceMatch[1]), 2),
                'thumb' => $thumb,
            ];
            $found++;
        }

        return $found;
    }

    private function scrapeProduct(string $url): array
    {
        $html = $this->fetch($url);
        $h1 = $this->cleanText($this->match('/<h1[^>]*>([\s\S]*?)<\/h1>/u', $html) ?? '');

        $content = null;
        if (preg_match('/<section id="description">([\s\S]*?)<\/section>/u', $html, $dm)) {
            $raw = $dm[1];
            $raw = preg_replace('/<(script|style)\b[\s\S]*?<\/\1>/iu', '', $raw) ?? $raw;
            $raw = preg_replace('/<a\b[^>]*>([\s\S]*?)<\/a>/iu', '$1', $raw) ?? $raw;
            $raw = strip_tags($raw, '<p><ul><ol><li><strong><b><em><i><br>');
            $raw = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $raw = trim(preg_replace('/\n{3,}/u', "\n\n", $raw) ?? $raw);
            $content = $raw !== '' ? $raw : null;
        }

        $attributes = [];
        preg_match_all(
            '/<td class="parametr"><span[^>]*>([\s\S]*?)<\/span><\/td><td>([\s\S]*?)<\/td>/u',
            $html,
            $attrMatches,
            PREG_SET_ORDER
        );
        foreach ($attrMatches as $am) {
            $name = $this->cleanText($am[1]);
            $value = $this->cleanText($am[2]);
            if ($name !== '' && $value !== '' && mb_strlen($name) <= 120) {
                $attributes[$name] = $value;
            }
        }

        $article = $this->cleanText($this->match('/Артикул:\s*<strong[^>]*itemprop="sku"[^>]*>([\s\S]*?)<\/strong>/u', $html) ?? '');
        if ($article === '') {
            $article = $this->cleanText($this->match('/Артикул:\s*<strong[^>]*>([\s\S]*?)<\/strong>/u', $html) ?? '');
        }
        if ($article === '' && preg_match('/Артикул:\s*([^<\s]+)/u', $html, $articleMatch)) {
            $article = $this->cleanText($articleMatch[1]);
        }

        // ComfortProm поставляется по запросу: не показываем на витрине «нет в наличии»
        // и не наследуем розничный остаток чужого сайта как наш склад.
        $inStock = false;
        $stockText = 'Уточняйте наличие';

        preg_match_all('/userfls\/shop\/large\/([\d]+\/[^"\']+\.(?:jpg|jpeg|png|webp))/iu', $html, $imgMatches);
        $imagesRemote = array_values(array_unique($imgMatches[1] ?? []));

        return [
            'h1' => $h1,
            'content' => $content,
            'supplier_article' => $article,
            'attributes' => $attributes,
            'images_remote' => array_slice($imagesRemote, 0, 8),
            'in_stock' => $inStock,
            'stock_text' => $stockText,
        ];
    }

    private function downloadImages(array $item): array
    {
        $paths = [];
        $dir = public_path(self::IMAGE_DISK_PATH);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $base = self::BASE_URL . '/userfls/shop/large/';

        foreach ($item['images_remote'] as $filename) {
            try {
                $localName = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $filename);
                $target = $dir . DIRECTORY_SEPARATOR . $localName;

                if (! file_exists($target)) {
                    file_put_contents($target, $this->fetch($base . $filename));
                }

                $paths[] = self::IMAGE_DISK_PATH . '/' . $localName;
            } catch (\Throwable $e) {
                $this->warn('  image skipped: ' . $filename);
            }
        }

        return array_values(array_unique($paths));
    }

    private function findProduct(array $item, int $brandId, int $supplierId): ?object
    {
        $article = trim((string) ($item['supplier_article'] ?? ''));
        if ($supplierId > 0 && $article !== '') {
            $linked = DB::table('supplier_products')
                ->where('supplier_id', $supplierId)
                ->where('supplier_article_normalized', $this->normalizeArticle($article))
                ->whereNotNull('product_id')
                ->first();

            if ($linked) {
                $product = DB::table('products')->where('id', $linked->product_id)->first();
                if ($product) {
                    return $product;
                }
            }
        }

        if ($brandId > 0) {
            $norm = $this->normalizeName($item['name']);
            $modelKey = $this->comfortPromModelKey(
                $item['name'] . ' ' . ($item['supplier_article'] ?? '') . ' ' . ($item['url'] ?? '')
            );
            $candidates = DB::table('products')
                ->where('brand_id', $brandId)
                ->where('is_archived', false)
                ->get(['id', 'sku', 'name', 'slug', 'price', 'content', 'images']);

            foreach ($candidates as $candidate) {
                if ($this->hasDifferentSupplierArticle((int) $candidate->id, $supplierId, $article)) {
                    continue;
                }

                if ($this->normalizeName($candidate->name) === $norm) {
                    return $candidate;
                }
            }

            if ($modelKey !== '') {
                foreach ($candidates as $candidate) {
                    if ($this->hasDifferentSupplierArticle((int) $candidate->id, $supplierId, $article)) {
                        continue;
                    }

                    if ($this->comfortPromModelKey($candidate->name . ' ' . $candidate->slug) === $modelKey) {
                        return $candidate;
                    }
                }
            }
        }

        return null;
    }

    private function hasDifferentSupplierArticle(int $productId, int $supplierId, string $article): bool
    {
        if ($supplierId <= 0 || $article === '') {
            return false;
        }

        $normalized = $this->normalizeArticle($article);

        return DB::table('supplier_products')
            ->where('supplier_id', $supplierId)
            ->where('product_id', $productId)
            ->where('supplier_article_normalized', '!=', $normalized)
            ->exists();
    }

    private function upsertProduct(array $item, ?object $product, array $images, int $brandId, $now): int
    {
        if ($product && empty($images)) {
            $images = is_string($product->images) ? (json_decode($product->images, true) ?: []) : [];
        }

        $attrs = $item['attributes'] ?? [];
        $payload = [
            'category_id' => self::CATEGORY_ID,
            'brand_id' => $brandId,
            'supplier_id' => null,
            'name' => $item['name'],
            'h1' => ($item['h1'] ?? '') ?: $item['name'],
            'price' => $item['price_byn'],
            'currency' => 'BYN',
            'content' => $this->productDescription($item),
            'short_description' => $this->shortDescription($item),
            'images' => json_encode($images, JSON_UNESCAPED_UNICODE),
            'specs' => json_encode($attrs, JSON_UNESCAPED_UNICODE),
            'unit' => 'шт',
            'warranty' => '24 мес.',
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => false,
            'availability_status' => 'check',
            'stock_qty' => null,
            'is_featured' => false,
            'is_new' => false,
            'is_sale' => false,
            'sort_order' => 0,
            'meta_title' => $item['name'] . ' купить в %city%',
            'meta_keywords' => 'ComfortProm, банная печь, печь для бани, ' . $item['name'],
            'meta_description' => $item['name'] . ' — купить в Минске и Беларуси. Цена, фото, характеристики.',
            'rating' => 0,
            'reviews_count' => 0,
            'views_count' => 0,
            'updated_at' => $now,
        ];

        if ($product) {
            if ($product->content && ! ($item['content'] ?? null)) {
                unset($payload['content']);
            }
            if (! empty(json_decode((string) $product->images, true)) && empty($images)) {
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

    private function upsertSupplierProduct(array $item, int $supplierId, int $syncId, int $productId, $now): void
    {
        $article = $this->supplierArticle($item);
        $normalized = $this->normalizeArticle($article);

        DB::table('supplier_products')->updateOrInsert(
            [
                'supplier_id' => $supplierId,
                'supplier_article_normalized' => $normalized,
            ],
            [
                'supplier_sync_id' => $syncId,
                'product_id' => $productId,
                'product_sku' => DB::table('products')->where('id', $productId)->value('sku'),
                'supplier_article' => $article,
                'supplier_article_compact' => preg_replace('/[^A-Z0-9А-ЯЁ]+/u', '', mb_strtoupper($article)),
                'supplier_name' => $item['name'],
                'source_url' => $item['url'],
                'source_wp_id' => $item['good_id'],
                'price' => $item['price_byn'],
                'currency' => 'BYN',
                'currency_rate' => 1,
                'price_byn' => $item['price_byn'],
                'in_stock' => false,
                'stock_quantity' => null,
                'stock_status' => 'preorder',
                'stock_text' => 'Уточняйте наличие',
                'warehouse_name' => 'teplodvor.by',
                'delivery_days' => null,
                'last_stock_synced_at' => $now,
                'match_status' => 'matched',
                'match_confidence' => 1,
                'raw' => json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'last_synced_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    private function syncAttributes(int $productId, array $attributes, $now): int
    {
        $count = 0;

        foreach ($attributes as $name => $value) {
            if (! $name || ! $value) {
                continue;
            }

            $attrId = $this->ensureAttribute((string) $name, $now);

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

        $inBrief = in_array($name, ['Материал топки', 'Толщина металла', 'Объем парной, м³', 'Каменка', 'Диаметр дымохода, мм'], true);

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
            'in_brief' => $inBrief,
            'is_comparable' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function ensureBrand($now): int
    {
        $existing = DB::table('brands')->where('slug', self::BRAND_SLUG)->first();
        if ($existing) {
            return (int) $existing->id;
        }

        return (int) DB::table('brands')->insertGetId([
            'name' => self::SUPPLIER_NAME,
            'slug' => self::BRAND_SLUG,
            'h1' => self::SUPPLIER_NAME,
            'country' => 'Беларусь',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function ensureSupplier($now): int
    {
        DB::table('suppliers')->updateOrInsert(
            ['code' => self::SUPPLIER_CODE],
            [
                'name' => self::SUPPLIER_NAME,
                'currency' => 'BYN',
                'currency_rate' => 1,
                'contact' => self::SOURCE_URL,
                'notes' => 'ComfortProm products synced from teplodvor.by category pages.',
                'is_active' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        return (int) DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id');
    }

    private function ensureSupplierSync(int $supplierId, $now): int
    {
        DB::table('supplier_syncs')->updateOrInsert(
            ['key' => 'comfortprom_teplodvor'],
            [
                'name' => self::SUPPLIER_NAME,
                'code' => self::SUPPLIER_CODE,
                'title' => 'ComfortProm from teplodvor.by',
                'description' => 'Sync ComfortProm bath stoves from teplodvor.by cards.',
                'command' => 'supplier:sync-comfortprom',
                'source_url' => self::SOURCE_URL,
                'image_disk_path' => self::IMAGE_DISK_PATH,
                'is_active' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        return (int) DB::table('supplier_syncs')->where('key', 'comfortprom_teplodvor')->value('id');
    }

    private function shortDescription(array $item): string
    {
        $attrs = $item['attributes'] ?? [];
        $parts = array_filter([
            $attrs['Материал топки'] ?? null,
            $attrs['Толщина металла'] ?? null,
            $attrs['Объем парной, м³'] ?? null,
            $attrs['Каменка'] ?? null,
        ]);

        return $parts
            ? self::SUPPLIER_NAME . ' — банная печь: ' . implode(', ', $parts) . '.'
            : self::SUPPLIER_NAME . ' — банная печь для частной бани с доставкой по Беларуси.';
    }

    private function productDescription(array $item): string
    {
        $attrs = $item['attributes'] ?? [];
        $name = e((string) $item['name']);
        $volume = e((string) ($attrs['Объем парной, м³'] ?? $attrs['Объем помещения, м3'] ?? ''));
        $power = e((string) ($attrs['Мощность, кВт'] ?? ''));
        $material = e((string) ($attrs['Материал топки'] ?? ''));
        $thickness = e((string) ($attrs['Толщина металла'] ?? ''));
        $heater = e((string) ($attrs['Каменка'] ?? $attrs['Вид каменки'] ?? ''));
        $door = e((string) ($attrs['Дверь'] ?? $attrs['Дверца'] ?? $attrs['Тип дверцы'] ?? ''));
        $chimney = e((string) ($attrs['Диаметр дымохода, мм'] ?? ''));
        $weight = e((string) ($attrs['Вес, кг'] ?? ''));

        $facts = array_filter([
            $volume !== '' ? '<li><strong>Объём парной:</strong> ' . $volume . ' м³</li>' : null,
            $power !== '' ? '<li><strong>Мощность:</strong> ' . $power . ' кВт</li>' : null,
            $material !== '' ? '<li><strong>Материал топки:</strong> ' . $material . '</li>' : null,
            $thickness !== '' ? '<li><strong>Толщина металла:</strong> ' . $thickness . '</li>' : null,
            $heater !== '' ? '<li><strong>Каменка:</strong> ' . $heater . '</li>' : null,
            $door !== '' ? '<li><strong>Дверца:</strong> ' . $door . '</li>' : null,
            $chimney !== '' ? '<li><strong>Дымоход:</strong> Ø ' . $chimney . ' мм</li>' : null,
            $weight !== '' ? '<li><strong>Вес:</strong> ' . $weight . ' кг</li>' : null,
        ]);

        $factsHtml = $facts
            ? '<ul>' . implode('', $facts) . '</ul>'
            : '<p>Основные технические параметры смотрите во вкладке «Технические характеристики».</p>';

        return <<<HTML
<p><strong>{$name}</strong> — дровяная банная печь ComfortProm для частной бани и сауны. Если вы хотите купить печь для бани в %city%, эта модель подойдёт для проекта, где важны понятная конструкция, стабильный нагрев парной и удобная ежедневная эксплуатация.</p>

<p>ComfortProm выбирают для домашних бань, гостевых комплексов и небольших коммерческих парных, где печь должна быстро выходить на рабочий режим, держать температуру и не требовать сложного обслуживания. При подборе важно учитывать не только цену, но и объём парной, материал топки, тип каменки, дверцу, диаметр дымохода и схему безопасного монтажа.</p>

<h3>Что важно в этой модели</h3>
{$factsHtml}

<h3>Почему ComfortProm</h3>
<p>ComfortProm — белорусский производитель банных печей. В этой карточке производитель и поставщик для KOTLOV.BY — одна и та же белорусская компания, поэтому описание, цена и комплектация ведутся по актуальной линейке ComfortProm. Для покупателя это проще: можно уточнить наличие, комплектацию, дымоход и сопутствующие элементы без лишних посредников.</p>

<h3>Для какой бани подходит</h3>
<p>Эта печь ComfortProm подходит для бань, где нужна надёжная дровяная система отопления парной: быстро протопить помещение, поддерживать рабочую температуру и получить плотный банный пар. Для корректной работы печи особенно важны утепление парной, высота потолка, приток воздуха, правильный дымоход и соблюдение противопожарных отступов.</p>

<h3>Что можно заказать вместе с печью</h3>
<ul>
    <li>дымоход из нержавеющей стали под нужный диаметр;</li>
    <li>камни для банной печи и аксессуары для парной;</li>
    <li>элементы прохода через стену, потолок или кровлю;</li>
    <li>консультацию по размещению печи и безопасной схеме монтажа.</li>
</ul>

<h3>Поставка и подбор</h3>
<p>KOTLOV.BY помогает подобрать банную печь ComfortProm в %city% под конкретную парную, дымоход и режим использования. Если сомневаетесь между несколькими моделями — уточните объём парной, утепление, высоту потолка и планируемый формат парения, а мы подскажем подходящий вариант.</p>
HTML;
    }

    private function normalizeName(string $name): string
    {
        $name = mb_strtoupper($name);
        $name = preg_replace('/\b(ПЕЧЬ\s+ДЛЯ\s+БАНИ|ПЕЧЬ\s+БАННАЯ|ПЕЧЬ|БАННАЯ|COMFORTPROM|КОМФОРТПРОМ)\b/u', '', $name);
        $name = preg_replace('/[^А-ЯЁA-Z0-9(). ]+/u', ' ', $name);
        return trim(preg_replace('/\s+/u', ' ', $name));
    }

    private function comfortPromModelKey(string $name): string
    {
        $text = mb_strtolower($name);
        $text = str_replace(['ё', 'm3', 'м. куб.', 'м куб', 'кубов', 'куб.'], ['е', 'м3', 'м3', 'м3', 'м3', 'м3'], $text);

        $parts = [];

        if (str_contains($text, 'премиум') || str_contains($text, 'premium')) {
            $parts[] = 'premium';
        }
        if (str_contains($text, 'сталь')) {
            $parts[] = 'stal';
        } elseif (str_contains($text, 'чугун')) {
            $parts[] = 'chugun';
        }
        if (preg_match('/(\d+)\s*мм/u', $text, $m)) {
            $parts[] = $m[1] . 'mm';
        }
        if (preg_match('/(?:до\s*)?(\d+)\s*м3/u', $text, $m)) {
            $parts[] = $m[1] . 'm3';
        }
        if (str_contains($text, 'закрытая') || str_contains($text, 'zakryitaya') || str_contains($text, 'zakrytaia') || str_contains($text, 'zakr')) {
            $parts[] = 'zk';
        }
        if (str_contains($text, 'декор') || str_contains($text, 'decor') || str_contains($text, 'ddec')) {
            $parts[] = 'decor';
        }
        if (str_contains($text, 'панорама') || str_contains($text, 'panorama')) {
            $parts[] = 'panorama';
        } elseif (str_contains($text, 'стекл') || str_contains($text, 'steklo') || str_contains($text, 'steklom')) {
            $parts[] = 'steklo';
        }

        return implode('|', $parts);
    }

    private function supplierArticle(array $item): string
    {
        $article = trim((string) ($item['supplier_article'] ?? ''));

        return $article !== '' ? $article : 'teplodvor-' . (string) ($item['good_id'] ?? md5((string) ($item['url'] ?? 'comfortprom')));
    }

    private function normalizeArticle(string $article): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', '', $article) ?? $article));
    }

    private function nextKotlovSku(): string
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

    private function uniqueSlug(string $name): string
    {
        $base = $this->slugify($name) ?: 'comfortprom-product';
        $slug = $base;
        $i = 2;
        while (DB::table('products')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    private function slugify(string $name): string
    {
        $map = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
            'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
            'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
            'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '',
            'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        ];

        $slug = strtr(mb_strtolower($name), $map);
        $slug = preg_replace('/[^a-z0-9]+/u', '-', $slug) ?? $slug;
        return trim($slug, '-');
    }

    private function fetch(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Mozilla/5.0 (compatible; KotlovBot/1.0)\r\nAccept-Language: ru,en;q=0.8\r\n",
                'timeout' => 30,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);

        $lastError = null;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $body = @file_get_contents($url, false, $context);
            if ($body !== false) {
                return $body;
            }

            $lastError = error_get_last()['message'] ?? null;
            usleep(350000 * $attempt);
        }

        throw new \RuntimeException('Could not fetch ' . $url . ($lastError ? ': ' . $lastError : ''));
    }

    private function match(string $pattern, string $subject): ?string
    {
        return preg_match($pattern, $subject, $m)
            ? html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')
            : null;
    }

    private function cleanText(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}

<?php

namespace App\Console\Commands;

use App\Services\AiContentEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncSibirCommand extends Command
{
    protected $signature = 'supplier:sync-sibir
        {--apply : Write changes to the database}
        {--dry-run : Preview without writing changes}
        {--limit= : Limit number of products for testing}
        {--no-images : Skip downloading product images}
        {--enrich : Generate unique SEO descriptions via Claude API (requires ANTHROPIC_API_KEY)}
        {--sleep=300 : Delay between requests in milliseconds}';

    protected $description = 'Scrape Сибирь solid fuel boilers from teplodvor.by and sync prices, cards, photos and attributes.';

    private const SUPPLIER_CODE   = 'sibir';
    private const SYNC_KEY        = 'sibir_teplodvor';
    private const SOURCE_URL      = 'https://www.teplodvor.by/shop/kotly/tverdotoplivnye/sibir/';
    private const BASE_URL        = 'https://www.teplodvor.by';
    private const IMAGE_DISK_PATH = 'img/products/sibir';
    private const CATEGORY_ID     = 54;
    private const BRAND_SLUG      = 'sibir';

    // ── Entry point ───────────────────────────────────────────────────────────────

    public function handle(): int
    {
        $apply          = (bool) $this->option('apply');
        $limit          = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $downloadImages = ! (bool) $this->option('no-images');
        $enrichContent  = (bool) $this->option('enrich');
        $sleepMs        = max(0, (int) ($this->option('sleep') ?? 300));

        $enricher = new AiContentEnricher();
        if ($enrichContent && ! $enricher->isAvailable()) {
            $this->warn('--enrich: ANTHROPIC_API_KEY not set, content enrichment skipped.');
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

        $this->info(sprintf('Found %d products on teplodvor.by/sibir.', count($items)));

        if ($limit !== null && $limit > 0) {
            $items = array_slice($items, 0, $limit);
        }

        if (! $apply) {
            return $this->dryRun($items);
        }

        $now        = now();
        $brandId    = $this->ensureBrand($now);
        $supplierId = $this->ensureSupplier($now);
        $syncId     = $this->ensureSupplierSync($now);

        $stats = [
            'created'    => 0,
            'updated'    => 0,
            'no_change'  => 0,
            'images'     => 0,
            'attributes' => 0,
            'errors'     => 0,
        ];

        foreach ($items as $i => $item) {
            $this->line(sprintf('[%d/%d] %s', $i + 1, count($items), mb_substr($item['name'], 0, 60)));

            try {
                $detail = $this->scrapeProduct($item['url']);
                $merged = array_merge($item, $detail);

                $images = [];
                if ($downloadImages && ! empty($merged['images_remote'])) {
                    $images = $this->downloadImages($merged);
                    $stats['images'] += count($images);
                }

                $product = $this->findProduct($merged, $supplierId, $brandId);
                $isNew   = ! $product;

                if ($isNew && $enrichContent) {
                    $aiText = $enricher->enrich($item['name'], 'Сибирь', $merged['content'] ?? null, $merged['attributes'] ?? []);
                    if ($aiText) {
                        $merged['content'] = $aiText;
                        $this->line('  <fg=cyan>AI content generated.</>');
                    }
                }

                $productId  = $this->upsertProduct($merged, $product, $images, $brandId, $now);
                $productSku = (string) DB::table('products')->where('id', $productId)->value('sku');

                $this->upsertSupplierProduct($merged, $productId, $productSku, $supplierId, $syncId, $now);
                $attrs = $this->syncAttributes($productId, $merged['attributes'] ?? [], $now);
                $stats['attributes'] += $attrs;

                $stats[$isNew ? 'created' : (abs((float)($product->price ?? 0) - $merged['price_byn']) > 0.01 ? 'updated' : 'no_change')]++;

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

    // ── Dry-run ───────────────────────────────────────────────────────────────────

    private function dryRun(array $items): int
    {
        $supplierId = (int) (DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id') ?? 0);
        $brandId    = (int) (DB::table('brands')->where('slug', self::BRAND_SLUG)->value('id') ?? 0);
        $rows       = [];

        foreach ($items as $item) {
            $product = $this->findProduct($item, $supplierId, $brandId);
            $action  = ! $product
                ? 'create'
                : (abs((float)($product->price ?? 0) - $item['price_byn']) > 0.01
                    ? sprintf('update_price %.2f→%.2f', (float)$product->price, $item['price_byn'])
                    : 'no_change');

            $rows[] = [
                $action,
                number_format($item['price_byn'], 2),
                $product->sku ?? '—',
                mb_substr($item['name'], 0, 52),
            ];
        }

        $this->table(['action', 'price_byn', 'sku', 'name'], $rows);
        $this->line('Run with --apply to update the database.');

        return self::SUCCESS;
    }

    // ── Scraping ──────────────────────────────────────────────────────────────────

    private function scrapeCatalog(int $sleepMs): array
    {
        $items = [];
        $page  = 1;

        do {
            $url  = $page === 1 ? self::SOURCE_URL : self::SOURCE_URL . 'page' . $page . '/';
            $html = $this->fetch($url);

            $found   = $this->parseListingPage($html, $items);
            $hasNext = str_contains($html, 'class="next_page"')
                || preg_match('/href="[^"]*sibir\/page' . ($page + 1) . '\/"/', $html);

            $page++;
            usleep($sleepMs * 1000);
        } while ($found > 0 && $hasNext && $page <= 20);

        return array_values($items);
    }

    private function parseListingPage(string $html, array &$items): int
    {
        $found = 0;

        preg_match_all('/<div class="js_shop col[^"]*product">([\s\S]*?)(?=<div class="js_shop col|<div class="previous_next|$)/u', $html, $blocks);

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

            $url  = $linkMatch[1];
            $name = $this->cleanText($linkMatch[2]);

            if (! preg_match('/class="js_shop_price">([\d.,]+)</', $block, $priceMatch)) {
                continue;
            }
            $price = round((float) str_replace(',', '.', $priceMatch[1]), 2);

            $thumb = null;
            if (preg_match('/src="(\/userfls\/shop\/small\/[^"]+)"/', $block, $imgMatch)) {
                $thumb = self::BASE_URL . $imgMatch[1];
            }

            $items[$goodId] = [
                'good_id'   => $goodId,
                'name'      => $name,
                'url'       => $url,
                'price_byn' => $price,
                'thumb'     => $thumb,
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
            $html, $attrMatches, PREG_SET_ORDER
        );
        foreach ($attrMatches as $am) {
            $name  = $this->cleanText($am[1]);
            $value = $this->cleanText($am[2]);
            if ($name !== '' && $value !== '' && mb_strlen($name) <= 120) {
                $attributes[$name] = $value;
            }
        }

        preg_match_all('/userfls\/shop\/large\/([\d]+\/[^"\']+\.(?:jpg|jpeg|png|webp))/iu', $html, $imgMatches);
        $imagesRemote = array_values(array_unique($imgMatches[1] ?? []));

        return [
            'h1'            => $h1,
            'content'       => $content,
            'attributes'    => $attributes,
            'images_remote' => array_slice($imagesRemote, 0, 8),
        ];
    }

    private function downloadImages(array $item): array
    {
        $paths = [];
        $dir   = public_path(self::IMAGE_DISK_PATH);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $base = self::BASE_URL . '/userfls/shop/large/';

        foreach ($item['images_remote'] as $filename) {
            try {
                $localName = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $filename);
                $target    = $dir . DIRECTORY_SEPARATOR . $localName;

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

    // ── Matching ──────────────────────────────────────────────────────────────────

    private function findProduct(array $item, int $supplierId, int $brandId): ?object
    {
        if ($supplierId > 0) {
            $sp = DB::table('supplier_products')
                ->where('supplier_id', $supplierId)
                ->where('source_wp_id', $item['good_id'])
                ->whereNotNull('product_id')
                ->first();

            if ($sp) {
                return DB::table('products')->where('id', $sp->product_id)->first();
            }
        }

        if ($brandId > 0) {
            $norm       = $this->normalizeName($item['name']);
            $candidates = DB::table('products')
                ->where('brand_id', $brandId)
                ->where('is_archived', false)
                ->get(['id', 'sku', 'name', 'price', 'content', 'images']);

            foreach ($candidates as $c) {
                if ($this->normalizeName($c->name) === $norm) {
                    return $c;
                }
            }
        }

        return null;
    }

    private function normalizeName(string $name): string
    {
        $name = mb_strtoupper($name);
        $name = preg_replace('/\b(КОТЕЛ\s+ТВЕРДОТОПЛИВНЫЙ|ТВЕРДОТОПЛИВНЫЙ\s+КОТЕЛ|КОТЕЛ|СИБИРЬ|SIBIR)\b/u', '', $name);
        $name = preg_replace('/[^А-ЯЁA-Z0-9(). ]+/u', ' ', $name);
        return trim(preg_replace('/\s+/u', ' ', $name));
    }

    // ── Persistence ───────────────────────────────────────────────────────────────

    private function upsertProduct(array $item, ?object $product, array $images, int $brandId, $now): int
    {
        if ($product && empty($images)) {
            $existing = is_string($product->images) ? (json_decode($product->images, true) ?: []) : [];
            $images   = $existing;
        }

        $attrs   = $item['attributes'] ?? [];
        $payload = [
            'category_id'       => self::CATEGORY_ID,
            'brand_id'          => $brandId,
            'supplier_id'       => null,
            'name'              => $item['name'],
            'h1'                => ($item['h1'] ?? '') ?: $item['name'],
            'price'             => $item['price_byn'],
            'price_old'         => null,
            'currency'          => 'BYN',
            'content'           => $item['content'] ?? null,
            'short_description' => null,
            'images'            => json_encode($images, JSON_UNESCAPED_UNICODE),
            'specs'             => json_encode($attrs, JSON_UNESCAPED_UNICODE),
            'unit'              => 'шт',
            'warranty'          => isset($attrs['Гарантия мес']) ? $attrs['Гарантия мес'] . ' мес.' : null,
            'is_active'         => true,
            'is_archived'       => false,
            'in_stock'          => true,
            'stock_qty'         => null,
            'is_featured'       => false,
            'is_new'            => false,
            'is_sale'           => false,
            'sort_order'        => 0,
            'meta_title'        => $item['name'] . ' купить в %city%',
            'meta_keywords'     => 'Сибирь, твердотопливный котел, ' . $item['name'],
            'meta_description'  => $item['name'] . ' — купить по лучшей цене.',
            'rating'            => 0,
            'reviews_count'     => 0,
            'views_count'       => 0,
            'updated_at'        => $now,
        ];

        if ($product) {
            if ($product->content && ! ($item['content'] ?? null)) {
                unset($payload['content']);
            }
            $existing = is_string($product->images) ? (json_decode($product->images, true) ?: []) : [];
            if (! empty($existing) && empty($images)) {
                unset($payload['images']);
            }

            DB::table('products')->where('id', $product->id)->update($payload);
            return (int) $product->id;
        }

        $payload['sku']        = $this->nextKotlovSku();
        $payload['slug']       = $this->uniqueSlug($item['name']);
        $payload['created_at'] = $now;

        return (int) DB::table('products')->insertGetId($payload);
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

        $inBrief = in_array($name, [
            'Мощность',
            'Мощность, кВт',
            'Вид топлива',
            'Топливо',
            'Площадь обогрева',
            'Отапливаемая площадь',
            'КПД',
            'Диаметр дымохода',
        ], true);

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
            'in_brief'      => $inBrief,
            'is_comparable' => true,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
    }

    private function upsertSupplierProduct(array $item, int $productId, string $productSku, int $supplierId, ?int $syncId, $now): void
    {
        DB::table('supplier_products')->updateOrInsert(
            ['supplier_id' => $supplierId, 'source_wp_id' => $item['good_id']],
            [
                'supplier_article'            => $item['good_id'],
                'supplier_article_normalized' => $item['good_id'],
                'supplier_sync_id'            => $syncId,
                'product_id'                  => $productId,
                'product_sku'                 => $productSku,
                'supplier_name'               => $item['name'],
                'source_url'                  => $item['url'],
                'source_wp_id'                => $item['good_id'],
                'price'                       => $item['price_byn'],
                'currency'                    => 'BYN',
                'currency_rate'               => 1.0,
                'price_byn'                   => $item['price_byn'],
                'in_stock'                    => true,
                'match_status'                => 'matched',
                'match_confidence'            => 'auto_name',
                'raw'                         => json_encode([
                    'good_id' => $item['good_id'],
                    'thumb'   => $item['thumb'] ?? null,
                ], JSON_UNESCAPED_UNICODE),
                'last_synced_at' => $now,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]
        );
    }

    // ── Brand / supplier / sync registration ──────────────────────────────────────

    private function ensureBrand($now): int
    {
        $existing = DB::table('brands')->where('slug', self::BRAND_SLUG)->first();

        if ($existing) {
            return (int) $existing->id;
        }

        return (int) DB::table('brands')->insertGetId([
            'name'       => 'Сибирь',
            'slug'       => self::BRAND_SLUG,
            'h1'         => 'Сибирь',
            'country'    => 'Россия',
            'is_active'  => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function ensureSupplier($now): int
    {
        $existing = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->first();

        if ($existing) {
            DB::table('suppliers')->where('id', $existing->id)->update([
                'name'       => 'Сибирь (teplodvor.by)',
                'contact'    => self::SOURCE_URL,
                'is_active'  => true,
                'updated_at' => $now,
            ]);
            return (int) $existing->id;
        }

        return (int) DB::table('suppliers')->insertGetId([
            'code'          => self::SUPPLIER_CODE,
            'name'          => 'Сибирь (teplodvor.by)',
            'currency'      => 'BYN',
            'currency_rate' => 1,
            'contact'       => self::SOURCE_URL,
            'notes'         => 'Твердотопливные котлы Сибирь. Цены с teplodvor.by (BYN).',
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
                'name'            => 'Сибирь',
                'code'            => self::SUPPLIER_CODE,
                'title'           => 'Сибирь: твердотопливные котлы (teplodvor.by)',
                'description'     => 'Скрапит твердотопливные котлы Сибирь с teplodvor.by: цены BYN, описания, фото, характеристики.',
                'command'         => 'supplier:sync-sibir',
                'source_url'      => self::SOURCE_URL,
                'image_disk_path' => self::IMAGE_DISK_PATH,
                'is_active'       => true,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]
        );

        return DB::table('supplier_syncs')->where('key', self::SYNC_KEY)->value('id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────────

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
        $base = Str::slug($name) ?: 'sibir-product';
        $slug = $base;
        $i    = 2;
        while (DB::table('products')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    private function fetch(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'header'  => "User-Agent: Mozilla/5.0 (compatible; KotlovBot/1.0)\r\nAccept-Language: ru,en;q=0.8\r\n",
                'timeout' => 30,
            ],
            'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);

        $body = file_get_contents($url, false, $context);
        if ($body === false) {
            throw new \RuntimeException('Could not fetch ' . $url);
        }
        return $body;
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

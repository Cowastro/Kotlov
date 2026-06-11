<?php

namespace App\Console\Commands;

use App\Services\AiContentEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SyncMetabelCommand extends Command
{
    protected $signature = 'supplier:sync-metabel
        {--apply : Write changes to the database}
        {--dry-run : Preview without writing changes}
        {--limit= : Limit number of items for testing}
        {--no-images : Skip downloading product images from metabel.by}
        {--no-scrape : Skip website scraping — update prices only}
        {--enrich : Generate unique SEO descriptions via Claude API (requires ANTHROPIC_API_KEY)}
        {--sleep=200 : Delay between website requests in milliseconds}
        {--price-file= : Path to Excel price file (default: storage/prices/meta_2025.xlsx)}';

    protected $description = 'Sync MetaBel prices from Excel МРЦ and enrich product cards from metabel.by.';

    private const SUPPLIER_CODE   = 'metabel';
    private const SYNC_KEY        = 'metabel_price';
    private const BRAND_ID        = 45;
    private const SOURCE_URL      = 'https://metabel.by/produktsiya';
    private const BASE_URL        = 'https://metabel.by';
    private const IMAGE_DISK_PATH = 'img/products/metabel';

    private const CATEGORY_KEYWORDS = [
        'ПЕЧИ БАННЫЕ'        => 69,
        'ПЕЧИ-КАМИНЫ'        => 61,
        'ТОПКИ КАМИННЫЕ'     => 90,
        'ДВЕРИ ПЕЧНЫЕ'       => 287,
        'ГРИЛИ И АКСЕССУАРЫ' => null,
    ];

    private const CATALOG_URLS = [
        '/produktsiya/pechi-kaminy',
        '/produktsiya/bannye-pechi',
        '/produktsiya/kaminnye-topki',
        '/produktsiya/dveri-pechnye',
        '/produktsiya/barbekyu-gril',
        '/produktsiya/aksessuary',
    ];

    private const MANUAL_MATCH = [
        'ОКА С ПЛИТОЙ'                            => 'PS-002.811',
        'ПЕЧЬ БАННАЯ ПБМ 20В (С ВЕРМИКУЛИТОМ)'    => 'PS-009.545',
        'ПЕЧЬ БАННАЯ ПБМ 16 (В МОДИФИКАЦИИ ПС)'   => 'PS-006.589',
        'ПЕЧЬ БАННАЯ ПБМ 20 (В МОДИФИКАЦИИ ПС)'   => 'PS-012.050',
        'ДВЕРЬ ПЕЧНАЯ ДП-01'                      => 'PS-001.899',
        'ДВЕРЬ ПЕЧНАЯ ДП-02'                      => 'PS-001.900',
        'ДВЕРЬ ПЕЧНАЯ ДП-05'                      => 'PS-001.901',
        'ДВЕРЬ КАМИННАЯ ДК-01'                    => 'PS-001.902',
    ];

    // ── Entry point ───────────────────────────────────────────────────────────────

    public function handle(): int
    {
        $apply          = (bool) $this->option('apply');
        $limit          = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $downloadImages = ! (bool) $this->option('no-images');
        $scrapeWeb      = ! (bool) $this->option('no-scrape');
        $enrichContent  = (bool) $this->option('enrich');
        $sleepMs        = max(0, (int) ($this->option('sleep') ?? 200));

        $enricher = new AiContentEnricher();
        if ($enrichContent && ! $enricher->isAvailable()) {
            $this->warn('--enrich: no AI provider configured, enrichment skipped.');
            $enrichContent = false;
        }
        $priceFile      = $this->option('price-file') ?: storage_path('prices/meta_2025.xlsx');

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: database will be updated.</>'
            : '<fg=yellow;options=bold>DRY RUN: database will not be changed.</>');

        if (! file_exists($priceFile)) {
            $this->error("Price file not found: {$priceFile}");
            return self::FAILURE;
        }

        try {
            $items = $this->parsePriceFile($priceFile);
        } catch (\Throwable $e) {
            $this->error('Failed to parse price file: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info(sprintf('Parsed %d items from price file.', count($items)));

        if ($limit !== null && $limit > 0) {
            $items = array_slice($items, 0, $limit);
        }

        if (! $apply) {
            return $this->dryRun($items);
        }

        // Scrape website catalog list pages (fast — listing only, no detail pages yet)
        $webCatalog = [];
        if ($scrapeWeb) {
            $this->line('Scraping metabel.by catalog...');
            try {
                $webCatalog = $this->scrapeWebsiteCatalog($sleepMs);
                $this->info(sprintf('Found %d products on website.', count($webCatalog)));
            } catch (\Throwable $e) {
                $this->warn('Website catalog scrape failed: ' . $e->getMessage() . '. Continuing price-only.');
            }
        }

        $now        = now();
        $supplierId = $this->ensureSupplier($now);
        $syncId     = $this->ensureSupplierSync($now);

        $stats = [
            'created'           => 0,
            'update_price'      => 0,
            'no_change'         => 0,
            'content_updated'   => 0,
            'skipped_no_price'  => 0,
            'skipped_duplicate' => 0,
            'errors'            => 0,
        ];

        foreach ($items as $item) {
            if (($item['action'] ?? null) === 'skipped_duplicate') {
                $stats['skipped_duplicate']++;
                continue;
            }

            if (($item['price_byn'] ?? null) === null || $item['price_byn'] <= 0) {
                $stats['skipped_no_price']++;
                continue;
            }

            try {
                // Scrape detail page for this item if a matching URL was found
                $webData = [];
                if ($scrapeWeb && ! empty($webCatalog)) {
                    $webPage = $this->matchWebPage($item, $webCatalog);
                    if ($webPage) {
                        try {
                            $webData = $this->scrapeProductPage($webPage['url']);
                            usleep($sleepMs * 1000);
                        } catch (\Throwable $e) {
                            $this->warn('  web detail failed [' . $item['supplier_article'] . ']: ' . $e->getMessage());
                        }
                    }
                }

                $product = $this->findProduct($item, $supplierId);

                if (! $product) {
                    if ($enrichContent) {
                        $aiText = $enricher->enrich($item['price_name'], 'Мета-Бел', $webData['content'] ?? null, $webData['attributes'] ?? []);
                        if ($aiText) {
                            $webData['content'] = $aiText;
                            $this->line('  <fg=cyan>AI content generated.</>');
                        }
                    }

                    $productId = $this->createProduct($item, $webData, $downloadImages, $now);
                    $sku       = (string) DB::table('products')->where('id', $productId)->value('sku');
                    $this->upsertSupplierProduct($item, $productId, $sku, $supplierId, $syncId, $now);

                    if (! empty($webData['attributes'])) {
                        $this->syncAttributes($productId, $webData['attributes'], $item['cat_id'], $now);
                    }

                    $stats['created']++;
                    $this->line('[create] ' . $item['price_name']);
                } else {
                    $prevPrice = (float) ($product->price ?? 0);
                    $this->updateProductPrice($product->id, $item['price_byn'], $now);
                    $this->upsertSupplierProduct($item, $product->id, (string) $product->sku, $supplierId, $syncId, $now);

                    if (! empty($webData)) {
                        $enriched = $this->updateProductContent($product, $item['cat_id'], $webData, $downloadImages, $now);
                        if ($enriched > 0) {
                            $stats['content_updated']++;
                        }
                    }

                    if (abs($prevPrice - $item['price_byn']) > 0.01) {
                        $stats['update_price']++;
                        $this->line(sprintf(
                            '[price] %s  %.2f → %.2f BYN',
                            mb_substr($item['price_name'], 0, 40),
                            $prevPrice,
                            $item['price_byn']
                        ));
                    } else {
                        $stats['no_change']++;
                    }
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn('  error [' . $item['supplier_article'] . ']: ' . $e->getMessage());
            }
        }

        $this->showArchiveCandidates($items, $supplierId);

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
        $rows       = [];

        foreach ($items as $item) {
            if (($item['action'] ?? null) === 'skipped_duplicate') {
                $rows[] = ['skipped_duplicate', '—', '—', mb_substr($item['price_name'], 0, 52)];
                continue;
            }

            if (($item['price_byn'] ?? null) === null || $item['price_byn'] <= 0) {
                $rows[] = ['skipped_no_price', number_format($item['price_byn'] ?? 0, 2), '—', mb_substr($item['price_name'], 0, 52)];
                continue;
            }

            $product = $this->findProduct($item, $supplierId);

            if (! $product) {
                $action = 'create';
                $dbSku  = '—';
            } else {
                $prev   = (float) ($product->price ?? 0);
                $dbSku  = $product->sku;
                $action = abs($prev - $item['price_byn']) > 0.01
                    ? sprintf('update_price  %.2f→%.2f', $prev, $item['price_byn'])
                    : 'no_change';
            }

            $rows[] = [$action, number_format($item['price_byn'], 2), $dbSku, mb_substr($item['price_name'], 0, 52)];
        }

        $this->table(['action', 'price_byn', 'db_sku', 'price_name'], $rows);
        $this->showArchiveCandidates($items, $supplierId);
        $this->line('Run with --apply to update the database.');

        return self::SUCCESS;
    }

    // ── Excel parsing ──────────────────────────────────────────────────────────────

    private function parsePriceFile(string $path): array
    {
        $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, false);

        $items      = [];
        $currentCat = null;
        $seen       = [];

        foreach ($rows as $row) {
            $num  = trim((string) ($row[0] ?? ''));
            $name = trim((string) ($row[1] ?? ''));

            $combined = preg_replace('/\s+/', ' ', mb_strtoupper("{$num} {$name}"));
            foreach (self::CATEGORY_KEYWORDS as $keyword => $catId) {
                if (str_contains($combined, $keyword)) {
                    $currentCat = $catId;
                    break;
                }
            }

            if (! is_numeric($num) || $name === '') {
                continue;
            }

            $raw      = $row[3] ?? null;
            $rawStr   = str_replace(',', '', (string) $raw);
            $priceByn = is_numeric($rawStr) ? round((float) $rawStr, 2) : null;
            $article  = $this->supplierArticleFromName($name);

            if (isset($seen[$article])) {
                $items[] = [
                    'price_name'       => $name,
                    'supplier_article' => $article,
                    'price_byn'        => $priceByn,
                    'cat_id'           => $currentCat,
                    'action'           => 'skipped_duplicate',
                ];
                continue;
            }

            $seen[$article] = true;
            $items[]        = [
                'price_name'       => $name,
                'supplier_article' => $article,
                'price_byn'        => $priceByn,
                'cat_id'           => $currentCat,
            ];
        }

        return $items;
    }

    private function supplierArticleFromName(string $name): string
    {
        if (preg_match('/[«"](.*?)[»"]/u', $name, $m)) {
            return $this->normalizeSupplierArticle($m[1]);
        }

        return $this->normalizeSupplierArticle($name);
    }

    // ── Website scraping ──────────────────────────────────────────────────────────

    /**
     * Scrape all MetaBel catalog pages and return a map:
     * normalizedKey => ['url' => '...', 'name' => '...']
     */
    private function scrapeWebsiteCatalog(int $sleepMs): array
    {
        $catalog = [];

        foreach (self::CATALOG_URLS as $categoryPath) {
            $page = 0;

            do {
                $url  = self::BASE_URL . $categoryPath . ($page > 0 ? '?start=' . ($page * 12) : '');
                $html = $this->fetch($url);
                $found = $this->parseListingPage($html, $catalog);
                $hasNext = str_contains($html, 'class="hasTooltip pagenav">Вперед');
                $page++;
                usleep($sleepMs * 1000);
            } while ($found > 0 && $hasNext && $page < 20);
        }

        return $catalog;
    }

    private function parseListingPage(string $html, array &$catalog): int
    {
        $found = 0;

        // Each product has a <div class="name"><a href="/produktsiya/...">Name</a></div>
        preg_match_all('/<div class="name">\s*<a href="([^"]+)">([\s\S]*?)<\/a>/u', $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $url  = self::BASE_URL . $match[1];
            $name = $this->cleanText($match[2]);

            if ($name === '') {
                continue;
            }

            $key = $this->normalizePriceName($name);

            if ($key !== '' && ! isset($catalog[$key])) {
                $catalog[$key] = ['url' => $url, 'name' => $name];
                $found++;
            }
        }

        return $found;
    }

    private function matchWebPage(array $item, array $webCatalog): ?array
    {
        $key = $this->normalizePriceName($item['price_name']);
        return $webCatalog[$key] ?? null;
    }

    private function scrapeProductPage(string $url): array
    {
        $html = $this->fetch($url);

        return [
            'h1'             => $this->cleanText($this->match('/<h1[^>]*>([\s\S]*?)<\/h1>/u', $html) ?? ''),
            'content'        => $this->extractDescription($html),
            'images_remote'  => $this->extractImages($html),
            'attributes'     => $this->parseAttributes($html),
        ];
    }

    private function extractDescription(string $html): ?string
    {
        if (! preg_match('/<div class="jshop_prod_description">([\s\S]*?)<\/div>/u', $html, $m)) {
            return null;
        }

        $content = $m[1];
        // Strip the <h3>ОПИСАНИЕ</h3> header
        $content = preg_replace('/<h3[^>]*>[\s\S]*?<\/h3>/iu', '', $content) ?? $content;
        // Remove links, scripts, styles
        $content = preg_replace('/<(script|style)\b[\s\S]*?<\/\1>/iu', '', $content) ?? $content;
        $content = preg_replace('/<a\b[^>]*>([\s\S]*?)<\/a>/iu', '$1', $content) ?? $content;
        // Keep only safe tags
        $content = strip_tags($content, '<p><ul><ol><li><strong><b><em><i><br>');
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = preg_replace('/\n{3,}/u', "\n\n", $content) ?? $content;
        $content = trim($content);

        return $content !== '' ? $content : null;
    }

    private function extractImages(string $html): array
    {
        // Full images have "full_" prefix in img_products directory
        preg_match_all('/img_products\/(full_[^"\']+\.(?:jpg|jpeg|png|webp))/iu', $html, $matches);
        $images = array_values(array_unique($matches[1] ?? []));

        return array_slice($images, 0, 8);
    }

    private function parseAttributes(string $html): array
    {
        $attrs = [];

        preg_match_all(
            '/<span class="extra_fields_name">([\s\S]*?)<\/span>[\s\S]*?<span class="extra_fields_value">([\s\S]*?)<\/span>/u',
            $html, $matches, PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $name  = $this->normalizeAttributeName($this->cleanText($match[1]));
            $value = $this->cleanText($match[2]);

            if ($name !== '' && $value !== '' && mb_strlen($name) <= 120) {
                $attrs[$name] = $value;
            }
        }

        return $attrs;
    }

    private function downloadImages(array $filenames): array
    {
        $paths = [];
        $dir   = public_path(self::IMAGE_DISK_PATH);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $base = 'https://metabel.by/components/com_jshopping/files/img_products/';

        foreach ($filenames as $filename) {
            try {
                $ext       = strtolower(pathinfo($filename, PATHINFO_EXTENSION)) ?: 'jpg';
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

    private function findProduct(array $item, int $supplierId): ?object
    {
        if ($supplierId > 0) {
            $sp = DB::table('supplier_products')
                ->where('supplier_id', $supplierId)
                ->where('supplier_article', $item['supplier_article'])
                ->whereNotNull('product_id')
                ->first();

            if ($sp) {
                return DB::table('products')->where('id', $sp->product_id)->first();
            }
        }

        if (isset(self::MANUAL_MATCH[$item['supplier_article']])) {
            return DB::table('products')
                ->where('sku', self::MANUAL_MATCH[$item['supplier_article']])
                ->first();
        }

        $pNorm = $this->normalizePriceName($item['price_name']);

        if ($pNorm === '') {
            return null;
        }

        $candidates = DB::table('products')
            ->where('brand_id', self::BRAND_ID)
            ->where('is_archived', false)
            ->get(['id', 'sku', 'name', 'price']);

        foreach ($candidates as $candidate) {
            if ($this->normalizeDbName($candidate->name) === $pNorm) {
                return $candidate;
            }
        }

        return null;
    }

    private function normalizePriceName(string $name): string
    {
        $name = mb_strtoupper($name);

        if (preg_match('/\(В\s+МОДИФИКАЦИИ\s+([^)]+)\)/u', $name, $m)) {
            $name = $m[1];
        } elseif (preg_match('/[«"](.*?)[»"]/u', $name, $m)) {
            $name = $m[1];
        } else {
            $name = preg_replace('/\b(АОТК?В?|ТКТ)\s*[\d.,]+[-\d.,]*/u', '', $name);
            $name = preg_replace('/\b(ПЕЧЬ-КАМИН|ПЕЧЬ|ТОПКА|КАМИННАЯ|БАННАЯ|КАМЕНКА)\b/u', '', $name);
            $name = preg_replace('/\(([^)]+)\)/u', ' $1 ', $name);
        }

        $name = preg_replace('/[^А-ЯЁA-Z0-9+ ]+/u', ' ', $name);
        return trim(preg_replace('/\s+/u', ' ', $name));
    }

    private function normalizeDbName(string $name): string
    {
        $name = mb_strtoupper($name);
        $name = preg_replace('/\bМЕТА[-\s]*БЕЛ\b/u', '', $name);
        $name = preg_replace('/\b(ПЕЧЬ-КАМИН|ПЕЧЬ|ТОПКА|КАМИННАЯ|КАМИННЫЙ|БАННАЯ|КАМЕНКА|ДРОВЯНАЯ|ОТОПИТЕЛЬНАЯ)\b/u', '', $name);
        $name = preg_replace('/\(В\s+МОДИФИКАЦИИ\s+([^)]+)\)/iu', ' $1 ', $name);
        $name = preg_replace('/\([^)]+\)/u', '', $name);
        $name = preg_replace('/\b(АОТК?В?|ТКТ)\s*[-–]?\s*\d[\d.,\-]*/u', '', $name);
        $name = preg_replace('/\b\d+\s*КВТ\b/iu', '', $name);

        $name = preg_replace('/[^А-ЯЁA-Z0-9+ ]+/u', ' ', $name);
        return trim(preg_replace('/\s+/u', ' ', $name));
    }

    // ── Persistence ───────────────────────────────────────────────────────────────

    private function createProduct(array $item, array $webData, bool $downloadImages, $now): int
    {
        $name   = $this->buildProductName($item);
        $h1     = $webData['h1'] ?? '';
        $images = [];

        if ($downloadImages && ! empty($webData['images_remote'])) {
            $images = $this->downloadImages($webData['images_remote']);
        }

        return (int) DB::table('products')->insertGetId([
            'category_id'       => $item['cat_id'] ?? 287,
            'brand_id'          => self::BRAND_ID,
            'supplier_id'       => null,
            'name'              => $name,
            'h1'                => $h1 ?: $name,
            'sku'               => $this->nextKotlovSku(),
            'slug'              => $this->uniqueSlug($name),
            'price'             => $item['price_byn'],
            'price_old'         => null,
            'currency'          => 'BYN',
            'content'           => $webData['content'] ?? null,
            'short_description' => null,
            'images'            => json_encode($images, JSON_UNESCAPED_UNICODE),
            'specs'             => json_encode($webData['attributes'] ?? [], JSON_UNESCAPED_UNICODE),
            'unit'              => 'шт',
            'warranty'          => $webData['attributes']['Гарантия'] ?? null,
            'is_active'         => true,
            'is_archived'       => false,
            'in_stock'          => true,
            'stock_qty'         => null,
            'is_featured'       => false,
            'is_new'            => true,
            'is_sale'           => false,
            'sort_order'        => 0,
            'meta_title'        => $name . ' купить в Минске',
            'meta_keywords'     => 'Мета-Бел, ' . $name,
            'meta_description'  => $name . ' — купить по лучшей цене.',
            'rating'            => 0,
            'reviews_count'     => 0,
            'views_count'       => 0,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);
    }

    private function updateProductPrice(int $productId, float $priceByn, $now): void
    {
        DB::table('products')->where('id', $productId)->update([
            'price'      => $priceByn,
            'updated_at' => $now,
        ]);
    }

    /**
     * Enrich existing product with website content/images/attributes.
     * Only fills in what is currently missing — never overwrites existing content.
     * Returns the number of fields actually updated.
     */
    private function updateProductContent(object $product, ?int $categoryId, array $webData, bool $downloadImages, $now): int
    {
        $updates   = [];
        $enriched  = 0;

        if (! $product->content && ! empty($webData['content'])) {
            $updates['content'] = $webData['content'];
            $enriched++;
        }

        $existingImages = json_decode($product->images ?? '[]', true) ?: [];
        if (empty($existingImages) && ! empty($webData['images_remote']) && $downloadImages) {
            $updates['images'] = json_encode(
                $this->downloadImages($webData['images_remote']),
                JSON_UNESCAPED_UNICODE
            );
            $enriched++;
        }

        if (! empty($updates)) {
            $updates['updated_at'] = $now;
            DB::table('products')->where('id', $product->id)->update($updates);
        }

        if (! empty($webData['attributes'])) {
            $count = $this->syncAttributes((int) $product->id, $webData['attributes'], $categoryId, $now);
            if ($count > 0) {
                $enriched++;
            }
        }

        return $enriched;
    }

    private function syncAttributes(int $productId, array $attributes, ?int $categoryId, $now): int
    {
        $count = 0;
        $catId = $categoryId ?? 287;

        foreach ($attributes as $name => $value) {
            if (! $name || ! $value) {
                continue;
            }

            $attrId = $this->ensureAttribute((string) $name, $catId, $now);

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

    private function ensureAttribute(string $name, int $categoryId, $now): int
    {
        $existing = DB::table('attributes')
            ->where('category_id', $categoryId)
            ->where('name', $name)
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        $inBrief = in_array($name, ['Мощность', 'Площадь обогрева', 'Площадь отапливаемого помещения', 'Вид топки', 'Материал'], true);

        return (int) DB::table('attributes')->insertGetId([
            'category_id'   => $categoryId,
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
            ['supplier_id' => $supplierId, 'supplier_article' => $item['supplier_article']],
            [
                'supplier_article_normalized' => $item['supplier_article'],
                'supplier_sync_id'            => $syncId,
                'product_id'                  => $productId,
                'product_sku'                 => $productSku,
                'supplier_name'               => $item['price_name'],
                'source_url'                  => self::SOURCE_URL,
                'source_wp_id'                => null,
                'price'                       => $item['price_byn'],
                'currency'                    => 'BYN',
                'currency_rate'               => 1.0,
                'price_byn'                   => $item['price_byn'],
                'in_stock'                    => true,
                'match_status'                => 'matched',
                'match_confidence'            => 'auto_name',
                'raw'                         => json_encode(['cat_id' => $item['cat_id']], JSON_UNESCAPED_UNICODE),
                'last_synced_at'              => $now,
                'created_at'                  => $now,
                'updated_at'                  => $now,
            ]
        );
    }

    private function buildProductName(array $item): string
    {
        $model  = $this->extractModelFromPriceName($item['price_name']);
        $prefix = match ($item['cat_id']) {
            69      => 'Печь банная',
            61      => 'Печь-камин',
            90      => 'Топка каминная',
            default => '',
        };

        if ($prefix && $model !== $item['price_name']) {
            return trim("{$prefix} Мета-Бел {$model}");
        }

        return preg_replace('/\s+/', ' ', trim($item['price_name']));
    }

    private function extractModelFromPriceName(string $name): string
    {
        if (preg_match('/[«"](.*?)[»"]/u', $name, $m)) {
            return trim($m[1]);
        }

        if (preg_match('/\(в\s+модификации\s+([^)]+)\)/iu', $name, $m)) {
            return trim($m[1]);
        }

        return trim($name);
    }

    private function showArchiveCandidates(array $priceItems, int $supplierId): void
    {
        $matchedIds = [];

        foreach ($priceItems as $item) {
            if (($item['action'] ?? null) === 'skipped_duplicate') {
                continue;
            }

            $product = $this->findProduct($item, $supplierId);
            if ($product) {
                $matchedIds[] = (int) $product->id;
            }
        }

        $candidates = DB::table('products')
            ->where('brand_id', self::BRAND_ID)
            ->where('is_archived', false)
            ->whereNotIn('id', $matchedIds ?: [0])
            ->get(['sku', 'name', 'price']);

        if ($candidates->isEmpty()) {
            return;
        }

        $this->warn(sprintf("\n⚠  Кандидаты в архив (%d) — нет в прайсе, вручную:", $candidates->count()));

        $this->table(
            ['sku', 'price_byn', 'name'],
            $candidates->map(fn ($p) => [
                $p->sku,
                number_format((float) $p->price, 2),
                mb_substr($p->name, 0, 60),
            ])->all()
        );
    }

    // ── Supplier / sync registration ──────────────────────────────────────────────

    private function ensureSupplier($now): int
    {
        $existing = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->first();

        if ($existing) {
            DB::table('suppliers')->where('id', $existing->id)->update([
                'name'       => 'Мета-Бел',
                'contact'    => self::SOURCE_URL,
                'is_active'  => true,
                'updated_at' => $now,
            ]);

            return (int) $existing->id;
        }

        return (int) DB::table('suppliers')->insertGetId([
            'code'          => self::SUPPLIER_CODE,
            'name'          => 'Мета-Бел',
            'currency'      => 'BYN',
            'currency_rate' => 1,
            'contact'       => self::SOURCE_URL,
            'notes'         => 'Белорусский производитель печей и топок. Прайс: Excel МРЦ. Цены в BYN.',
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
                'name'            => 'Мета-Бел',
                'code'            => self::SUPPLIER_CODE,
                'title'           => 'МЕТА-БЕЛ: цены + карточки',
                'description'     => 'Обновляет цены из Excel МРЦ и обогащает карточки (описание, фото, характеристики) с metabel.by.',
                'command'         => 'supplier:sync-metabel',
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

    private function normalizeAttributeName(string $name): string
    {
        $name = trim(str_replace("\u{A0}", ' ', $name), " :\t");
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);

        return match ($name) {
            'Номинальная тепловая  мощность',
            'Номинальная тепловая мощность' => 'Мощность',
            'Отапливаемая площадь',
            'Площадь отапливаемого помещения' => 'Площадь обогрева',
            'Масса' => 'Вес',
            'Диаметр дымохода, мм' => 'Диаметр дымохода',
            default => $name,
        };
    }

    private function normalizeSupplierArticle(string $s): string
    {
        $s = mb_strtoupper(trim($s));
        $s = str_replace(
            ["\u{AB}", "\u{BB}", "\u{201C}", "\u{201D}", "\u{2018}", "\u{2019}", "\u{2013}", "\u{2014}", "\u{2212}"],
            ['',       '',       '',         '',         '',         '',         '-',        '-',        '-'],
            $s
        );
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return trim($s);
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
        $base = Str::slug($name) ?: 'metabel-product';
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

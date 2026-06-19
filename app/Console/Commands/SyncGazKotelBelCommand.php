<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sync ГазКотелБел (АТЕМ / ЖИТОМИР / GKB) price list into KOTLOV.
 *
 * The price list is a Google Sheets CSV with the following columns (0-indexed):
 *   0: Наименование товара  (article code or full name)
 *   1: Ед. изм.            ("шт" for product rows, empty for section headers)
 *   2: Кол-во верхн. дым   (stock — top-flue variant)
 *   3: Кол-во горизонт.    (stock — horizontal-flue variant)
 *   4: Цена оптовая (BYN)  (supplier purchase cost, Belarusian rubles)
 *   5: РРЦ (BYN)           (recommended retail price, Belarusian rubles)
 *
 * Section headers are rows where col[1] is empty and col[0] is not "ИТОГО".
 * Prices are in Belarusian Rubles (BYN) — no conversion needed.
 *
 *   php artisan supplier:sync-gazkotelbel --dry-run
 *   php artisan supplier:sync-gazkotelbel --apply
 *   php artisan supplier:sync-gazkotelbel --apply --create-new
 */
class SyncGazKotelBelCommand extends Command
{
    protected $signature = 'supplier:sync-gazkotelbel
        {--dry-run : Preview changes without writing}
        {--apply : Write changes to the database}
        {--create-new : Create catalog products for unmatched price-list rows}
        {--sheet-url= : Override the default Google Sheets URL}
        {--price-file= : Path to a local CSV file (skips download)}
        {--limit= : Process only the first N product rows}';

    protected $description = 'Sync ГазКотелБел (ЖИТОМИР/GKB) prices, stock and new products from Google Sheets.';

    private const SUPPLIER_CODE = 'gazkotelbel';
    private const SUPPLIER_NAME = 'ГазКотелБел';
    private const SOURCE_URL    = 'https://gazkotelbel.com/';
    private const SYNC_KEY      = 'gazkotelbel_price';
    private const DEFAULT_SHEET_URL = 'https://docs.google.com/spreadsheets/d/1ry8S1_DCHYuWgJ1uGHAgMhTLi9CfGEXpNj77oNHOo9Y/export?format=csv';
    private const CACHE_PATH = 'supplier-cache/gazkotelbel-price.csv';

    /** Category id for rows we can't classify. */
    private const DEFAULT_CATEGORY = 53; // Газовые котлы

    /** Section keyword → category_id (checked in order; first match wins) */
    private const SECTION_CATEGORY_MAP = [
        'конвектор'  => 304, // Газовые конвекторы (created if missing)
        'водонагрев' => 298, // Водогрейная колонка
        'впг'        => 298,
        'комби'      => 101, // Комбинированные котлы
        'житомир-9'  => 101,
        'твердое'    => 54,  // Твердотопливные
        'аотв'       => 54,
        'актв'       => 54,
        'датчик'     => 106, // Сигнализаторы загазованности
        'насос'      => 60,  // Циркуляционные насосы
        'тэн'        => 291, // Аксессуары / комплектующие
        'электрорез' => 291,
        // "котел-колонка" (Житомир-10 style descriptor) → газовые котлы (default 53)
    ];

    /** Section keyword → brand name */
    private const SECTION_BRAND_MAP = [
        'gkb'       => 'GKB',
        'датчик'    => 'GKB',
        'насос'     => 'GKB',
        'житомир'   => 'Житомир',
        'водонагрев'=> 'Житомир',
        'впг'       => 'Житомир',
    ];

    private int $supplierId  = 0;
    private int $syncId      = 0;

    // brand name → id cache
    private array $brandCache = [];
    // category id cache
    private array $categoryExists = [];

    public function handle(): int
    {
        $apply     = (bool) $this->option('apply') && ! $this->option('dry-run');
        $dryRun    = ! $apply;
        $createNew = (bool) $this->option('create-new');
        $limit     = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        $this->line($dryRun
            ? '<fg=yellow;options=bold>DRY RUN — database will not be changed.</>'
            : '<fg=red;options=bold>APPLY — database will be updated.</>');
        $this->line('Prices are in BYN (no conversion needed).');

        // Load / download price file
        try {
            $csvPath = $this->resolveCsv();
            $rows    = $this->parseCsv($csvPath);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info(sprintf('Parsed %d product rows', count($rows)));

        if ($limit !== null) {
            $rows = array_slice($rows, 0, $limit);
        }

        if (! $apply) {
            return $this->showDryRun($rows);
        }

        // Ensure supplier + sync records exist
        $this->supplierId = $this->ensureSupplier();
        $this->syncId     = $this->ensureSync();

        $stats = ['matched' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        $now   = now();

        foreach ($rows as $row) {
            try {
                $brandId    = $this->ensureBrand($row['brand']);
                $categoryId = $this->resolveCategory($row['section_category']);

                $product = $this->findProduct($row, $brandId);

                if ($product) {
                    $stats['matched']++;
                    $changed = $this->upsertSupplierProduct($product->id, $row, $now);
                    if ($changed) {
                        $stats['updated']++;
                        $this->updateProductPrice($product->id, $row, $now);
                    }
                    $this->line(sprintf('  <fg=green>matched</> [%s] %s qty=%d cost=%.2f BYN',
                        $row['article'], $row['name'], $row['qty'], $row['cost_byn']));
                } elseif ($createNew) {
                    $id = $this->createProduct($row, $brandId, $categoryId, $now);
                    $this->upsertSupplierProduct($id, $row, $now);
                    $stats['created']++;
                    $this->line(sprintf('  <fg=cyan>created</> [%s] %s', $row['article'], $row['name']));
                } else {
                    $stats['skipped']++;
                    $this->line(sprintf('  <fg=yellow>no match</> [%s] %s', $row['article'], $row['name']));
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->error(sprintf('  error [%s] %s: %s', $row['article'] ?? '?', $row['name'] ?? '?', $e->getMessage()));
            }
        }

        // Mark sync completed
        DB::table('supplier_syncs')->where('id', $this->syncId)->update([
            'last_status'    => 'success',
            'last_synced_at' => $now,
            'updated_at'     => $now,
        ]);

        $this->table(['metric', 'count'], array_map(
            fn ($k, $v) => [$k, $v],
            array_keys($stats), $stats
        ));

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    // ── CSV download / parse ──────────────────────────────────────────────────────

    private function resolveCsv(): string
    {
        $file = $this->option('price-file');
        if ($file) {
            if (! file_exists($file)) {
                throw new \RuntimeException("File not found: {$file}");
            }
            return $file;
        }

        $url  = $this->option('sheet-url') ?: self::DEFAULT_SHEET_URL;
        $path = storage_path('app/' . self::CACHE_PATH);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        $ctx = stream_context_create([
            'http' => [
                'method'         => 'GET',
                'timeout'        => 60,
                'follow_location'=> 1,
                'max_redirects'  => 10,
                'header'         => "User-Agent: Mozilla/5.0 (compatible; KotlovBot/1.0)\r\n",
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);

        $content = @file_get_contents($url, false, $ctx);
        if ($content === false || strlen($content) < 100) {
            if (file_exists($path)) {
                $this->warn('Download failed; using cached file.');
                return $path;
            }
            throw new \RuntimeException('Failed to download price list and no cache exists.');
        }

        file_put_contents($path, $content);
        $this->line('Downloaded price list: ' . $path);
        return $path;
    }

    /** @return array<int,array<string,mixed>> */
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new \RuntimeException("Cannot open CSV: {$path}");
        }

        $rows           = [];
        $currentSection = '';
        $currentBrand   = 'Житомир';
        $currentCat     = self::DEFAULT_CATEGORY;
        $lineNum        = 0;

        while (($cols = fgetcsv($handle)) !== false) {
            $lineNum++;
            if (count($cols) < 2) {
                continue;
            }

            $col0 = trim((string) ($cols[0] ?? ''));
            $col1 = trim((string) ($cols[1] ?? ''));
            $col2 = trim((string) ($cols[2] ?? ''));
            $col3 = trim((string) ($cols[3] ?? ''));
            $col4 = trim((string) ($cols[4] ?? ''));
            $col5 = trim((string) ($cols[5] ?? ''));

            // Skip header rows and ИТОГО
            if ($lineNum <= 2) {
                continue;
            }
            if ($col0 === '' || mb_stripos($col0, 'итого') === 0 || mb_stripos($col0, 'наименование') === 0) {
                continue;
            }

            // Section header: col1 is empty (or unit column) and col4 (price) is empty
            if ($col1 === '' && $col4 === '') {
                $currentSection = $col0;
                [$currentBrand, $currentCat] = $this->classifySection($col0);
                continue;
            }

            // Also detect plain section headers like "ДАТЧИК УГАРНОГО ГАЗА", "ЦИРКУЛЯЦИОННЫЙ НАСОС"
            if ($col1 === '' && is_numeric($col4) === false && $col4 !== '') {
                // Could be a section with price in wrong column — skip
                continue;
            }

            // Product row: col1 == "шт"
            if (mb_strtolower($col1) !== 'шт') {
                continue;
            }

            $costByn   = $this->parseMoney($col4);
            $retailByn = $this->parseMoney($col5);

            if ($costByn === null || $costByn <= 0) {
                continue;
            }

            $qty = (int) $col2 + (int) $col3;

            $rows[] = [
                'name'             => $col0,
                'article'          => $this->extractArticle($col0, $currentSection),
                'qty'              => $qty,
                'cost_byn'         => $costByn,
                'retail_byn'       => $retailByn,
                'brand'            => $currentBrand,
                'section'          => $currentSection,
                'section_category' => $currentCat,
            ];
        }

        fclose($handle);
        return $rows;
    }

    /** @return array{string, int} [brandName, categoryId] */
    private function classifySection(string $section): array
    {
        $lower = mb_strtolower($section);

        $brand = 'Житомир';
        foreach (self::SECTION_BRAND_MAP as $needle => $b) {
            if (str_contains($lower, $needle)) {
                $brand = $b;
                break;
            }
        }

        $cat = self::DEFAULT_CATEGORY;
        foreach (self::SECTION_CATEGORY_MAP as $needle => $catId) {
            if (str_contains($lower, $needle)) {
                $cat = $catId;
                break;
            }
        }

        return [$brand, $cat];
    }

    private function extractArticle(string $name, string $section = ''): string
    {
        // КНС-2, КНС-10 etc (convectors)
        if (preg_match('/^(КНС-\d+)$/u', $name, $m)) {
            return $m[1];
        }

        // Житомир-9 комби: "КС-Г-010СН/АОТВ-10" — include both parts
        if (preg_match('/(КС-Г[В]?-\d+[А-Яа-я]*\/А[ОТ][А-Яа-я]*-\d+)/u', $name, $m)) {
            return $m[1];
        }

        // "Котел Ж-3 КС-Г-007СН" → "Ж3-КС-Г-007СН"
        if (preg_match('/Ж-(\d+)\s+(КС-Г[В]?-\d+[А-Яа-я]*)/u', $name, $m)) {
            return 'Ж' . $m[1] . '-' . $m[2];
        }

        // "Котел Житомир-Турбо КС-Г-10СН" → "ТУРБО-КС-Г-10СН"
        if (preg_match('/Турбо\s+(КС-Г[В]?-\d+[А-Яа-я]*)/iu', $name, $m)) {
            return 'ТУРБО-' . $m[1];
        }

        // Remaining КС-Г patterns
        if (preg_match('/(КС-Г[В]?-\d+[А-Яа-я]*)/u', $name, $m)) {
            return $m[1];
        }

        // "Котел АОГВ-7СН" / "Котел АДГВ-10СН"
        if (preg_match('/(А[ОД]ГВ-\d+[А-Яа-я]*)/u', $name, $m)) {
            return $m[1];
        }

        // "Котел Житомир АОТВ-12 ДВЕ ДВЕРИ" / АКТВ
        if (preg_match('/(А[ОК]ТВ-\d+[А-Яа-я]*)/u', $name, $m)) {
            return $m[1];
        }

        // "Котел Житомир-14М ТРИ ДВЕРИ" → "ЖИТОМИР-14М"
        if (preg_match('/Житомир-(\d+[А-Яа-яA-Za-z]*)/iu', $name, $m)) {
            return 'ЖИТОМИР-' . mb_strtoupper($m[1]);
        }

        // GKB насосы "GKB GT 25/4-130"
        if (preg_match('/GKB\s+(GT[\s\S]+)/u', $name, $m)) {
            return 'GKB-GT-' . preg_replace('/\s+/', '', $m[1]);
        }

        // GKB датчики "GKB CO999"
        if (preg_match('/GKB\s+([A-Za-z0-9]+)/u', $name, $m)) {
            return 'GKB-' . $m[1];
        }

        // ВПГ series
        if (preg_match('/(ВПГ-\d+[А-Яа-яA-Za-z]*)/u', $name, $m)) {
            return $m[1];
        }

        // Fallback: take first word-like token
        $words = preg_split('/\s+/u', $name);
        return mb_strtoupper((string) ($words[0] ?? substr($name, 0, 20)));
    }

    // ── Dry-run display ──────────────────────────────────────────────────────────

    private function showDryRun(array $rows): int
    {
        $table = [];
        foreach ($rows as $r) {
            [$brand, $catId] = [$r['brand'], $r['section_category']];
            $product = null;
            $brandId = DB::table('brands')->whereRaw('LOWER(name) = ?', [mb_strtolower($brand)])->value('id');
            if ($brandId) {
                $product = $this->findProduct($r, (int) $brandId);
            }

            $table[] = [
                $r['article'],
                mb_substr($r['name'], 0, 40),
                $brand,
                $catId,
                $r['qty'],
                number_format($r['cost_byn'], 2, '.', ''),
                number_format($r['retail_byn'] ?? 0, 2, '.', ''),
                $product ? "#{$product->id} {$product->sku}" : '—',
            ];
        }

        $this->table(
            ['Article', 'Name', 'Brand', 'Cat', 'Qty', 'Cost BYN', 'Retail BYN', 'Match'],
            $table
        );

        $matched = count(array_filter($rows, function ($r) {
            $brandId = DB::table('brands')->whereRaw('LOWER(name) = ?', [mb_strtolower($r['brand'])])->value('id');
            return $brandId && $this->findProduct($r, (int) $brandId) !== null;
        }));

        $this->info(sprintf('%d rows total, %d matched, %d new', count($rows), $matched, count($rows) - $matched));
        return self::SUCCESS;
    }

    // ── Product matching ─────────────────────────────────────────────────────────

    private function findProduct(array $row, int $brandId): ?object
    {
        $article = $row['article'];

        // 1. Match by existing supplier_products article for this supplier
        if ($this->supplierId > 0) {
            $sp = DB::table('supplier_products as sp')
                ->join('products as p', 'p.id', '=', 'sp.product_id')
                ->where('sp.supplier_id', $this->supplierId)
                ->where('sp.supplier_article', $article)
                ->where('p.brand_id', $brandId)
                ->select('p.*')
                ->first();
            if ($sp) {
                return $sp;
            }
        }

        // 2. Match by specs["Артикул"] in products of this brand
        $bySpec = DB::table('products')
            ->where('brand_id', $brandId)
            ->where('is_archived', false)
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(specs, '$.\"Артикул\"')) = ?", [$article])
            ->first();
        if ($bySpec) {
            return $bySpec;
        }

        // 3. Fuzzy name match — strip common prefixes and compare
        $needle = $this->normalizeModel($row['name']);
        $products = DB::table('products')
            ->where('brand_id', $brandId)
            ->where(fn ($q) => $q->where('is_archived', false)->orWhereNull('is_archived'))
            ->get(['id', 'sku', 'name', 'specs', 'price']);

        $best = null;
        $bestScore = 0;
        foreach ($products as $p) {
            $score = $this->similarity($needle, $this->normalizeModel($p->name));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $p;
            }
        }

        return $bestScore >= 85 ? $best : null;
    }

    private function normalizeModel(string $name): string
    {
        $name = mb_strtolower($name);
        // Strip word-type descriptors BEFORE й→и so str_replace hits them correctly
        foreach (['твердотопливный', 'газовый', 'отопительный', 'настенный', 'напольный', 'универсальный'] as $w) {
            $name = str_replace($w . ' ', '', $name);
        }
        // Strip leading noun/type words (include both ё and е variants)
        foreach (['котел ', 'котёл ', 'аппарат водонагр ', 'датчик ', 'циркуляционный насос ', 'печь '] as $p) {
            while (str_starts_with($name, $p)) {
                $name = substr($name, strlen($p));
            }
        }
        // Normalise letters AFTER word removal so й→и doesn't break matches above
        $name = str_replace(['ё', 'й', '(в)'], ['е', 'и', ''], $name);
        // Strip series prefix "ж-3 ", "ж-10 "
        $name = preg_replace('/ж-\d+\s+/u', '', $name) ?? $name;
        // Strip brand
        $name = preg_replace('/житомир[-–\s]?\S*\s*/iu', '', $name) ?? $name;
        // Strip trailing descriptors
        $name = preg_replace('/\s+(одноконтурный|двухконтурный|три двери|две двери|дымоходная|без батареек|турбированная|закрытая камера|в комплекте).*$/iu', '', $name) ?? $name;
        // Normalize separators
        $name = preg_replace('/[\s\/]+/u', ' ', $name);
        return trim($name);
    }

    private function similarity(string $a, string $b): int
    {
        if ($a === $b) {
            return 100;
        }
        if ($a === '' || $b === '') {
            return 0;
        }
        similar_text($a, $b, $pct);
        return (int) round($pct);
    }

    // ── Write helpers ─────────────────────────────────────────────────────────────

    private function upsertSupplierProduct(int $productId, array $row, $now): bool
    {
        $existing = DB::table('supplier_products')
            ->where('supplier_id', $this->supplierId)
            ->where('product_id', $productId)
            ->first();

        $costByn  = $row['cost_byn'];
        $inStock  = $row['qty'] > 0;
        $stockQty = $row['qty'];

        if ($existing) {
            $changed = abs((float) $existing->price_byn - $costByn) > 0.01
                || (int) $existing->stock_quantity !== $stockQty
                || (bool) $existing->in_stock !== $inStock;

            DB::table('supplier_products')->where('id', $existing->id)->update([
                'price'             => $costByn,
                'currency'          => 'BYN',
                'currency_rate'     => 1.0,
                'price_byn'         => $costByn,
                'in_stock'          => $inStock,
                'stock_quantity'    => $stockQty,
                'stock_status'      => $inStock ? 'in_stock' : 'out_of_stock',
                'supplier_article'  => $row['article'],
                'supplier_name'     => $row['name'],
                'last_synced_at'    => $now,
                'last_stock_synced_at' => $now,
                'updated_at'        => $now,
            ]);

            return $changed;
        }

        DB::table('supplier_products')->insert([
            'supplier_id'          => $this->supplierId,
            'supplier_sync_id'     => $this->syncId,
            'product_id'           => $productId,
            'product_sku'          => DB::table('products')->where('id', $productId)->value('sku'),
            'supplier_article'     => $row['article'],
            'supplier_name'        => $row['name'],
            'source_url'           => self::SOURCE_URL,
            'price'                => $costByn,
            'currency'             => 'BYN',
            'currency_rate'        => 1.0,
            'price_byn'            => $costByn,
            'in_stock'             => $inStock,
            'stock_quantity'       => $stockQty,
            'stock_status'         => $inStock ? 'in_stock' : 'out_of_stock',
            'match_status'         => 'auto',
            'match_confidence'     => '90',
            'last_synced_at'       => $now,
            'last_stock_synced_at' => $now,
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);

        return true;
    }

    private function updateProductPrice(int $productId, array $row, $now): void
    {
        if (($row['retail_byn'] ?? null) === null || $row['retail_byn'] <= 0) {
            return;
        }

        $retailByn = $row['retail_byn'];

        DB::table('products')->where('id', $productId)->update([
            'price'      => $retailByn,
            'in_stock'   => $row['qty'] > 0,
            'updated_at' => $now,
        ]);
    }

    private function createProduct(array $row, int $brandId, int $categoryId, $now): int
    {
        $retailByn = $row['retail_byn'] ?? 0;
        $costByn   = $row['cost_byn'];
        $name      = $this->buildProductName($row);
        $sku       = $this->nextKotlovSku();
        $inStock   = $row['qty'] > 0;

        $id = (int) DB::table('products')->insertGetId([
            'category_id'         => $categoryId,
            'brand_id'            => $brandId,
            'name'                => $name,
            'slug'                => $this->uniqueSlug($name),
            'h1'                  => $name,
            'sku'                 => $sku,
            'price'               => $retailByn > 0 ? $retailByn : $costByn * 1.3,
            'currency'            => 'BYN',
            'images'              => json_encode([]),
            'specs'               => json_encode(['Артикул' => $row['article']], JSON_UNESCAPED_UNICODE),
            'unit'                => 'шт',
            'is_active'           => true,
            'is_archived'         => false,
            'in_stock'            => $inStock,
            'availability_status' => $inStock ? Product::AVAILABILITY_IN_STOCK : Product::AVAILABILITY_CHECK,
            'stock_qty'           => $row['qty'],
            'is_featured'         => false,
            'is_new'              => true,
            'is_sale'             => false,
            'sort_order'          => 0,
            'rating'              => 0,
            'reviews_count'       => 0,
            'views_count'         => 0,
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);

        return $id;
    }

    private function buildProductName(array $row): string
    {
        $brand = $row['brand'];
        $name  = $row['name'];

        // Already has brand in it
        if (mb_stripos($name, $brand) !== false || mb_stripos($name, 'житомир') !== false) {
            return $name;
        }

        return $brand . ' ' . $name;
    }

    // ── Supplier / sync bootstrap ─────────────────────────────────────────────────

    private function ensureSupplier(): int
    {
        $existing = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->first();
        if ($existing) {
            return (int) $existing->id;
        }

        $now = now();
        return (int) DB::table('suppliers')->insertGetId([
            'code'       => self::SUPPLIER_CODE,
            'name'       => self::SUPPLIER_NAME,
            'is_active'  => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function ensureSync(): int
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('supplier_syncs')) {
            return 0;
        }
        $now = now();
        DB::table('supplier_syncs')->updateOrInsert(
            ['key' => self::SYNC_KEY],
            ['name' => self::SUPPLIER_NAME, 'code' => self::SUPPLIER_CODE,
             'title' => 'ГазКотелБел: цены и наличие',
             'last_run_at' => $now, 'updated_at' => $now, 'created_at' => $now]
        );
        return (int) DB::table('supplier_syncs')->where('key', self::SYNC_KEY)->value('id');
    }

    private function ensureBrand(string $name): int
    {
        if (isset($this->brandCache[$name])) {
            return $this->brandCache[$name];
        }

        $existing = DB::table('brands')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first(['id']);

        if ($existing) {
            return $this->brandCache[$name] = (int) $existing->id;
        }

        $now  = now();
        $slug = Str::slug($name) ?: mb_strtolower($name);
        $id   = (int) DB::table('brands')->insertGetId([
            'name'       => $name,
            'slug'       => $slug,
            'is_active'  => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->info("  Created brand: {$name} (id {$id})");
        return $this->brandCache[$name] = $id;
    }

    private function resolveCategory(int $id): int
    {
        if (! isset($this->categoryExists[$id])) {
            $this->categoryExists[$id] = DB::table('categories')->where('id', $id)->exists();
        }

        if ($this->categoryExists[$id]) {
            return $id;
        }

        // If category 304 (Газовые конвекторы) doesn't exist, fall back to 281 Электрические конвекторы parent, or create it
        if ($id === 304) {
            return $this->ensureGasConvectorCategory();
        }

        return self::DEFAULT_CATEGORY;
    }

    private function ensureGasConvectorCategory(): int
    {
        $existing = DB::table('categories')->where('slug', 'gazovye-konvektory')->first(['id']);
        if ($existing) {
            $this->categoryExists[304] = true;
            return (int) $existing->id;
        }

        $now = now();
        $id  = (int) DB::table('categories')->insertGetId([
            'parent_id'  => 0,
            'name'       => 'Газовые конвекторы',
            'slug'       => 'gazovye-konvektory',
            'is_active'  => true,
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->info("  Created category: Газовые конвекторы (id {$id})");
        $this->categoryExists[304] = true;
        return $id;
    }

    // ── Utilities ─────────────────────────────────────────────────────────────────

    private function parseMoney(string $value): ?float
    {
        $value = trim(str_replace([' ', "\xc2\xa0", ','], ['', '', '.'], $value));
        return is_numeric($value) && (float) $value > 0 ? round((float) $value, 2) : null;
    }

    private function nextKotlovSku(): string
    {
        $max = DB::table('products')
            ->where('sku', 'like', 'KOTLOV-%')
            ->pluck('sku')
            ->map(fn ($s) => preg_match('/^KOTLOV-(\d+)$/', (string) $s, $m) ? (int) $m[1] : 0)
            ->max() ?? 0;

        $next = $max + 1;
        do {
            $sku = sprintf('KOTLOV-%06d', $next++);
        } while (DB::table('products')->where('sku', $sku)->exists());

        return $sku;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $i    = 2;
        while (DB::table('products')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}

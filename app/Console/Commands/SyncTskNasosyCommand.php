<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Synchronise ТСК Насосы (aqualider.by) prices & stock with KOTLOV.
 *
 * Reads the supplier Google Sheet, links rows to existing products and (with
 * --create-new) creates missing ones. Modeled on supplier:sync-rusklimat.
 *
 *   php artisan supplier:sync-tsk-nasosy --dry-run
 *   php artisan supplier:sync-tsk-nasosy --apply --create-new
 *
 * Pricing: «Опт 1 с НДС» = our purchase price → supplier_products.price/price_byn.
 * «МРЦ с НДС» = recommended retail → products.price for newly created products.
 * Stock «под заказ …» → preorder (supplier_products.in_stock = false).
 */
class SyncTskNasosyCommand extends Command
{
    protected $signature = 'supplier:sync-tsk-nasosy
        {--dry-run : Preview, write nothing}
        {--apply : Write changes}
        {--limit= : Process only the first N data rows}
        {--stock-file= : Local CSV/XLSX path (skips Google Sheet download)}
        {--sheet-url= : Override the default Google Sheet URL}
        {--create-new : Create products for rows with no match}';

    protected $description = 'Sync ТСК Насосы prices & stock from Google Sheets into supplier_products.';

    private const SUPPLIER_CODE = 'tsk_nasosy';
    private const SUPPLIER_NAME = 'ТСК Насосы';
    private const SYNC_KEY      = 'tsk_nasosy_stock';
    private const SOURCE_URL    = 'https://aqualider.by/';
    private const DEFAULT_SHEET_URL = 'https://docs.google.com/spreadsheets/d/1NlqXqVky2cDDAELEEKKAO0e07mpZjpkhLOQN13YWiuU/edit';
    private const SHEET_NAME        = 'Одним листом'; // consolidated data tab (gviz by name)
    private const SHEET_CACHE_PATH  = 'supplier-cache/tsk-nasosy.csv';

    /**
     * Fixed column layout of the «Одним листом» tab — the CSV export has NO text
     * header row (column captions are frozen labels), so we map by position.
     */
    private const COLS = [
        'article'      => 0,  // Артикул
        'brand'        => 2,  // Бренд
        'name'         => 3,  // Модель
        'price'        => 13, // Опт 1 с НДС (закупка)
        'retail_price' => 15, // МРЦ с НДС
        'status'       => 16, // Наличие в Минске
    ];

    /** name keyword → KOTLOV category_id (for --create-new). */
    private const CATEGORY_MAP = [
        'эцв'          => 272, // скважинные
        'скважин'      => 272,
        'циркуляц'     => 60,
        'насосная станция' => 251,
        'станци'       => 251,
        'дренаж'       => 265,
        'фекаль'       => 265,
        'канализац'    => 265,
        'повышения давления' => 60,
        'насос'        => 272, // generic pump fallback
    ];

    private array $indexBySupplierArticle = [];
    private array $indexBySku = [];
    private array $indexByBrandModel = [];
    private array $brandById = [];
    private array $brandByName = [];

    public function handle(): int
    {
        $apply     = (bool) $this->option('apply') && ! $this->option('dry-run');
        $createNew = (bool) $this->option('create-new');
        $limit     = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: database will be updated.</>'
            : '<fg=yellow;options=bold>DRY RUN: database will not be changed.</>');

        // ── Resolve file ──────────────────────────────────────────────────────────
        $file = $this->option('stock-file');
        if ($file !== null) {
            if (! file_exists($file)) {
                $this->error("File not found: {$file}");
                return self::FAILURE;
            }
        } else {
            try {
                $file = $this->downloadGoogleSheet($this->option('sheet-url') ?: self::DEFAULT_SHEET_URL);
            } catch (\RuntimeException $e) {
                $this->error($e->getMessage() === 'private'
                    ? 'Google Sheet недоступен публично. Открой доступ «Anyone with the link» или используй --stock-file=.'
                    : 'Download failed: ' . $e->getMessage());
                return self::FAILURE;
            }
        }

        try {
            $rows = $this->parseFile($file);
        } catch (\Throwable $e) {
            $this->error('Parse failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info(sprintf('Parsed %d data rows from %s', count($rows), basename($file)));
        if ($limit) {
            $rows = array_slice($rows, 0, $limit);
        }
        if ($rows === []) {
            $this->warn('No data rows detected — check the sheet structure / columns.');
            return self::SUCCESS;
        }

        $this->buildIndex();
        $classified = array_map(fn ($r) => $this->classify($r), $rows);

        return $apply
            ? $this->applyChanges($classified, $createNew)
            : $this->showDryRun($classified);
    }

    // ── Google Sheet download (same approach as sync-rusklimat) ───────────────────

    private function downloadGoogleSheet(string $url): string
    {
        if (! preg_match('#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $url, $m)) {
            throw new \RuntimeException('Invalid Google Sheets URL.');
        }
        $id = $m[1];
        // Use gviz by sheet NAME — the data lives on «Одним листом», not the default gid.
        $export = "https://docs.google.com/spreadsheets/d/{$id}/gviz/tq?tqx=out:csv&sheet=" . rawurlencode(self::SHEET_NAME);

        $ctx = stream_context_create([
            'http' => ['method' => 'GET', 'timeout' => 45, 'follow_location' => 1, 'max_redirects' => 10,
                       'header' => "User-Agent: Mozilla/5.0 (compatible; KotlovBot/1.0)\r\nAccept: text/csv,*/*"],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $content = @file_get_contents($export, false, $ctx);
        if ($content === false || trim($content) === '') {
            throw new \RuntimeException('Could not fetch the sheet.');
        }
        if (str_starts_with(ltrim($content), '<') || stripos($content, '<html') !== false) {
            throw new \RuntimeException('private');
        }
        $path = storage_path('app/' . self::SHEET_CACHE_PATH);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, $content);
        $this->info('Downloaded to storage/app/' . self::SHEET_CACHE_PATH);
        return $path;
    }

    // ── Parse ─────────────────────────────────────────────────────────────────────

    private function parseFile(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'csv') {
            $h = fopen($path, 'r');
            // Detect delimiter over a chunk (first line may be a multiline quoted title).
            $sample = (string) fread($h, 16384); rewind($h);
            $delim = substr_count($sample, ';') > substr_count($sample, ',') ? ';' : ',';
            $raw = [];
            while (($r = fgetcsv($h, 0, $delim)) !== false) {
                $raw[] = array_map(fn ($v) => $this->clean((string) $v), $r);
            }
            fclose($h);
            return $this->normalise($raw);
        }
        $sheet = IOFactory::load($path)->getActiveSheet();
        $raw = array_map(fn ($r) => array_map(fn ($v) => $this->clean((string) ($v ?? '')), $r),
            $sheet->toArray(null, true, true, false));
        return $this->normalise($raw);
    }

    private function normalise(array $raw): array
    {
        // Fixed column layout (see self::COLS) — the export has no text header,
        // so a data row is detected by a numeric article + numeric Опт1 price.
        $this->detectedColumns = self::COLS;
        $c = self::COLS;

        $items = [];
        $seen  = [];
        foreach ($raw as $row) {
            $article = trim((string) ($row[$c['article']] ?? ''));
            if (! preg_match('/^\d{3,}$/u', $article)) {
                continue; // not a data row (title/section/brand banner/empty)
            }
            $price = $this->num((string) ($row[$c['price']] ?? ''));
            if ($price === null) {
                continue;
            }
            $norm = $this->normArticle($article);
            if (isset($seen[$norm])) {
                continue;
            }
            $seen[$norm] = true;

            $items[] = [
                'article'      => $article,
                'norm_article' => $norm,
                'brand'        => trim((string) ($row[$c['brand']] ?? '')),
                'name'         => trim((string) ($row[$c['name']] ?? '')),
                'price'        => $price,
                'retail_price' => $this->num((string) ($row[$c['retail_price']] ?? '')),
                'status_text'  => trim((string) ($row[$c['status']] ?? '')),
            ];
        }
        return $items;
    }

    /** @var array<string,int> */
    private array $detectedColumns = [];

    private function detectColumns(array $header): array
    {
        $patterns = [
            'article'      => ['артикул'],
            'brand'        => ['бренд', 'производитель', 'марка'],
            'name'         => ['модель', 'наименование', 'название', 'товар'],
            'price'        => ['опт 1', 'опт1'],
            'retail_price' => ['мрц', 'ррц', 'рекомендуем', 'розниц'],
            'status'       => ['наличие'],
        ];
        $candidates = [];
        foreach ($header as $idx => $cell) {
            $low = mb_strtolower(trim($cell));
            if ($low === '') {
                continue;
            }
            foreach ($patterns as $key => $kws) {
                foreach ($kws as $prio => $kw) {
                    if ($low === $kw || str_starts_with($low, $kw)) {
                        if (! isset($candidates[$key]) || $prio < $candidates[$key][0]) {
                            $candidates[$key] = [$prio, $idx];
                        }
                        break;
                    }
                }
            }
        }
        $map = [];
        foreach ($candidates as $k => [, $idx]) {
            $map[$k] = $idx;
        }
        return $map;
    }

    private function col(array $row, array $map, string $key): string
    {
        return isset($map[$key]) ? trim((string) ($row[$map[$key]] ?? '')) : '';
    }

    // ── Stock ───────────────────────────────────────────────────────────────────

    /** @return array{status:string,in_stock:bool,delivery_days:?int} */
    private function stock(string $text): array
    {
        $t = mb_strtolower(trim($text));
        if ($t === '') {
            return ['status' => 'unknown', 'in_stock' => false, 'delivery_days' => null];
        }
        if (str_contains($t, 'заказ')) {
            return ['status' => 'preorder', 'in_stock' => false,
                    'delivery_days' => str_contains($t, 'мес') ? 60 : (str_contains($t, 'нед') ? 10 : 14)];
        }
        if (str_contains($t, 'нет') || str_contains($t, 'отсут') || str_contains($t, 'снят')) {
            return ['status' => 'out_of_stock', 'in_stock' => false, 'delivery_days' => null];
        }
        if (str_contains($t, 'налич') || str_contains($t, 'склад') || str_contains($t, 'есть')) {
            return ['status' => 'in_stock', 'in_stock' => true, 'delivery_days' => 0];
        }
        return ['status' => 'unknown', 'in_stock' => false, 'delivery_days' => null];
    }

    // ── Index & matching ──────────────────────────────────────────────────────────

    private function buildIndex(): void
    {
        DB::table('brands')->get(['id', 'name'])->each(function ($b) {
            $this->brandById[(int) $b->id] = $b->name;
            $this->brandByName[mb_strtolower($b->name)] = (int) $b->id;
        });

        $sid = $this->supplierId();
        if ($sid > 0) {
            DB::table('supplier_products')->where('supplier_id', $sid)->whereNotNull('product_id')
                ->get(['supplier_article', 'product_id'])
                ->each(fn ($sp) => $this->indexBySupplierArticle[$this->normArticle($sp->supplier_article)] = (int) $sp->product_id);
        }

        DB::table('products')->where('is_archived', false)->get(['id', 'sku', 'name', 'brand_id'])
            ->each(function ($p) {
                $this->indexBySku[mb_strtoupper(trim((string) $p->sku))] = (int) $p->id;
                $bid = (int) $p->brand_id;
                if ($bid > 0) {
                    $model = $this->model((string) $p->name, $this->brandById[$bid] ?? '');
                    if ($model !== '') {
                        $this->indexByBrandModel[$bid][$model] = (int) $p->id;
                    }
                }
            });
    }

    private function classify(array $row): array
    {
        $brandId = $this->resolveBrand($row['brand']);
        $stock   = $this->stock($row['status_text']);
        $match   = $this->match($row, $brandId);
        $catId   = $this->resolveCategory($row['name']);

        $action = match (true) {
            $match !== null                         => 'matched',
            $brandId === null                       => 'brand_missing',
            $catId === null                         => 'category_missing',
            $stock['status'] === 'out_of_stock'     => 'skipped_out_of_stock',
            default                                 => 'create_candidate',
        };

        return $row + [
            'matched_product_id' => $match['product_id'] ?? null,
            'matched_sku'        => $match['sku'] ?? null,
            'confidence'         => $match['confidence'] ?? null,
            'resolved_brand_id'  => $brandId,
            'resolved_category_id' => $catId,
            'stock'              => $stock,
            'action'             => $action,
        ];
    }

    private function match(array $row, ?int $brandId): ?array
    {
        if (isset($this->indexBySupplierArticle[$row['norm_article']])) {
            $pid = $this->indexBySupplierArticle[$row['norm_article']];
            return ['product_id' => $pid, 'sku' => $this->sku($pid), 'confidence' => 'exact_supplier_article'];
        }
        $up = mb_strtoupper($row['norm_article']);
        if (isset($this->indexBySku[$up])) {
            return ['product_id' => $this->indexBySku[$up], 'sku' => $up, 'confidence' => 'exact_sku'];
        }
        if ($brandId !== null && ! empty($this->indexByBrandModel[$brandId])) {
            $model = $this->model($row['name'], $this->brandById[$brandId] ?? '');
            if ($model !== '' && isset($this->indexByBrandModel[$brandId][$model])) {
                $pid = $this->indexByBrandModel[$brandId][$model];
                return ['product_id' => $pid, 'sku' => $this->sku($pid), 'confidence' => 'brand_model'];
            }
        }
        return null;
    }

    private function resolveBrand(string $name): ?int
    {
        $key = mb_strtolower(trim($name));
        if ($key === '') {
            return null;
        }
        if (isset($this->brandByName[$key])) {
            return $this->brandByName[$key];
        }
        foreach ($this->brandByName as $n => $id) {
            if (str_starts_with($key, $n) || str_starts_with($n, $key)) {
                return $id;
            }
        }
        return null;
    }

    private function resolveCategory(string $name): ?int
    {
        $low = mb_strtolower($name);
        foreach (self::CATEGORY_MAP as $kw => $cat) {
            if (str_contains($low, $kw)) {
                return $cat;
            }
        }
        return null;
    }

    // ── Dry-run report (step 12) ──────────────────────────────────────────────────

    private function showDryRun(array $rows): int
    {
        $this->newLine();
        $this->info('Найденные колонки: ' . implode(', ', array_keys($this->detectedColumns)));

        $statuses = [];
        $actions  = [];
        foreach ($rows as $r) {
            $statuses[$r['stock']['status']] = ($statuses[$r['stock']['status']] ?? 0) + 1;
            $actions[$r['action']] = ($actions[$r['action']] ?? 0) + 1;
        }
        $live = ($actions['matched'] ?? 0) + ($actions['create_candidate'] ?? 0);
        $skipped = count($rows) - $live;

        $this->table(['метрика', 'кол-во'], [
            ['строк (данные)', count($rows)],
            ['живых (matched + create)', $live],
            ['пропущено (brand/cat/out/dup)', $skipped],
        ]);
        $this->info('Статусы наличия:');
        $this->table(['stock_status', 'кол-во'], array_map(fn ($k, $v) => [$k, $v], array_keys($statuses), array_values($statuses)));
        $this->info('Действия:');
        $this->table(['action', 'кол-во'], array_map(fn ($k, $v) => [$k, $v], array_keys($actions), array_values($actions)));

        // ── Покрытие брендов: что из прайса уже есть в каталоге ───────────────────
        $brands = [];
        foreach ($rows as $r) {
            $b = trim($r['brand']);
            if ($b === '') {
                continue;
            }
            $brands[$b]['rows'] = ($brands[$b]['rows'] ?? 0) + 1;
            $brands[$b]['resolved'] = $r['resolved_brand_id'] !== null;
        }
        ksort($brands);
        $this->newLine();
        $this->info('Бренды прайса (есть ли в каталоге):');
        $this->table(['бренд', 'строк', 'в каталоге?'],
            array_map(fn ($b, $i) => [$b, $i['rows'], $i['resolved'] ? 'да' : '<fg=yellow>НЕТ</>'], array_keys($brands), $brands));

        // ── Что уже есть локально в целевых категориях насосов ────────────────────
        $catNames = DB::table('categories')->whereIn('id', [272, 60, 251, 265])->pluck('name', 'id');
        $existing = DB::table('products')->where('is_archived', false)
            ->whereIn('category_id', [272, 60, 251, 265])
            ->select('category_id', DB::raw('count(*) as c'))->groupBy('category_id')->pluck('c', 'category_id');
        $this->info('Существующие активные товары в категориях насосов (с чем будем склеивать):');
        $this->table(['cat_id', 'категория', 'товаров в каталоге'],
            collect([272, 60, 251, 265])->map(fn ($c) => [$c, $catNames[$c] ?? '—', $existing[$c] ?? 0])->all());

        // ── Уверенность матчинга (как именно склеилось) ───────────────────────────
        $conf = [];
        foreach ($rows as $r) {
            if ($r['action'] === 'matched') {
                $conf[$r['confidence']] = ($conf[$r['confidence']] ?? 0) + 1;
            }
        }
        if ($conf !== []) {
            $this->info('Матчинг по уверенности:');
            $this->table(['confidence', 'кол-во'], array_map(fn ($k, $v) => [$k, $v], array_keys($conf), array_values($conf)));
        }

        $this->info('Примеры (10):');
        $this->table(
            ['article', 'brand', 'name', 'опт1', 'мрц', 'статус', 'in_stock', 'action', 'matched_sku'],
            array_map(fn ($r) => [
                mb_substr($r['article'], 0, 14), mb_substr($r['brand'], 0, 12), mb_substr($r['name'], 0, 30),
                number_format($r['price'], 2), $r['retail_price'] !== null ? number_format($r['retail_price'], 2) : '—',
                $r['stock']['status'], $r['stock']['in_stock'] ? 'да' : 'нет', $r['action'], $r['matched_sku'] ?? '—',
            ], array_slice($rows, 0, 10))
        );

        $this->newLine();
        $this->line('Запусти с <fg=green>--apply</> (и <fg=green>--create-new</> для новых).');
        return self::SUCCESS;
    }

    // ── Apply ───────────────────────────────────────────────────────────────────

    private function applyChanges(array $rows, bool $createNew): int
    {
        $now = now();
        $sid = $this->ensureSupplier($now);
        $syncId = $this->ensureSync($now);
        $stats = array_fill_keys(['matched', 'created', 'updated', 'brand_missing', 'category_missing', 'skipped', 'errors'], 0);

        foreach ($rows as $r) {
            if (in_array($r['action'], ['brand_missing', 'category_missing'], true)) {
                $stats[$r['action']]++;
                continue;
            }
            if ($r['action'] === 'skipped_out_of_stock') {
                $stats['skipped']++;
                continue;
            }
            try {
                if ($r['matched_product_id'] !== null) {
                    $this->upsertSupplierProduct($r, (int) $r['matched_product_id'], (string) $r['matched_sku'], $sid, $syncId, $now);
                    $stats['matched']++;
                } elseif ($createNew && $r['resolved_brand_id'] !== null && $r['resolved_category_id'] !== null) {
                    $pid = $this->createProduct($r, $now);
                    $sku = $this->sku($pid);
                    $this->upsertSupplierProduct($r, $pid, $sku, $sid, $syncId, $now);
                    $stats['created']++;
                    $this->line("[create] {$r['article']} → {$sku}");
                } else {
                    $stats['skipped']++;
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn("[error] {$r['article']}: " . $e->getMessage());
            }
        }

        // Mark supplier links absent for products no longer in the price (do NOT delete).
        $present = array_filter(array_map(fn ($r) => $r['norm_article'], $rows));
        $this->deactivateMissing($sid, $present, $now);

        $this->newLine();
        $this->table(['метрика', 'кол-во'], array_map(fn ($k, $v) => [$k, $v], array_keys($stats), array_values($stats)));
        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function upsertSupplierProduct(array $r, int $pid, string $sku, int $sid, ?int $syncId, $now): void
    {
        DB::table('supplier_products')->updateOrInsert(
            ['supplier_id' => $sid, 'supplier_article' => $r['norm_article']],
            [
                'supplier_article_normalized' => $r['norm_article'],
                'supplier_sync_id' => $syncId,
                'product_id'   => $pid,
                'product_sku'  => $sku,
                'supplier_name' => trim($r['brand'] . ' ' . $r['name']),
                'source_url'   => self::SOURCE_URL,
                'price'        => $r['price'],
                'currency'     => 'BYN',
                'currency_rate' => 1.0,
                'price_byn'    => $r['price'],
                'in_stock'     => $r['stock']['in_stock'],
                'stock_status' => $r['stock']['status'],
                'stock_text'   => $r['status_text'] !== '' ? $r['status_text'] : null,
                'delivery_days' => $r['stock']['delivery_days'],
                'match_status' => 'matched',
                'match_confidence' => $r['confidence'],
                'is_active'    => true,
                'raw'          => json_encode(['article' => $r['article'], 'brand' => $r['brand'], 'retail' => $r['retail_price']], JSON_UNESCAPED_UNICODE),
                'last_synced_at' => $now,
                'last_stock_synced_at' => $now,
                'updated_at'   => $now,
                'created_at'   => $now,
            ]
        );
    }

    private function createProduct(array $r, $now): int
    {
        $brand = $this->brandById[(int) $r['resolved_brand_id']] ?? '';
        $name  = trim($r['brand'] . ' ' . $r['name']);
        $name  = $name !== '' ? $name : $r['article'];
        return (int) DB::table('products')->insertGetId([
            'category_id' => (int) $r['resolved_category_id'],
            'brand_id'    => (int) $r['resolved_brand_id'],
            'name'        => $name,
            'h1'          => $name,
            'sku'         => $this->nextSku(),
            'slug'        => $this->uniqueSlug($name),
            'price'       => $r['retail_price'] ?? 0,   // retail (МРЦ); markup logic handled by project rules
            'currency'    => 'BYN',
            'images'      => json_encode([]),
            'specs'       => json_encode([]),
            'unit'        => 'шт',
            'is_active'   => true,
            'is_archived' => false,
            'in_stock'    => $r['stock']['in_stock'],
            'stock_qty'   => null,
            'is_new'      => true,
            'meta_title'  => $name . ' купить в %city%',
            'meta_description' => $name . ' — купить по выгодной цене в Беларуси.',
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
    }

    private function deactivateMissing(int $sid, array $presentArticles, $now): void
    {
        $present = array_values(array_unique($presentArticles));
        DB::table('supplier_products')
            ->where('supplier_id', $sid)
            ->when($present !== [], fn ($q) => $q->whereNotIn('supplier_article', $present))
            ->update(['is_active' => false, 'in_stock' => false, 'stock_status' => 'out_of_stock', 'updated_at' => $now]);
    }

    // ── Supplier / sync registration ──────────────────────────────────────────────

    private function ensureSupplier($now): int
    {
        $existing = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->first();
        if ($existing) {
            DB::table('suppliers')->where('id', $existing->id)->update([
                'name' => self::SUPPLIER_NAME, 'is_active' => true, 'updated_at' => $now,
            ]);
            return (int) $existing->id;
        }
        return (int) DB::table('suppliers')->insertGetId([
            'code' => self::SUPPLIER_CODE, 'name' => self::SUPPLIER_NAME, 'currency' => 'BYN',
            'currency_rate' => 1, 'contact' => self::SOURCE_URL,
            'notes' => 'Насосное оборудование (aqualider.by). Опт 1 = закупка. «Под заказ» = preorder.',
            'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function ensureSync($now): ?int
    {
        if (! Schema::hasTable('supplier_syncs')) {
            return null;
        }
        DB::table('supplier_syncs')->updateOrInsert(
            ['key' => self::SYNC_KEY],
            ['name' => self::SUPPLIER_NAME, 'code' => self::SUPPLIER_CODE, 'title' => 'ТСК Насосы: цены и наличие',
             'description' => 'Цены/наличие из Google Sheets. Опт 1 = закупка; «под заказ» = preorder.',
             'command' => 'supplier:sync-tsk-nasosy', 'source_url' => self::SOURCE_URL,
             'image_disk_path' => 'img/products/tsk-nasosy', 'is_active' => true,
             'last_run_at' => $now, 'updated_at' => $now, 'created_at' => $now]
        );
        return (int) DB::table('supplier_syncs')->where('key', self::SYNC_KEY)->value('id');
    }

    private function supplierId(): int
    {
        static $id = null;
        $id ??= (int) (DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id') ?? 0);
        return $id;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────────

    private function sku(int $pid): string
    {
        return (string) DB::table('products')->where('id', $pid)->value('sku');
    }

    private function nextSku(): string
    {
        $max = DB::table('products')->where('sku', 'like', 'KOTLOV-%')->pluck('sku')
            ->map(fn ($s) => preg_match('/^KOTLOV-(\d+)$/', (string) $s, $m) ? (int) $m[1] : 0)->max() ?? 0;
        $next = max(0, (int) $max) + 1;
        do {
            $sku = sprintf('KOTLOV-%06d', $next++);
        } while (DB::table('products')->where('sku', $sku)->exists());
        return $sku;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'tsk-nasos';
        $slug = $base; $i = 2;
        while (DB::table('products')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    private function normArticle(string $s): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtoupper(trim($s))) ?? $s);
    }

    private function model(string $productName, string $brand): string
    {
        $n = mb_strtoupper($productName);
        if ($brand !== '') {
            $n = preg_replace('/' . preg_quote(mb_strtoupper($brand), '/') . '/u', '', $n) ?? $n;
        }
        $n = preg_replace('/[^А-ЯЁA-Z0-9\-\/.]/u', ' ', $n) ?? $n;
        return trim(preg_replace('/\s+/u', ' ', $n) ?? $n);
    }

    private function num(string $v): ?float
    {
        if (trim($v) === '') {
            return null;
        }
        $clean = str_replace([' ', "\u{A0}", ','], ['', '', '.'], $v);
        if (! preg_match('/-?\d+(?:\.\d+)?/', $clean, $m)) {
            return null;
        }
        return (float) $m[0];
    }

    private function clean(string $v): string
    {
        $v = html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $v = str_replace("\u{A0}", ' ', $v);
        return trim(preg_replace('/\s+/u', ' ', $v) ?? $v);
    }
}

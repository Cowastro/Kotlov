<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SyncThermostudioPricelistCommand extends Command
{
    protected $signature = 'supplier:sync-thermostudio-pricelist
        {--dry-run : Preview only}
        {--apply : Write supplier prices and stock}
        {--sheet-url= : Google Sheets URL}
        {--price-file= : Local XLSX file instead of downloading Google Sheet}
        {--brand=* : Process only selected brands, repeatable or comma-separated}
        {--available-only : Keep only rows that are in stock or expected soon}
        {--only-linked : Update only already linked Thermostudio supplier rows}
        {--create-new : Create products that do not match existing catalog items}
        {--sync-retail-prices : Update products.price from retail BYN}
        {--candidate-report= : Write create_candidate review CSV to a path}
        {--limit= : Process only N parsed rows after filters}
        {--offset=0 : Skip N parsed rows after filters}';

    protected $description = 'Audit and sync Thermostudio Google price list: supplier prices, retail prices and stock.';

    private const SUPPLIER_CODE = 'thermostudio';
    private const SUPPLIER_NAME = 'Термостудия';
    private const SYNC_KEY = 'thermostudio_pricelist';
    private const SOURCE_URL = 'https://docs.google.com/spreadsheets/d/1cQEYeQIDjAyHG-_r-ZqL0v0zsSj9hZ5m9yDpyTBBqm0/edit';
    private const DEFAULT_SHEET_URL = 'https://docs.google.com/spreadsheets/d/1cQEYeQIDjAyHG-_r-ZqL0v0zsSj9hZ5m9yDpyTBBqm0/edit?gid=1298422136#gid=1298422136';
    private const CACHE_PATH = 'supplier-cache/thermostudio-pricelist.xlsx';

    private const DEFAULT_BRANDS = [
        'Ariston',
        'Bosch',
        'Buderus',
        'Candy',
        'Ferroli',
        'Fondital',
        'Kermi',
        'Kospel',
        'Kiturami',
        'Navien',
        'Protherm',
        'SAS',
        'Superlux',
        'TEC Line',
        'Teknix',
        'Tesy',
        'Auraton',
        'Vaillant',
        'Viessmann',
        'Сигнал',
    ];

    private const BRAND_ALIASES = [
        'abse velis pro inox power' => 'Ariston',
        'abse velis pro power' => 'Ariston',
        'abs velis pro inox r' => 'Ariston',
        'ariston' => 'Ariston',
        'ariston evn' => 'Ariston',
        'ariston эвн' => 'Ariston',
        'bosch' => 'Bosch',
        'buderus' => 'Buderus',
        'candy evn' => 'Candy',
        'candy эвн' => 'Candy',
        'candy' => 'Candy',
        'ferroli' => 'Ferroli',
        'fondital radiatory' => 'Fondital',
        'fondital радиаторы' => 'Fondital',
        'fondital' => 'Fondital',
        'kermi' => 'Kermi',
        'kermi fko' => 'Kermi',
        'kermi ftv' => 'Kermi',
        'kospel' => 'Kospel',
        'kiturami' => 'Kiturami',
        'navien' => 'Navien',
        'protherm' => 'Protherm',
        'sas' => 'SAS',
        'superlux rf' => 'Superlux',
        'superlux рф' => 'Superlux',
        'superlux' => 'Superlux',
        'tec line' => 'TEC Line',
        'tecline' => 'TEC Line',
        'teknix' => 'Teknix',
        'tesy' => 'Tesy',
        'auraton' => 'Auraton',
        'vaillant' => 'Vaillant',
        'viessmann' => 'Viessmann',
        'сигнал' => 'Сигнал',
    ];

    private const SECTION_WORDS = [
        'vitodens',
        'электрические котлы',
        'конденсационные котлы',
        'напольные котлы',
        'настенные газовые котлы',
        'напольные газовые котлы',
        'электро котлы',
        'газовые колонки',
        'радиатор биметаллический',
        'тип 11',
        'тип 12',
        'тип 22',
        'тип 33',
        'высота',
        'серия',
    ];

    /** @var array<string,int> */
    private array $brandByKey = [];

    /** @var array<int,string> */
    private array $brandNameById = [];

    /** @var array<string,int> */
    private array $categoryBySlug = [];

    /** @var array<string,int> */
    private array $indexBySupplierArticle = [];

    /** @var array<string,int> */
    private array $indexByProductName = [];

    /** @var array<string,int> */
    private array $indexByBrandModel = [];

    /** @var array<string,int> */
    private array $indexByModelSignature = [];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply') && ! (bool) $this->option('dry-run');
        $createNew = (bool) $this->option('create-new');

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: Thermostudio price list will write changes.</>'
            : '<fg=yellow;options=bold>DRY RUN: Thermostudio price list will preview only.</>');

        try {
            $rows = $this->loadRows();
        } catch (\Throwable $e) {
            $this->error('Price list load failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $brandFilter = $this->brandFilter();
        $availableOnly = (bool) $this->option('available-only');
        $rows = array_values(array_filter($rows, function (array $row) use ($brandFilter, $availableOnly) {
            if (! isset($brandFilter[$this->brandKey($row['brand'])])) {
                return false;
            }

            if ($availableOnly && ! in_array($row['stock_status'], ['in_stock', 'preorder'], true)) {
                return false;
            }

            return true;
        }));

        $offset = max(0, (int) $this->option('offset'));
        if ($offset > 0) {
            $rows = array_slice($rows, $offset);
        }

        $limit = $this->option('limit');
        if ($limit !== null && (int) $limit > 0) {
            $rows = array_slice($rows, 0, (int) $limit);
        }

        $this->buildIndexes();
        $classified = array_map(fn (array $row) => $this->classify($row), $rows);

        return $apply
            ? $this->applyRows($classified, $createNew)
            : $this->report($classified);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function loadRows(): array
    {
        $file = $this->option('price-file');
        if ($file) {
            $path = $this->absolutePath((string) $file);
        } else {
            $path = $this->downloadSheet($this->option('sheet-url') ?: self::DEFAULT_SHEET_URL);
        }

        $spreadsheet = IOFactory::load($path);
        $rows = [];

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $maxRow = $sheet->getHighestRow();
            $maxCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());
            $sheetTitle = $sheet->getTitle();
            if ($this->isSummarySheet($sheetTitle) && $spreadsheet->getSheetCount() > 1) {
                continue;
            }

            $currentBrand = $this->canonicalBrand($sheetTitle);
            $currentSection = null;

            for ($rowIndex = 1; $rowIndex <= $maxRow; $rowIndex++) {
                $cells = [];
                for ($col = 1; $col <= min($maxCol, 12); $col++) {
                    $name = Coordinate::stringFromColumnIndex($col);
                    $cell = $sheet->getCell($name . $rowIndex);
                    $value = $cell->getValue();
                    if (is_string($value) && preg_match('/^=\s*([0-9]+(?:[.,][0-9]+)?)\s*$/', $value, $match)) {
                        $value = $match[1];
                    }
                    $cells[$col - 1] = $this->clean((string) (is_numeric($value) ? $value : $cell->getFormattedValue()));
                }
                $cells = $this->trimLeadingEmptyCells($cells);

                if ($this->isEmptyOrFormulaErrorRow($cells)) {
                    continue;
                }

                if ($this->isHeaderRow($cells)) {
                    continue;
                }

                $marker = $this->rowMarker($cells);
                if ($marker !== null) {
                    if ($brand = $this->canonicalBrand($marker)) {
                        $currentBrand = $brand;
                        $currentSection = null;
                    } else {
                        $currentSection = $marker;
                    }
                    continue;
                }

                $item = $this->normaliseRow($cells, $rowIndex, $currentBrand, $currentSection, $sheetTitle);
                if ($item !== null) {
                    $rows[] = $item;
                }
            }
        }

        return $this->makeSupplierArticlesUnique($rows);
    }

    private function downloadSheet(string $sheetUrl): string
    {
        if (! preg_match('#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $sheetUrl, $match)) {
            throw new \RuntimeException('Invalid Google Sheets URL.');
        }

        $dir = storage_path('app/supplier-cache');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $path = storage_path('app/' . self::CACHE_PATH);
        $exportUrl = "https://docs.google.com/spreadsheets/d/{$match[1]}/export?format=xlsx";
        $this->line("Downloading Thermostudio Google Sheet: {$exportUrl}");
        $content = $this->fetch($exportUrl);
        if ($content === null || strlen($content) < 1024 || str_starts_with(ltrim($content), '<')) {
            throw new \RuntimeException('Unable to download readable XLSX from Google Sheets.');
        }

        file_put_contents($path, $content);

        return $path;
    }

    /**
     * @param array<int,string> $cells
     */
    private function normaliseRow(array $cells, int $sheetRow, ?string $brand, ?string $section, string $sheetTitle): ?array
    {
        if ($brand === null) {
            return null;
        }

        $article = $this->clean($cells[0] ?? '');
        $model = $this->clean($cells[1] ?? '');
        $stockIndex = $this->stockColumnIndex($cells);
        $stockText = $this->clean($cells[$stockIndex] ?? '');
        [$priceIndex, $retailIndex] = $this->priceColumnIndexes($cells, $stockIndex);
        $price = $this->money($cells[$priceIndex] ?? '');
        $retail = $this->money($cells[$retailIndex] ?? '');
        $description = $this->descriptionFromCells($cells, $priceIndex, $retailIndex, $stockIndex);

        if ($price === null && $retail === null) {
            return null;
        }

        if ($this->isZeroLike($model) && $description !== '' && ! $this->isZeroLike($description)) {
            $model = $description;
            $description = '';
        }

        if ($this->isZeroLike($article)) {
            $article = '';
        }

        if ($this->isZeroLike($model) || $model === '') {
            $model = $article;
        }

        $name = trim($model);
        if ($name === '' || $this->isHeaderLike($name)) {
            return null;
        }

        $stock = $this->stock($stockText);
        $rawArticle = $article !== '' ? $article : $brand . ' ' . $name;

        return [
            'sheet' => $sheetTitle,
            'sheet_row' => $sheetRow,
            'brand' => $brand,
            'section' => $section,
            'name' => $name,
            'description' => $description,
            'price_byn' => $price,
            'retail_byn' => $retail,
            'stock_text' => $stockText,
            'stock_quantity' => $stock['quantity'],
            'stock_status' => $stock['status'],
            'in_stock' => $stock['status'] === 'in_stock',
            'supplier_article_raw' => $rawArticle,
            'supplier_article' => $rawArticle,
            'supplier_article_unique' => $rawArticle,
            'norm_article' => $this->normArticle($rawArticle),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function makeSupplierArticlesUnique(array $rows): array
    {
        $counts = [];
        $modelCounts = [];
        foreach ($rows as $row) {
            $key = (string) $row['norm_article'];
            if ($key !== '') {
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }

            $modelKey = $this->brandKey((string) $row['brand']) . '|' . $this->nameKey((string) $row['name']);
            if ($modelKey !== '|') {
                $modelCounts[$modelKey] = ($modelCounts[$modelKey] ?? 0) + 1;
            }
        }

        foreach ($rows as &$row) {
            $key = (string) $row['norm_article'];
            $modelKey = $this->brandKey((string) $row['brand']) . '|' . $this->nameKey((string) $row['name']);
            $row['ambiguous_variant'] = ($key !== '' && ($counts[$key] ?? 0) > 1)
                || (($modelCounts[$modelKey] ?? 0) > 1);

            if ($key !== '' && ($counts[$key] ?? 0) > 1) {
                $suffix = $this->variantSuffix((string) $row['name'], (string) $row['description']);
                $row['supplier_article_unique'] = trim((string) $row['supplier_article_raw'] . ' | ' . $suffix);
                $row['norm_article'] = $this->normArticle((string) $row['supplier_article_unique']);
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<int,string> $cells
     */
    private function rowMarker(array $cells): ?string
    {
        $a = $this->clean($cells[0] ?? '');
        $b = $this->clean($cells[1] ?? '');
        $c = $this->clean($cells[2] ?? '');
        $d = $this->clean($cells[3] ?? '');
        $e = $this->clean($cells[4] ?? '');
        $f = $this->clean($cells[5] ?? '');

        if ($a === '' && $b === '') {
            return null;
        }

        if (($a !== '' && ! $this->isZeroLike($a) && $this->isHeaderLike($a))
            || ($b !== '' && ! $this->isZeroLike($b) && $this->isHeaderLike($b))) {
            return null;
        }

        if ($a !== '' && $b === '' && $c === '' && $d === '' && $e === '' && $f === '') {
            return $a;
        }

        if ($a !== '' && $this->isZeroLike($b) && $this->isZeroLike($c) && $this->isZeroLike($d) && $this->isZeroLike($e) && $this->isZeroLike($f)) {
            return $a;
        }

        if ($this->isZeroLike($a) && $b !== '' && $this->isZeroLike($c) && $this->isZeroLike($d) && $this->isZeroLike($e) && $this->isZeroLike($f)) {
            return $b;
        }

        return null;
    }

    private function canonicalBrand(string $value): ?string
    {
        $key = $this->brandKey($value);
        if (isset(self::BRAND_ALIASES[$key])) {
            return self::BRAND_ALIASES[$key];
        }

        foreach (self::BRAND_ALIASES as $alias => $brand) {
            if ($alias !== '' && str_contains($key, $alias)) {
                return $brand;
            }
        }

        foreach (self::SECTION_WORDS as $section) {
            if (str_contains($key, $this->brandKey($section))) {
                return null;
            }
        }

        return null;
    }

    private function classify(array $row): array
    {
        $brandId = $this->brandByKey[$this->brandKey($row['brand'])] ?? null;
        $match = $this->match($row, $brandId);
        $categoryId = $this->categoryId($row);

        $action = match (true) {
            $match !== null => 'matched',
            (bool) $this->option('only-linked') => 'skip_unlinked',
            $categoryId === null => 'category_missing',
            default => 'create_candidate',
        };

        return $row + [
            'brand_id' => $brandId,
            'category_id' => $categoryId,
            'matched_product_id' => $match['product_id'] ?? null,
            'match_confidence' => $match['confidence'] ?? null,
            'action' => $action,
        ];
    }

    private function match(array $row, ?int $brandId): ?array
    {
        $normArticle = (string) $row['norm_article'];
        if ($normArticle !== '' && isset($this->indexBySupplierArticle[$normArticle])) {
            return ['product_id' => $this->indexBySupplierArticle[$normArticle], 'confidence' => 'supplier_article'];
        }

        if (! empty($row['ambiguous_variant'])) {
            return null;
        }

        $brand = (string) $row['brand'];
        $nameKey = $this->nameKey($brand . ' ' . $row['name']);
        if ($nameKey !== '' && isset($this->indexByProductName[$nameKey])) {
            return ['product_id' => $this->indexByProductName[$nameKey], 'confidence' => 'exact_name'];
        }

        $plainNameKey = $this->nameKey((string) $row['name']);
        if ($plainNameKey !== '' && isset($this->indexByProductName[$plainNameKey])) {
            return ['product_id' => $this->indexByProductName[$plainNameKey], 'confidence' => 'exact_plain_name'];
        }

        if ($brandId !== null) {
            $brandKey = $this->brandKey($this->brandNameById[$brandId] ?? $brand);
            $modelKey = $this->modelKey((string) $row['name'], $brand);
            $key = $brandKey . '|' . $modelKey;
            if ($modelKey !== '' && isset($this->indexByBrandModel[$key])) {
                return ['product_id' => $this->indexByBrandModel[$key], 'confidence' => 'brand_model'];
            }

            $signature = $this->modelSignature($brand . ' ' . $row['name']);
            $sigKey = $brandKey . '|' . $signature;
            if ($signature !== '' && isset($this->indexByModelSignature[$sigKey])) {
                return ['product_id' => $this->indexByModelSignature[$sigKey], 'confidence' => 'model_signature'];
            }
        }

        return null;
    }

    private function applyRows(array $rows, bool $createNew): int
    {
        $now = now();
        $supplierId = $this->ensureSupplier($now);
        $syncId = $this->ensureSync($now);
        $stats = array_fill_keys(['matched', 'created', 'updated_retail', 'skipped', 'errors'], 0);

        foreach ($rows as $row) {
            try {
                $productId = (int) ($row['matched_product_id'] ?? 0);

                if ($productId <= 0 && $createNew && $row['action'] === 'create_candidate') {
                    $productId = $this->createProduct($row, $now);
                    $row['matched_product_id'] = $productId;
                    $row['match_confidence'] = 'created_from_price';
                    $stats['created']++;
                }

                if ($productId <= 0) {
                    $stats['skipped']++;
                    continue;
                }

                $this->upsertSupplierProduct($row, $productId, $supplierId, $syncId, $now);
                $stats['matched']++;

                if ((bool) $this->option('sync-retail-prices') && $row['retail_byn'] !== null) {
                    DB::table('products')->where('id', $productId)->update([
                        'price' => $row['retail_byn'],
                        'in_stock' => $row['in_stock'],
                        'stock_qty' => $row['stock_quantity'],
                        'availability_status' => $this->productAvailability($row['stock_status']),
                        'updated_at' => $now,
                    ]);
                    $stats['updated_retail']++;
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn(sprintf('[error] row %s %s: %s', $row['sheet_row'], $row['name'], $e->getMessage()));
            }
        }

        $this->table(['metric', 'count'], array_map(fn ($key, $value) => [$key, $value], array_keys($stats), array_values($stats)));

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function report(array $rows): int
    {
        $this->newLine();
        $this->table(['metric', 'count'], [
            ['parsed rows', count($rows)],
            ['in stock', count(array_filter($rows, fn ($row) => $row['stock_status'] === 'in_stock'))],
            ['expected', count(array_filter($rows, fn ($row) => $row['stock_status'] === 'preorder'))],
            ['with supplier article', count(array_filter($rows, fn ($row) => trim((string) $row['supplier_article_raw']) !== ''))],
        ]);

        $this->info('Actions:');
        $this->table(['action', 'count'], $this->counts($rows, 'action'));

        $this->info('Brands:');
        $this->table(['brand', 'count'], $this->counts($rows, 'brand'));

        $matched = array_filter($rows, fn ($row) => $row['matched_product_id'] !== null);
        $this->info('Match confidence:');
        $this->table(['confidence', 'count'], $this->counts($matched, 'match_confidence'));

        $this->info('Examples:');
        $this->table(
            ['sheet', 'row', 'brand', 'category', 'section', 'article', 'name', 'opt', 'retail', 'stock', 'action', 'match'],
            array_map(fn ($row) => [
                mb_substr((string) ($row['sheet'] ?? '-'), 0, 22),
                $row['sheet_row'],
                $row['brand'],
                mb_substr($this->categoryName($row), 0, 24),
                mb_substr((string) ($row['section'] ?? '-'), 0, 22),
                mb_substr((string) $row['supplier_article'], 0, 24),
                mb_substr((string) $row['name'], 0, 34),
                $row['price_byn'] ?? '-',
                $row['retail_byn'] ?? '-',
                $row['stock_status'],
                $row['action'],
                $row['matched_product_id'] ?: '-',
            ], array_slice($rows, 0, 20))
        );

        $reportPath = trim((string) ($this->option('candidate-report') ?? ''));
        if ($reportPath !== '') {
            $this->writeCandidateReport($rows, $reportPath);
        }

        $this->line('Next: run with --apply to update confident matches. Add --create-new only after reviewing create_candidate rows.');

        return self::SUCCESS;
    }

    private function upsertSupplierProduct(array $row, int $productId, int $supplierId, ?int $syncId, $now): void
    {
        $productSku = (string) DB::table('products')->where('id', $productId)->value('sku');
        $payload = [
            'supplier_sync_id' => $syncId,
            'product_id' => $productId,
            'product_sku' => $productSku,
            'supplier_article' => $row['supplier_article_raw'],
            'supplier_article_normalized' => $row['norm_article'],
            'supplier_name' => trim($row['brand'] . ' ' . $row['name']),
            'source_url' => null,
            'price' => $row['price_byn'],
            'currency' => 'BYN',
            'currency_rate' => 1,
            'price_byn' => $row['price_byn'],
            'in_stock' => $row['in_stock'],
            'stock_quantity' => $row['stock_quantity'],
            'stock_status' => $row['stock_status'],
            'stock_text' => $row['stock_text'] !== '' ? $row['stock_text'] : null,
            'delivery_days' => $row['stock_status'] === 'preorder' ? 7 : ($row['in_stock'] ? 0 : null),
            'last_stock_synced_at' => $now,
            'match_status' => 'matched',
            'match_confidence' => $row['match_confidence'],
            'raw' => json_encode([
                'sheet' => $row['sheet'],
                'sheet_row' => $row['sheet_row'],
                'brand' => $row['brand'],
                'section' => $row['section'],
                'description' => $row['description'],
                'retail_byn' => $row['retail_byn'],
                'supplier_article_unique' => $row['supplier_article_unique'],
            ], JSON_UNESCAPED_UNICODE),
            'last_synced_at' => $now,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('supplier_products', 'supplier_article_compact')) {
            $payload['supplier_article_compact'] = $this->compactArticle((string) $row['supplier_article_unique']);
        }

        $existingId = DB::table('supplier_products')
            ->where('supplier_id', $supplierId)
            ->where('product_id', $productId)
            ->value('id');

        if ($existingId) {
            DB::table('supplier_products')->where('id', $existingId)->update($payload);
            return;
        }

        DB::table('supplier_products')->updateOrInsert(
            ['supplier_id' => $supplierId, 'supplier_article_normalized' => $row['norm_article']],
            $payload + ['created_at' => $now]
        );
    }

    private function createProduct(array $row, $now): int
    {
        $brandId = $row['brand_id'] ?? $this->findOrCreateBrand((string) $row['brand'], $now);
        $name = $this->productName($row);

        return (int) DB::table('products')->insertGetId([
            'category_id' => (int) $row['category_id'],
            'brand_id' => $brandId,
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'h1' => $name,
            'sku' => $this->nextSku(),
            'price' => $row['retail_byn'] ?? 0,
            'currency' => 'BYN',
            'content' => $row['description'],
            'short_description' => $row['description'],
            'images' => json_encode([]),
            'specs' => json_encode([]),
            'unit' => 'шт',
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => $row['in_stock'],
            'stock_qty' => $row['stock_quantity'],
            'availability_status' => $this->productAvailability($row['stock_status']),
            'is_new' => true,
            'meta_title' => $name . ' купить в %city%',
            'meta_description' => $name . ' — купить в Беларуси.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function buildIndexes(): void
    {
        DB::table('brands')->get(['id', 'name'])->each(function ($brand) {
            $this->brandByKey[$this->brandKey((string) $brand->name)] = (int) $brand->id;
            $this->brandNameById[(int) $brand->id] = (string) $brand->name;
        });

        DB::table('categories')->get(['id', 'slug'])->each(function ($category) {
            $this->categoryBySlug[(string) $category->slug] = (int) $category->id;
        });

        $supplierId = $this->supplierId();
        if ($supplierId > 0) {
            DB::table('supplier_products')
                ->where('supplier_id', $supplierId)
                ->whereNotNull('product_id')
                ->get(['supplier_article_normalized', 'supplier_article', 'product_id'])
                ->each(function ($row) {
                    foreach ([$row->supplier_article_normalized, $row->supplier_article] as $article) {
                        $key = $this->normArticle((string) $article);
                        if ($key !== '') {
                            $this->indexBySupplierArticle[$key] = (int) $row->product_id;
                        }
                    }
                });
        }

        DB::table('products')
            ->where('is_archived', false)
            ->get(['id', 'name', 'brand_id'])
            ->each(function ($product) {
                $productId = (int) $product->id;
                $name = (string) $product->name;
                $nameKey = $this->nameKey($name);
                if ($nameKey !== '') {
                    $this->indexByProductName[$nameKey] = $productId;
                }

                $brandId = (int) $product->brand_id;
                if ($brandId > 0 && isset($this->brandNameById[$brandId])) {
                    $brand = $this->brandNameById[$brandId];
                    $brandKey = $this->brandKey($brand);
                    $modelKey = $this->modelKey($name, $brand);
                    if ($modelKey !== '') {
                        $this->indexByBrandModel[$brandKey . '|' . $modelKey] = $productId;
                    }

                    $signature = $this->modelSignature($name);
                    if ($signature !== '') {
                        $this->indexByModelSignature[$brandKey . '|' . $signature] = $productId;
                    }
                }
            });
    }

    private function categoryId(array $row): ?int
    {
        $rawText = mb_strtolower(($row['sheet'] ?? '') . ' ' . $row['section'] . ' ' . ($row['supplier_article'] ?? '') . ' ' . ($row['supplier_article_raw'] ?? '') . ' ' . $row['name'] . ' ' . $row['description']);
        $text = $this->nameKey($rawText);

        if (preg_match('/\x{044D}\x{0432}\x{043D}/u', $rawText)) {
            return $this->categoryBySlug['vodonagrevateli'] ?? $this->categoryBySlug['electric'] ?? null;
        }

        if (str_contains($text, 'termo max')) {
            return $this->categoryBySlug['vodonagrevateli'] ?? null;
        }

        foreach (['gaz', 'gazov', 'kolonk', 'cares', 'clas', 'alteas', 'genus', 'vitopend', 'vitodens', 'lynx', 'gepard', 'pantera', 'torino', 'world alpha', 'antea', 'itaca', 'minorca', 'formentera', 'giava', 'bali', 'deluxe', 'prime', 'smart tok'] as $keyword) {
            if (str_contains($text, $keyword)) {
                return $this->categoryBySlug['gazovye'] ?? $this->categoryBySlug['gas'] ?? null;
            }
        }

        if (str_contains($text, 'dymohod') || str_contains($text, 'дымоход')) {
            return $this->categoryBySlug['dymohody-nerzhaveyushchie'] ?? $this->categoryBySlug['koaxial-dymoxod'] ?? null;
        }

        if (str_contains($text, 'radiator') || str_contains($text, 'радиатор')) {
            if (str_contains($text, 'bimetal') || str_contains($text, 'биметал')) {
                return $this->categoryBySlug['bimetallicheskie-radiatory'] ?? $this->categoryBySlug['radiatory'] ?? null;
            }

            return $this->categoryBySlug['stalnye-radiatory'] ?? $this->categoryBySlug['radiatory'] ?? null;
        }

        if (str_contains($text, 'vodonagrevatel') || str_contains($text, 'водонагревател') || str_contains($text, 'бойлер')) {
            if (str_contains($text, 'kosven') || str_contains($text, 'косвен')) {
                return $this->categoryBySlug['bojlery-kosvennogo-nagreva'] ?? $this->categoryBySlug['vodonagrevateli'] ?? null;
            }

            return $this->categoryBySlug['vodonagrevateli'] ?? $this->categoryBySlug['electric'] ?? null;
        }

        if (str_contains($text, 'gazov') || str_contains($text, 'газов') || str_contains($text, 'kolonk') || str_contains($text, 'колонк')) {
            return $this->categoryBySlug['gazovye'] ?? $this->categoryBySlug['gas'] ?? null;
        }

        if (str_contains($text, 'elektr') || str_contains($text, 'электр')) {
            return $this->categoryBySlug['elektricheskie'] ?? $this->categoryBySlug['electric'] ?? null;
        }

        return $this->categoryBySlug['kotly'] ?? null;
    }

    private function productName(array $row): string
    {
        $name = trim((string) $row['name']);
        $brand = trim((string) $row['brand']);
        if ($brand !== '' && ! str_contains($this->nameKey($name), $this->nameKey($brand))) {
            $name = $brand . ' ' . $name;
        }

        return $name;
    }

    private function ensureSupplier($now): int
    {
        $existing = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->first();
        if ($existing) {
            DB::table('suppliers')->where('id', $existing->id)->update([
                'name' => self::SUPPLIER_NAME,
                'currency' => 'BYN',
                'currency_rate' => 1,
                'contact' => self::SOURCE_URL,
                'is_active' => true,
                'updated_at' => $now,
            ]);
            return (int) $existing->id;
        }

        return (int) DB::table('suppliers')->insertGetId([
            'code' => self::SUPPLIER_CODE,
            'name' => self::SUPPLIER_NAME,
            'currency' => 'BYN',
            'currency_rate' => 1,
            'contact' => self::SOURCE_URL,
            'notes' => 'Prices and stock are synced from Thermostudio Google Sheets. Product content is enriched separately from source sites.',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function ensureSync($now): ?int
    {
        if (! Schema::hasTable('supplier_syncs')) {
            return null;
        }

        DB::table('supplier_syncs')->updateOrInsert(
            ['key' => self::SYNC_KEY],
            [
                'name' => self::SUPPLIER_NAME,
                'code' => self::SUPPLIER_CODE,
                'title' => 'Термостудия: прайс и остатки',
                'description' => 'Google Sheets прайс: обновляет supplier_products, цены и наличие без автоматического создания дублей.',
                'command' => 'supplier:sync-thermostudio-pricelist',
                'source_url' => self::SOURCE_URL,
                'image_disk_path' => 'img/products/thermostudio',
                'is_active' => true,
                'last_run_at' => $now,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        return (int) DB::table('supplier_syncs')->where('key', self::SYNC_KEY)->value('id');
    }

    private function supplierId(): int
    {
        return (int) (DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id') ?? 0);
    }

    private function findOrCreateBrand(string $name, $now): int
    {
        $key = $this->brandKey($name);
        if (isset($this->brandByKey[$key])) {
            return $this->brandByKey[$key];
        }

        $id = (int) DB::table('brands')->insertGetId([
            'name' => $name,
            'slug' => $this->uniqueBrandSlug($name),
            'h1' => $name,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->brandByKey[$key] = $id;
        $this->brandNameById[$id] = $name;

        return $id;
    }

    private function productAvailability(string $stockStatus): string
    {
        return match ($stockStatus) {
            'in_stock' => 'in_stock',
            'preorder' => 'preorder',
            default => 'out_of_stock',
        };
    }

    /**
     * @return array<string,int>
     */
    private function counts(array $rows, string $key): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $value = (string) ($row[$key] ?? '-');
            $value = $value !== '' ? $value : '-';
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }
        arsort($counts);

        return array_map(fn ($key, $value) => [$key, $value], array_keys($counts), array_values($counts));
    }

    private function categoryName(array $row): string
    {
        $categoryId = $this->categoryId($row);
        if (! $categoryId) {
            return '-';
        }

        return (string) (DB::table('categories')->where('id', $categoryId)->value('name') ?: '-');
    }

    private function writeCandidateReport(array $rows, string $path): void
    {
        $candidates = array_values(array_filter($rows, fn ($row) => $row['action'] === 'create_candidate'));
        if ($candidates === []) {
            $this->info('Candidate report skipped: no create_candidate rows.');
            return;
        }

        $path = $this->absolutePath($path);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $handle = fopen($path, 'wb');
        if ($handle === false) {
            $this->warn('Candidate report failed: cannot write ' . $path);
            return;
        }

        fputcsv($handle, ['sheet', 'row', 'brand', 'category', 'section', 'article', 'name', 'description', 'price_byn', 'retail_byn', 'stock', 'suggestions']);
        foreach ($candidates as $row) {
            fputcsv($handle, [
                $row['sheet'],
                $row['sheet_row'],
                $row['brand'],
                $this->categoryName($row),
                $row['section'],
                $row['supplier_article'],
                $row['name'],
                $row['description'],
                $row['price_byn'],
                $row['retail_byn'],
                $row['stock_text'],
                implode(' || ', $this->candidateSuggestions($row)),
            ]);
        }

        fclose($handle);
        $this->info(sprintf('Candidate report written: %s (%d rows)', $path, count($candidates)));
    }

    /**
     * @return string[]
     */
    private function candidateSuggestions(array $row): array
    {
        $tokens = $this->importantTokens($row['brand'] . ' ' . $row['name']);
        if ($tokens === []) {
            return [];
        }

        $query = DB::table('products')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->where('products.is_archived', false)
            ->select('products.id', 'products.sku', 'products.name', 'brands.name as brand');

        $query->where(function ($q) use ($tokens) {
            foreach (array_slice($tokens, 0, 5) as $token) {
                $q->orWhere('products.name', 'like', '%' . $token . '%');
            }
        });

        $sourceKey = $this->nameKey($row['brand'] . ' ' . $row['name']);
        $results = [];
        foreach ($query->limit(80)->get() as $product) {
            $candidateKey = $this->nameKey((string) $product->name);
            similar_text($sourceKey, $candidateKey, $score);
            if ($score < 45) {
                continue;
            }

            $results[] = [
                'score' => $score,
                'label' => sprintf('%d [%s] %s | %s', (int) $product->id, (string) $product->sku, (string) ($product->brand ?? '-'), (string) $product->name),
            ];
        }

        usort($results, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_map(fn ($item) => sprintf('%.0f%% %s', $item['score'], $item['label']), array_slice($results, 0, 5));
    }

    /**
     * @return string[]
     */
    private function importantTokens(string $value): array
    {
        $tokens = preg_split('/\s+/u', $this->nameKey($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stop = array_fill_keys(['kotlov', 'котел', 'котёл', 'котлы', 'gazovyi', 'газовый', 'elektricheskii', 'электрический', 'vodonagrevatel', 'водонагреватель', 'radiator', 'радиатор', 'dlia', 'для', 'the', 'new'], true);

        $tokens = array_values(array_unique(array_filter($tokens, fn ($token) => mb_strlen($token) >= 3 && ! isset($stop[$token]))));
        usort($tokens, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        return $tokens;
    }

    /**
     * @return array<string,bool>
     */
    private function brandFilter(): array
    {
        $brands = $this->csvOption('brand');
        if ($brands === []) {
            $brands = self::DEFAULT_BRANDS;
        }

        $canonical = [];
        foreach ($brands as $brand) {
            $canonical[] = $this->canonicalBrand($brand) ?: $brand;
        }

        return array_fill_keys(array_map(fn ($brand) => $this->brandKey($brand), $canonical), true);
    }

    /**
     * @return string[]
     */
    private function csvOption(string $name): array
    {
        $values = $this->option($name) ?: [];
        if (! is_array($values)) {
            $values = [$values];
        }

        $result = [];
        foreach ($values as $value) {
            foreach (explode(',', (string) $value) as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $result[] = $part;
                }
            }
        }

        return array_values(array_unique($result));
    }

    private function stock(string $value): array
    {
        $text = mb_strtolower($this->clean($value));
        $quantity = null;

        if (preg_match('/>\s*(\d+)/u', $text, $match)) {
            $quantity = (int) $match[1] + 1;
        } elseif (preg_match('/^\d+$/u', $text)) {
            $quantity = (int) $text;
        }

        if ($quantity !== null) {
            return ['status' => $quantity > 0 ? 'in_stock' : 'out_of_stock', 'quantity' => $quantity];
        }

        if (str_contains($text, 'ожида') || str_contains($text, 'под заказ')) {
            return ['status' => 'preorder', 'quantity' => null];
        }

        if (str_contains($text, 'есть') || str_contains($text, 'налич') || str_contains($text, 'склад')) {
            return ['status' => 'in_stock', 'quantity' => null];
        }

        return ['status' => 'out_of_stock', 'quantity' => null];
    }

    /**
     * @param array<int,string> $cells
     * @return array<int,string>
     */
    private function trimLeadingEmptyCells(array $cells): array
    {
        while (count($cells) > 1 && $this->clean((string) ($cells[0] ?? '')) === '') {
            array_shift($cells);
        }

        return array_values($cells);
    }

    /**
     * @param array<int,string> $cells
     */
    private function stockColumnIndex(array $cells): int
    {
        for ($i = count($cells) - 1; $i >= 0; $i--) {
            if ($this->isStockLikeCell($cells[$i] ?? '')) {
                return $i;
            }
        }

        return array_key_exists(5, $cells) ? 5 : max(0, count($cells) - 1);
    }

    /**
     * @param array<int,string> $cells
     * @return array{0:int,1:int}
     */
    private function priceColumnIndexes(array $cells, int $stockIndex): array
    {
        if ($stockIndex >= 7 && $this->money($cells[3] ?? '') !== null && $this->money($cells[4] ?? '') !== null) {
            return [3, 4];
        }

        if (
            $this->money($cells[$stockIndex + 1] ?? '') !== null
            && $this->money($cells[$stockIndex + 2] ?? '') !== null
        ) {
            return [$stockIndex + 1, $stockIndex + 2];
        }

        if (
            $stockIndex >= 2
            && $this->money($cells[$stockIndex - 2] ?? '') !== null
            && $this->money($cells[$stockIndex - 1] ?? '') !== null
        ) {
            return [$stockIndex - 2, $stockIndex - 1];
        }

        return [3, 4];
    }

    /**
     * @param array<int,string> $cells
     */
    private function descriptionFromCells(array $cells, int $priceIndex, int $retailIndex, int $stockIndex): string
    {
        $parts = [];
        $stop = min(count($cells) - 1, max($priceIndex, $retailIndex, $stockIndex) - 1);

        for ($i = 2; $i <= $stop; $i++) {
            if (in_array($i, [$priceIndex, $retailIndex, $stockIndex], true)) {
                continue;
            }

            $value = $this->clean($cells[$i] ?? '');
            if ($value === '' || $this->isZeroLike($value) || $this->isHeaderLike($value)) {
                continue;
            }

            $parts[] = $value;
        }

        return implode('; ', array_values(array_unique($parts)));
    }

    private function isStockLikeCell(string $value): bool
    {
        $text = mb_strtolower($this->clean($value));

        return $text === '#ref!'
            || str_contains($text, 'есть')
            || str_contains($text, 'отсутств')
            || str_contains($text, 'ожида')
            || str_contains($text, 'под заказ')
            || str_contains($text, 'налич')
            || str_contains($text, 'склад');
    }

    private function isSummarySheet(string $title): bool
    {
        $key = $this->nameKey($title);

        return in_array($key, ['dannye', 'данные'], true);
    }

    private function money(string $value): ?float
    {
        $value = trim(str_replace(["\xc2\xa0", ' '], '', $value));
        if ($value === '' || ! preg_match('/\d/', $value)) {
            return null;
        }

        $value = preg_replace('/[^\d,.\-]/u', '', $value) ?? '';
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $lastComma = strrpos($value, ',');
            $lastDot = strrpos($value, '.');
            if ($lastDot !== false && $lastComma !== false && $lastDot > $lastComma) {
                $value = str_replace(',', '', $value);
            } else {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            }
        } else {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private function isHeaderRow(array $cells): bool
    {
        $text = $this->nameKey(implode(' ', $cells));

        return str_contains($text, 'artikul model opisanie')
            || (str_contains($text, 'артикул') && str_contains($text, 'модель'));
    }

    private function isHeaderLike(string $value): bool
    {
        $key = $this->nameKey($value);

        return $key === ''
            || $key === '0'
            || str_contains($key, 'прайс лист')
            || str_contains($key, 'артикул')
            || str_contains($key, 'модель')
            || str_contains($key, 'описание')
            || str_contains($key, 'курс евро');
    }

    private function isEmptyOrFormulaErrorRow(array $cells): bool
    {
        $joined = trim(implode('', $cells));
        if ($joined === '') {
            return true;
        }

        foreach ($cells as $cell) {
            if ($cell !== '' && $cell !== '#REF!') {
                return false;
            }
        }

        return true;
    }

    private function isZeroLike(string $value): bool
    {
        $value = trim($value);

        return $value === '' || $value === '0' || $value === '0.00' || $value === '#REF!';
    }

    private function normArticle(string $value): string
    {
        $value = mb_strtoupper(trim($value));
        $value = strtr($value, ['А' => 'A', 'В' => 'B', 'Е' => 'E', 'К' => 'K', 'М' => 'M', 'Н' => 'H', 'О' => 'O', 'Р' => 'P', 'С' => 'C', 'Т' => 'T', 'У' => 'Y', 'Х' => 'X']);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function compactArticle(string $value): string
    {
        return preg_replace('/[^A-ZА-ЯЁ0-9]+/u', '', $this->normArticle($value)) ?? '';
    }

    private function variantSuffix(string $name, string $description): string
    {
        $suffix = $this->nameKey($description) ?: $this->nameKey($name);
        $suffix = preg_replace('/\s+/u', '-', $suffix) ?? $suffix;

        return mb_substr($suffix, 0, 48) ?: 'variant';
    }

    private function brandKey(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['ё', '.', '-', '_'], ['е', ' ', ' ', ' '], $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function nameKey(string $value): string
    {
        $value = mb_strtolower($value);
        $value = str_replace(['ё', '"', "'", '«', '»'], ['е', '', '', '', ''], $value);
        $value = preg_replace('/[^a-zа-я0-9]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function modelKey(string $name, string $brand): string
    {
        $name = $this->nameKey($name);
        $brand = $this->nameKey($brand);
        if ($brand !== '') {
            $name = trim(preg_replace('/\b' . preg_quote($brand, '/') . '\b/u', '', $name) ?? $name);
        }

        foreach (['котел', 'котёл', 'газовый', 'электрический', 'водонагреватель', 'радиатор', 'стальной', 'биметаллический', 'дымоход', 'колонка', 'настенный', 'напольный'] as $word) {
            $name = trim(preg_replace('/\b' . preg_quote($word, '/') . '\b/u', ' ', $name) ?? $name);
        }

        return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    }

    private function modelSignature(string $value): string
    {
        $value = $this->modelKey($value, '');
        foreach (['квт', 'kw', 'byn', 'рф', 'br'] as $word) {
            $value = trim(preg_replace('/\b' . preg_quote($word, '/') . '\b/u', ' ', $value) ?? $value);
        }

        return preg_replace('/[^a-zа-я0-9]+/u', '', $value) ?? '';
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'thermostudio-product';
        $slug = $base;
        $i = 2;

        while (DB::table('products')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    private function uniqueBrandSlug(string $name): string
    {
        $base = Str::slug($name) ?: Str::random(8);
        $slug = $base;
        $i = 2;

        while (DB::table('brands')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    private function nextSku(): string
    {
        $max = DB::table('products')
            ->where('sku', 'like', 'KOTLOV-%')
            ->pluck('sku')
            ->map(fn ($sku) => preg_match('/^KOTLOV-(\d+)$/', (string) $sku, $match) ? (int) $match[1] : 0)
            ->max() ?? 0;

        $next = max(0, (int) $max) + 1;
        do {
            $sku = sprintf('KOTLOV-%06d', $next++);
        } while (DB::table('products')->where('sku', $sku)->exists());

        return $sku;
    }

    private function absolutePath(string $path): string
    {
        if (preg_match('/^[a-zA-Z]:[\\\\\/]/', $path) || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return $path;
        }

        return base_path($path);
    }

    private function fetch(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 90,
                'follow_location' => 1,
                'max_redirects' => 10,
                'header' => "User-Agent: Mozilla/5.0 (compatible; KotlovBot/1.0)\r\nAccept: */*\r\n",
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $content = @file_get_contents($url, false, $context);

        return $content === false ? null : $content;
    }

    private function clean(string $value): string
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace(["\xc2\xa0", "\r"], [' ', ''], $value);
        $value = preg_replace('/[ \t]+/u', ' ', $value) ?? $value;
        $value = preg_replace("/\n{3,}/u", "\n\n", $value) ?? $value;

        return trim($value);
    }
}

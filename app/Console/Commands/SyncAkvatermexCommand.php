<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SyncAkvatermexCommand extends Command
{
    protected $signature = 'supplier:sync-akvatermex
        {--dry-run : Preview only}
        {--apply : Write supplier prices and stock}
        {--sheet-url= : Google Sheets URL}
        {--price-file= : Local XLSX file instead of downloading Google Sheet}
        {--brand=* : Process only selected brands, repeatable or comma-separated}
        {--available-only : Keep only rows with stock}
        {--only-linked : Update only already linked Akvatermex supplier rows}
        {--create-new : Create products that do not match existing catalog items}
        {--sync-retail-prices : Update products.price from retail BYN}
        {--prefer-teplodvor-source : Prefer storage/teplodvor_index.json product cards over blocked thermex.by source URLs}
        {--teplodvor-index=teplodvor_index.json : Local Teplodvor slug index path relative to storage/}
        {--candidate-report= : Write create_candidate review CSV to a path}
        {--limit= : Process only N parsed rows after filters}
        {--offset=0 : Skip N parsed rows after filters}';

    protected $description = 'Audit and sync Akvatermex Google price list: Thermex group prices, stock and source URLs.';

    private const SUPPLIER_CODE = 'akvatermex';
    private const SUPPLIER_NAME = 'АКВАТЕРМЕКС';
    private const SYNC_KEY = 'akvatermex_pricelist';
    private const SOURCE_URL = 'https://docs.google.com/spreadsheets/d/19G0Mei9zkr8iFzTJeYKFHd4IYeIfJwH_7dDAYhTp5jk/edit';
    private const DEFAULT_SHEET_URL = 'https://docs.google.com/spreadsheets/d/19G0Mei9zkr8iFzTJeYKFHd4IYeIfJwH_7dDAYhTp5jk/edit?gid=500849086#gid=500849086';
    private const CACHE_PATH = 'supplier-cache/akvatermex-pricelist.xlsx';

    private const DEFAULT_BRANDS = [
        'Thermex',
        'Garanterm',
        'Edisson',
        'AquaVerso',
        'Etalon',
        'Eurostar',
        'EuroElite',
    ];

    private const BRAND_ALIASES = [
        'thermex' => 'Thermex',
        'термекс' => 'Thermex',
        'тэрмекс' => 'Thermex',
        'garanterm' => 'Garanterm',
        'edisson' => 'Edisson',
        'aquaverso' => 'AquaVerso',
        'aqua verso' => 'AquaVerso',
        'etalon' => 'Etalon',
        'eurostar' => 'Eurostar',
        'euro star' => 'Eurostar',
        'euroelite' => 'EuroElite',
        'euro elite' => 'EuroElite',
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

    /** @var array<string,string> */
    private array $teplodvorIndex = [];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply') && ! (bool) $this->option('dry-run');
        $createNew = (bool) $this->option('create-new');

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: Akvatermex price list will write changes.</>'
            : '<fg=yellow;options=bold>DRY RUN: Akvatermex price list will preview only.</>');

        try {
            $rows = $this->loadRows();
        } catch (\Throwable $e) {
            $this->error('Price list load failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $brandFilter = $this->brandFilter();
        $availableOnly = (bool) $this->option('available-only');
        $rows = array_values(array_filter($rows, function (array $row) use ($brandFilter, $availableOnly) {
            if ($row['name'] === '' || ($row['price_byn'] === null && $row['retail_byn'] === null)) {
                return false;
            }

            if (! isset($brandFilter[$this->brandKey($row['brand'])])) {
                return false;
            }

            if ($availableOnly && $row['stock_status'] !== 'in_stock') {
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
        $path = $file
            ? $this->absolutePath((string) $file)
            : $this->downloadSheet((string) ($this->option('sheet-url') ?: self::DEFAULT_SHEET_URL));

        $spreadsheet = IOFactory::load($path);
        $rows = [];
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $title = $sheet->getTitle();
            if ($this->skipSheet($title)) {
                continue;
            }

            $rows = array_merge($rows, $this->parseSheet($sheet, $title));
        }

        return $this->dedupeRows($rows);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function parseSheet($sheet, string $title): array
    {
        $rows = [];
        $maxRow = $sheet->getHighestRow();
        $layout = $this->layoutForSheet($title);
        if ($layout === null) {
            return [];
        }

        for ($rowIndex = $layout['start']; $rowIndex <= $maxRow; $rowIndex++) {
            $name = $this->cell($sheet, $layout['name'], $rowIndex);
            if ($name === '' || $this->isHeaderLike($name)) {
                continue;
            }

            $brand = $this->detectBrand($name, $title);
            if ($brand === null) {
                continue;
            }

            $price = $this->money($this->cell($sheet, $layout['price'], $rowIndex));
            $retail = $this->money($this->cell($sheet, $layout['retail'], $rowIndex));
            $stockText = $this->cell($sheet, $layout['stock'], $rowIndex);
            $stock = $this->stock($stockText);
            $ean = isset($layout['ean']) ? $this->cell($sheet, $layout['ean'], $rowIndex) : '';
            $article = isset($layout['article']) ? $this->cell($sheet, $layout['article'], $rowIndex) : '';
            $sourceUrl = $this->sourceUrl($sheet, $layout, $rowIndex);
            if ((bool) $this->option('prefer-teplodvor-source')) {
                $sourceUrl = $this->teplodvorSourceUrl($brand, $name) ?: $sourceUrl;
            }
            $categoryHint = isset($layout['category']) ? $this->cell($sheet, $layout['category'], $rowIndex) : '';
            $description = $this->description($sheet, $layout, $rowIndex);

            if ($price === null && $retail !== null) {
                $price = $retail;
            }

            if ($retail === null && $price !== null) {
                $retail = $price;
            }

            if ($retail !== null && $price !== null && $retail < $price) {
                $retail = $price;
            }

            $supplierArticle = $this->bestArticle($article, $ean, $brand, $name);

            $rows[] = [
                'sheet' => $title,
                'sheet_row' => $rowIndex,
                'brand' => $brand,
                'name' => $name,
                'description' => $description,
                'category_hint' => $categoryHint,
                'price_byn' => $price,
                'retail_byn' => $retail,
                'stock_text' => $stockText,
                'stock_quantity' => $stock['quantity'],
                'stock_status' => $stock['status'],
                'in_stock' => $stock['status'] === 'in_stock',
                'ean' => $ean,
                'supplier_article_raw' => $supplierArticle,
                'supplier_article' => $supplierArticle,
                'norm_article' => $this->normArticle($supplierArticle),
                'source_url' => $sourceUrl,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string,int>|null
     */
    private function layoutForSheet(string $title): ?array
    {
        $key = $this->nameKey($title);

        if (str_contains($key, 'full pricelist')) {
            return ['start' => 2, 'name' => 1, 'retail' => 6, 'price' => 8, 'stock' => 9, 'ean' => 11, 'article' => 13, 'source' => 14, 'category' => 15, 'description_cols' => [2, 3, 4, 5, 10, 12, 16]];
        }

        if (str_contains($key, 'konvekt')) {
            return ['start' => 3, 'name' => 1, 'retail' => 8, 'price' => 10, 'stock' => 12, 'ean' => 14, 'source' => 1, 'category_slug' => 'elektricheskie-konvektoryi', 'description_cols' => [3, 4, 5, 6, 7, 13]];
        }

        if (str_contains($key, 'gazovoe')) {
            return ['start' => 3, 'name' => 1, 'retail' => 12, 'price' => 13, 'stock' => 15, 'ean' => 17, 'source' => 1, 'category_slug' => 'gas', 'description_cols' => [3, 4, 5, 6, 8, 9, 10, 11, 16]];
        }

        if (str_contains($key, 'ne rabotaet') || str_contains($key, 'работает')) {
            return ['start' => 2, 'name' => 1, 'retail' => 14, 'price' => 16, 'stock' => 18, 'source' => 1, 'category_slug' => 'elektricheskie', 'description_cols' => [3, 4, 5, 6, 7, 8, 9, 10, 11]];
        }

        return null;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function dedupeRows(array $rows): array
    {
        $result = [];
        $seen = [];
        foreach ($rows as $row) {
            $key = $row['norm_article'] ?: $this->nameKey($row['brand'] . ' ' . $row['name']);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $row;
        }

        return $result;
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
        $article = (string) $row['norm_article'];
        if ($article !== '' && isset($this->indexBySupplierArticle[$article])) {
            return ['product_id' => $this->indexBySupplierArticle[$article], 'confidence' => 'supplier_article'];
        }

        if ((bool) $this->option('only-linked')) {
            return null;
        }

        $brand = (string) $row['brand'];
        foreach ([$brand . ' ' . $row['name'], (string) $row['name']] as $name) {
            $key = $this->nameKey($name);
            if ($key !== '' && isset($this->indexByProductName[$key])) {
                return ['product_id' => $this->indexByProductName[$key], 'confidence' => 'exact_name'];
            }
        }

        if ($brandId !== null) {
            $modelKey = $this->modelKey((string) $row['name'], $brand);
            $brandKey = $this->brandKey($this->brandNameById[$brandId] ?? $brand);
            if ($modelKey !== '' && isset($this->indexByBrandModel[$brandKey . '|' . $modelKey])) {
                return ['product_id' => $this->indexByBrandModel[$brandKey . '|' . $modelKey], 'confidence' => 'brand_model'];
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
        $this->table(['metric', 'count'], [
            ['parsed rows', count($rows)],
            ['in stock', count(array_filter($rows, fn ($row) => $row['stock_status'] === 'in_stock'))],
            ['with source url', count(array_filter($rows, fn ($row) => (string) $row['source_url'] !== ''))],
            ['with EAN', count(array_filter($rows, fn ($row) => (string) $row['ean'] !== ''))],
        ]);

        $this->info('Actions:');
        $this->table(['action', 'count'], $this->counts($rows, 'action'));

        $this->info('Brands:');
        $this->table(['brand', 'count'], $this->counts($rows, 'brand'));

        $this->info('Match confidence:');
        $matched = array_filter($rows, fn ($row) => $row['matched_product_id'] !== null);
        $this->table(['confidence', 'count'], $this->counts($matched, 'match_confidence'));

        $this->info('Examples:');
        $this->table(
            ['sheet', 'row', 'brand', 'category', 'article', 'name', 'opt', 'retail', 'stock', 'source', 'action', 'match'],
            array_map(fn ($row) => [
                mb_substr((string) $row['sheet'], 0, 22),
                $row['sheet_row'],
                $row['brand'],
                mb_substr($this->categoryName($row), 0, 22),
                mb_substr((string) $row['supplier_article'], 0, 18),
                mb_substr((string) $row['name'], 0, 34),
                $row['price_byn'] ?? '-',
                $row['retail_byn'] ?? '-',
                $row['stock_status'],
                $row['source_url'] ? 'yes' : '-',
                $row['action'],
                $row['matched_product_id'] ?: '-',
            ], array_slice($rows, 0, 24))
        );

        $reportPath = trim((string) ($this->option('candidate-report') ?? ''));
        if ($reportPath !== '') {
            $this->writeCandidateReport($rows, $reportPath);
        }

        $this->line('Next: run with --apply to update matched rows. Add --create-new only after reviewing create_candidate rows.');

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
            'source_url' => $row['source_url'] ?: null,
            'price' => $row['price_byn'],
            'currency' => 'BYN',
            'currency_rate' => 1,
            'price_byn' => $row['price_byn'],
            'in_stock' => $row['in_stock'],
            'stock_quantity' => $row['stock_quantity'],
            'stock_status' => $row['stock_status'],
            'stock_text' => $row['stock_text'] !== '' ? $row['stock_text'] : null,
            'delivery_days' => $row['in_stock'] ? 0 : null,
            'last_stock_synced_at' => $now,
            'match_status' => 'matched',
            'match_confidence' => $row['match_confidence'],
            'raw' => json_encode([
                'sheet' => $row['sheet'],
                'sheet_row' => $row['sheet_row'],
                'brand' => $row['brand'],
                'name' => $row['name'],
                'ean' => $row['ean'],
                'description' => $row['description'],
                'retail_byn' => $row['retail_byn'],
            ], JSON_UNESCAPED_UNICODE),
            'last_synced_at' => $now,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('supplier_products', 'supplier_article_compact')) {
            $payload['supplier_article_compact'] = $this->compactArticle((string) $row['supplier_article_raw']);
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
        $brandId = $row['brand_id'] ?: $this->findOrCreateBrand((string) $row['brand'], $now);
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
            'meta_description' => $name . ' - купить в Беларуси.',
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
                $nameKey = $this->nameKey((string) $product->name);
                if ($nameKey !== '') {
                    $this->indexByProductName[$nameKey] = $productId;
                }

                $brandId = (int) $product->brand_id;
                if ($brandId > 0 && isset($this->brandNameById[$brandId])) {
                    $brand = $this->brandNameById[$brandId];
                    $modelKey = $this->modelKey((string) $product->name, $brand);
                    if ($modelKey !== '') {
                        $this->indexByBrandModel[$this->brandKey($brand) . '|' . $modelKey] = $productId;
                    }
                }
            });
    }

    private function categoryId(array $row): ?int
    {
        $slug = (string) ($this->layoutForSheet((string) $row['sheet'])['category_slug'] ?? '');
        if ($slug !== '' && isset($this->categoryBySlug[$slug])) {
            return $this->categoryBySlug[$slug];
        }

        $text = $this->nameKey($row['sheet'] . ' ' . $row['category_hint'] . ' ' . $row['name'] . ' ' . $row['description']);

        if (str_contains($text, 'vodonagrevatel')) {
            return $this->categoryBySlug['electric'] ?? $this->categoryBySlug['vodonagrevateli'] ?? null;
        }

        if (str_contains($text, 'konvektor')) {
            return $this->categoryBySlug['elektricheskie-konvektoryi'] ?? null;
        }

        if (str_contains($text, 'gaz') || str_contains($text, 'kolonka')) {
            return $this->categoryBySlug['gas'] ?? $this->categoryBySlug['gazovye'] ?? null;
        }

        if (str_contains($text, 'eurostar') || str_contains($text, 'tesla') || str_contains($text, 'kotel')) {
            return $this->categoryBySlug['elektricheskie'] ?? null;
        }

        return $this->categoryBySlug['electric'] ?? $this->categoryBySlug['vodonagrevateli'] ?? null;
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
                'notes' => 'Prices and stock are synced from Akvatermex Google Sheets. Product content is enriched separately from source URLs.',
                'is_active' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        return (int) DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id');
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
                'title' => 'АКВАТЕРМЕКС: прайс и остатки',
                'description' => 'Google Sheets прайс Thermex group: обновляет supplier_products, цены, наличие и source_url.',
                'command' => 'supplier:sync-akvatermex',
                'source_url' => self::SOURCE_URL,
                'image_disk_path' => 'img/products/akvatermex',
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
        return $stockStatus === 'in_stock' ? 'in_stock' : 'out_of_stock';
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
        $this->line("Downloading Akvatermex Google Sheet: {$exportUrl}");
        $content = $this->fetch($exportUrl);
        if ($content === null || strlen($content) < 1024 || str_starts_with(ltrim($content), '<')) {
            throw new \RuntimeException('Unable to download readable XLSX from Google Sheets.');
        }

        file_put_contents($path, $content);

        return $path;
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

    private function sourceUrl($sheet, array $layout, int $row): string
    {
        $columns = array_values(array_unique(array_filter([
            $layout['source'] ?? null,
            $layout['name'] ?? null,
            $layout['retail'] ?? null,
            $layout['price'] ?? null,
        ])));

        foreach ($columns as $col) {
            $coord = Coordinate::stringFromColumnIndex((int) $col) . $row;
            $url = trim((string) $sheet->getCell($coord)->getHyperlink()->getUrl());
            if ($this->isHttpUrl($url)) {
                return $url;
            }

            $text = $this->cell($sheet, (int) $col, $row);
            if ($this->isHttpUrl($text)) {
                return $text;
            }
        }

        return '';
    }

    private function teplodvorSourceUrl(string $brand, string $name): string
    {
        $index = $this->loadTeplodvorIndex();
        if ($index === []) {
            return '';
        }

        $modelSlug = Str::slug($this->modelKey($name, $brand));
        $brandModelSlug = Str::slug($brand . ' ' . $this->modelKey($name, $brand));
        $candidateSlugs = array_values(array_unique(array_filter([
            $brandModelSlug,
            'vodonagrevatel-' . $brandModelSlug,
            'konvektor-' . $brandModelSlug,
            'gazovyy-vodonagrevatel-' . $brandModelSlug,
            'elektricheskiy-kotel-' . $brandModelSlug,
        ])));

        foreach ($candidateSlugs as $slug) {
            if (isset($index[$slug])) {
                return $index[$slug];
            }
        }

        if ($modelSlug === '') {
            return '';
        }

        $matches = [];
        foreach ($index as $slug => $url) {
            if (str_ends_with($slug, '-' . $modelSlug) || str_contains($slug, '-' . $brandModelSlug)) {
                $matches[$slug] = $url;
                if (count($matches) > 1) {
                    return '';
                }
            }
        }

        if ($matches) {
            return (string) reset($matches);
        }

        return $this->findTeplodvorTokenMatch($index, $brandModelSlug);
    }

    private function findTeplodvorTokenMatch(array $index, string $brandModelSlug): string
    {
        $tokens = $this->slugTokens($brandModelSlug);
        if (count($tokens) < 3) {
            return '';
        }

        $brandToken = $tokens[0] ?? '';
        if ($brandToken === '') {
            return '';
        }

        $matches = [];
        foreach ($index as $slug => $url) {
            $slugTokens = array_fill_keys($this->slugTokens((string) $slug), true);
            if (! isset($slugTokens[$brandToken])) {
                continue;
            }

            $missing = array_values(array_filter($tokens, fn (string $token): bool => ! isset($slugTokens[$token])));
            if ($missing !== []) {
                continue;
            }

            $matches[(string) $slug] = (string) $url;
            if (count($matches) > 1) {
                return '';
            }
        }

        return $matches ? (string) reset($matches) : '';
    }

    /**
     * @return string[]
     */
    private function slugTokens(string $slug): array
    {
        $tokens = preg_split('/-+/u', trim(mb_strtolower($slug), '-')) ?: [];

        return array_values(array_unique(array_filter(
            $tokens,
            fn (string $token): bool => $token !== '' && ! in_array($token, ['vodonagrevatel', 'konvektor', 'gazovyy', 'elektricheskiy', 'kotel'], true)
        )));
    }

    /**
     * @return array<string,string>
     */
    private function loadTeplodvorIndex(): array
    {
        if ($this->teplodvorIndex !== []) {
            return $this->teplodvorIndex;
        }

        $path = storage_path(trim((string) $this->option('teplodvor-index')));
        if (! file_exists($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            return [];
        }

        foreach ($data as $slug => $url) {
            if (is_string($url)) {
                $url = preg_replace('/\s+/u', '-', trim($url)) ?? trim($url);
            }

            if (is_string($slug) && is_string($url) && $this->isHttpUrl($url)) {
                $this->teplodvorIndex[$slug] = $url;
            }
        }

        return $this->teplodvorIndex;
    }

    private function description($sheet, array $layout, int $row): string
    {
        $parts = [];
        foreach (($layout['description_cols'] ?? []) as $col) {
            $value = $this->cell($sheet, (int) $col, $row);
            if ($value === '' || $this->isHeaderLike($value) || $this->isHttpUrl($value)) {
                continue;
            }
            $parts[] = $value;
        }

        return implode('; ', array_values(array_unique($parts)));
    }

    private function bestArticle(string $article, string $ean, string $brand, string $name): string
    {
        $article = trim($article);
        $ean = trim($ean);

        if ($article !== '' && ! preg_match('/^\d+(?:[.,]\d+)?$/', str_replace(' ', '', $article))) {
            return $article;
        }

        if ($ean !== '' && preg_match('/^\d{6,}$/', preg_replace('/\D+/', '', $ean) ?? '')) {
            return $ean;
        }

        if ($article !== '' && mb_strlen($article) >= 5) {
            return $article;
        }

        return trim($brand . ' ' . $name);
    }

    private function detectBrand(string $name, string $sheetTitle): ?string
    {
        $source = $this->brandKey($name . ' ' . $sheetTitle);
        foreach (self::BRAND_ALIASES as $alias => $brand) {
            if (str_contains($source, $this->brandKey($alias))) {
                return $brand;
            }
        }

        return null;
    }

    private function canonicalBrand(string $value): ?string
    {
        $key = $this->brandKey($value);
        return self::BRAND_ALIASES[$key] ?? null;
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

        if (str_contains($text, 'есть') || str_contains($text, 'налич')) {
            return ['status' => 'in_stock', 'quantity' => null];
        }

        return ['status' => 'out_of_stock', 'quantity' => null];
    }

    /**
     * @return array<int,array{0:string,1:int}>
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
        $id = (int) ($row['category_id'] ?? $this->categoryId($row));
        return $id > 0 ? (string) (DB::table('categories')->where('id', $id)->value('name') ?: '-') : '-';
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

        fputcsv($handle, ['sheet', 'row', 'brand', 'category', 'article', 'ean', 'name', 'description', 'price_byn', 'retail_byn', 'stock', 'source_url']);
        foreach ($candidates as $row) {
            fputcsv($handle, [
                $row['sheet'],
                $row['sheet_row'],
                $row['brand'],
                $this->categoryName($row),
                $row['supplier_article'],
                $row['ean'],
                $row['name'],
                $row['description'],
                $row['price_byn'],
                $row['retail_byn'],
                $row['stock_text'],
                $row['source_url'],
            ]);
        }

        fclose($handle);
        $this->info(sprintf('Candidate report written: %s (%d rows)', $path, count($candidates)));
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

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'akvatermex-product';
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

    private function skipSheet(string $title): bool
    {
        $key = $this->nameKey($title);

        return str_contains($key, 'kontakt')
            || str_contains($key, 'servis')
            || str_contains($key, 'tolko razmery')
            || str_contains($key, 'list14');
    }

    private function cell($sheet, int $col, int $row): string
    {
        return $this->clean((string) $sheet->getCell(Coordinate::stringFromColumnIndex($col) . $row)->getFormattedValue());
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

    private function isHeaderLike(string $value): bool
    {
        $key = $this->nameKey($value);

        return $key === ''
            || $key === '0'
            || str_contains($key, 'model')
            || str_contains($key, 'rezh')
            || str_contains($key, 'moshnost')
            || str_contains($key, 'rrc')
            || str_contains($key, 'nalichie')
            || str_contains($key, 'ustanovka')
            || str_contains($key, 'proizvoditelnost')
            || str_contains($key, 'otaplivaemaia');
    }

    private function isHttpUrl(string $value): bool
    {
        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
    }

    private function normArticle(string $value): string
    {
        $value = mb_strtoupper(trim($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function compactArticle(string $value): string
    {
        return preg_replace('/[^A-ZА-ЯЁ0-9]+/u', '', $this->normArticle($value)) ?? '';
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
        $value = str_replace(['ё', '"', "'", '«', '»', '+'], ['е', '', '', '', '', ' plus '], $value);
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

        foreach (['водонагреватель', 'конвектор', 'газовая', 'колонка', 'электрический', 'котел', 'котёл'] as $word) {
            $name = trim(preg_replace('/\b' . preg_quote($word, '/') . '\b/u', ' ', $name) ?? $name);
        }

        return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
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
                'timeout' => 120,
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

<?php

namespace App\Console\Commands;

use App\Models\SupplierReviewDecision;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SyncBaniaPricelistCommand extends Command
{
    protected $signature = 'supplier:sync-bania-pricelist
        {--dry-run : Preview changes without writing to the database}
        {--apply : Update BANIA supplier_products from the price list}
        {--price-file= : Path to a local XLSX/CSV file}
        {--sheet-url= : Google Sheets URL to download}
        {--retail-price-file= : Path to a local BANIA retail XLSX/CSV file}
        {--retail-sheet-url= : Google Sheets URL with BANIA retail prices}
        {--sync-retail-prices : Update products.price from the retail price list when a confident row is found}
        {--create-missing-products : Create BANIA products from wholesale price-list rows that have no supplier_products link}
        {--limit= : Process only the first N price rows}
        {--mark-missing-out-of-stock : Mark linked BANIA rows missing from the price list as out_of_stock}
        {--archive-missing-products : Archive products whose BANIA wholesale rows disappeared and which have no other supplier links}';

    protected $description = 'Sync BANIA supplier cost and stock from the dynamic Google price list without changing products.price.';

    private const SUPPLIER_CODE = 'bania';
    private const DEFAULT_SHEET_URL = 'https://docs.google.com/spreadsheets/d/1R2qoKV_NKlOAwaBb5dC58CjRawHXJGGX/edit?gid=1105454588#gid=1105454588';
    private const DEFAULT_RETAIL_SHEET_URL = 'https://docs.google.com/spreadsheets/d/1tdKCGzoMoeYQngx2ggeI9DifxHKSDMPc/edit?gid=886304601#gid=886304601';
    private const CACHE_PATH = 'supplier-cache/bania-pricelist.xlsx';
    private const RETAIL_CACHE_PATH = 'supplier-cache/bania-retail-pricelist.xlsx';
    private const PRODUCTION_BRANDS = [
        'Везувий',
        'Теплодар',
        'TMF',
        'Термофор',
        'Everest',
        'Эверест',
        'Этна',
        'ЭТНА',
    ];
    private const ALLOWED_CATEGORY_SLUGS = [
        'pechki',
        'pechi',
        'pechi-kaminy',
        'kaminy',
        'topki',
        'kotly',
        'kotly-na-drovah',
        'belorusskie-kotly',
        'kombinirovannye-kotly',
        'kotly-na-ugle',
        'kotly-na-pelletah',
        'pechnoe-i-kaminnoe-lite',
        'dveri-dlya-ban-i-saun',
        'aksessuary-dlya-bani',
        'kaminnye-nabory',
        'mangaly',
    ];
    private const ALLOWED_CATEGORY_NAMES = [
        'Отделка для парной',
        'Камни для печей',
        'Камни для бани',
        'Аксессуары для бани',
        'Обливные устройства для бани',
        'Вентиляционные клапаны и решётки для бани',
        'Дровницы и каминные принадлежности',
        'Каминные наборы',
        'Мангалы',
        'Казаны',
        'Печи для казана',
        'Комплектующие для мангала',
        'Керамические грили',
        'Мобильная баня',
    ];

    private array $reportRows = [];
    private array $manualRows = [];
    private array $appliedManualLinkIndex = [];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $dryRun = (bool) $this->option('dry-run') || ! $apply;
        $syncRetailPrices = (bool) $this->option('sync-retail-prices');
        $createMissingProducts = (bool) $this->option('create-missing-products');
        $limit = $this->option('limit') !== null ? max(0, (int) $this->option('limit')) : null;

        $this->line($dryRun
            ? '<fg=yellow;options=bold>DRY RUN: database will not be changed.</>'
            : '<fg=red;options=bold>APPLY: supplier_products stock/cost will be updated.</>');

        $supplier = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->first(['id', 'name']);
        if (! $supplier) {
            $this->error('BANIA supplier is not registered.');
            return self::FAILURE;
        }

        try {
            $priceFile = $this->resolvePriceFile();
        } catch (\Throwable $e) {
            $this->error('Price file download failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (! $priceFile || ! file_exists($priceFile)) {
            $this->error('Price file not found.');
            return self::FAILURE;
        }

        try {
            $rows = $this->readPriceRows($priceFile);
        } catch (\Throwable $e) {
            $this->error('Failed to read price list: ' . $e->getMessage());
            return self::FAILURE;
        }

        $retailIndexes = ['article' => [], 'rows' => []];
        try {
            $retailFile = $this->resolveRetailPriceFile();
            if ($retailFile && file_exists($retailFile)) {
                $retailRows = $this->readRetailRows($retailFile);
                $retailIndexes = $this->buildRetailIndexes($retailRows);
                $this->info('BANIA retail price rows loaded: ' . count($retailRows));
            }
        } catch (\Throwable $e) {
            $this->warn('Retail price list was not loaded: ' . $e->getMessage());
        }

        if ($limit !== null && $limit > 0) {
            $rows = array_slice($rows, 0, $limit);
        }

        $supplierProducts = $this->loadBaniaSupplierProducts((int) $supplier->id);
        $supplierProductsTotal = count($supplierProducts);
        $supplierProducts = array_values(array_filter(
            $supplierProducts,
            fn (object $supplierProduct): bool => $this->isAllowedSupplierProduct($supplierProduct)
        ));
        $this->appliedManualLinkIndex = $this->loadAppliedManualLinkIndex();
        $indexes = $this->buildIndexes($supplierProducts);
        $now = now();
        $matchedSupplierProductIds = [];
        $changedProductIds = [];
        $stats = [
            'price_rows' => count($rows),
            'matched' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'manual_review' => 0,
            'cost_above_retail' => 0,
            'skipped_unrelated' => 0,
            'skipped_empty_price' => 0,
            'errors' => 0,
            'missing_marked_out_of_stock' => 0,
            'retail_price_synced' => 0,
            'retail_price_suggested' => 0,
            'retail_price_missing' => 0,
            'retail_price_skipped_manual_review' => 0,
            'manual_review_resolved' => 0,
            'missing_archived' => 0,
            'missing_kept_other_suppliers' => 0,
            'restored_from_archive' => 0,
            'created_from_price_list' => 0,
            'create_missing_candidate' => 0,
            'create_missing_skipped_out_of_stock' => 0,
            'create_missing_skipped_empty_price' => 0,
            'create_missing_skipped_no_retail' => 0,
            'create_missing_skipped_duplicate_article' => 0,
        ];

        foreach ($rows as $row) {
            try {
                $match = $this->matchRow($row, $indexes, $supplierProducts);
                if (($match['action'] ?? '') === 'manual_review') {
                    if ($this->wasManualLinkApproved($row, $match)) {
                        $stats['manual_review_resolved']++;
                        continue;
                    }

                    $stats['manual_review']++;
                    $match['retail_match'] = $this->matchRetailForPriceRow($row, $retailIndexes, $match['supplier_product'] ?? null);

                    if (($match['retail_match']['price'] ?? null) !== null) {
                        $stats['retail_price_suggested']++;
                    } else {
                        $stats['retail_price_skipped_manual_review']++;
                    }

                    $this->addManualRow($row, $match);
                    $this->addReportRow($row, $match, 'manual_review');
                    continue;
                }

                if (($match['action'] ?? '') === 'skipped_unrelated') {
                    if ($createMissingProducts) {
                        $createResult = $this->createMissingProductFromPriceRow($row, $retailIndexes, (int) $supplier->id, $dryRun, $now);
                        $stats[$createResult['stat']]++;

                        if (! empty($createResult['product_id'])) {
                            $changedProductIds[(int) $createResult['product_id']] = true;
                        }

                        if (! empty($createResult['supplier_product_id'])) {
                            $matchedSupplierProductIds[(int) $createResult['supplier_product_id']] = true;
                        }

                        continue;
                    }

                    $stats['skipped_unrelated']++;
                    continue;
                }

                $supplierProduct = $match['supplier_product'] ?? null;
                if (! $supplierProduct) {
                    $stats['manual_review']++;
                    $this->addManualRow($row, $match + ['reason' => 'no confident supplier_product match']);
                    $this->addReportRow($row, $match, 'manual_review');
                    continue;
                }

                if (isset($matchedSupplierProductIds[(int) $supplierProduct->id])) {
                    if ($this->wasManualLinkApproved($row, $match)) {
                        $stats['manual_review_resolved']++;
                        continue;
                    }

                    $stats['manual_review']++;
                    $duplicateMatch = array_merge($match, ['reason' => 'price list contains another row for the same BANIA supplier_product']);
                    $duplicateMatch['retail_match'] = $this->matchRetailForPriceRow($row, $retailIndexes, $supplierProduct);
                    if (($duplicateMatch['retail_match']['price'] ?? null) !== null) {
                        $stats['retail_price_suggested']++;
                    } else {
                        $stats['retail_price_skipped_manual_review']++;
                    }
                    $this->addManualRow($row, $duplicateMatch);
                    $this->addReportRow($row, $duplicateMatch, 'manual_review');
                    continue;
                }

                $matchedSupplierProductIds[(int) $supplierProduct->id] = true;
                $stats['matched']++;
                $retailMatch = $this->matchRetailForPriceRow($row, $retailIndexes, $supplierProduct);
                $match['retail_match'] = $retailMatch;

                if ($row['price'] === null || $row['price'] <= 0) {
                    $stats['skipped_empty_price']++;
                    $this->addReportRow($row, $match, 'skipped_empty_price');
                    continue;
                }

                if ($this->supplierCostAboveRetail($supplierProduct, (float) $row['price'], $retailMatch['price'] ?? null)) {
                    $stats['cost_above_retail']++;
                    $reviewMatch = array_merge($match, [
                        'reason' => sprintf(
                            'supplier cost %.2f is above product retail %.2f; check price-list match or retail price',
                            (float) $row['price'],
                            (float) $supplierProduct->product_price
                        ),
                    ]);
                    $this->addManualRow($row, $reviewMatch);
                    $this->addReportRow($row, $reviewMatch, 'cost_above_retail');
                    continue;
                }

                $newInStock = $this->isAvailableStock($row['stock_status']);
                $changed = $this->hasSupplierProductChanges($supplierProduct, $row, $newInStock);
                $retailChanged = $this->hasRetailPriceChange($supplierProduct, $retailMatch);
                if (! $changed) {
                    if (! $syncRetailPrices || ! $retailChanged) {
                        $stats['unchanged']++;
                        if (($retailMatch['price'] ?? null) !== null) {
                            $stats['retail_price_suggested']++;
                        } else {
                            $stats['retail_price_missing']++;
                        }
                        $this->addReportRow($row, $match, 'unchanged');
                        continue;
                    }
                }

                if (! $dryRun) {
                    if ($changed) {
                        $this->updateSupplierProduct($supplierProduct, $row, $newInStock, $now);
                    }
                    if ($syncRetailPrices && $retailChanged) {
                        $this->updateProductRetailPrice($supplierProduct, $retailMatch, $now);
                        $stats['retail_price_synced']++;
                    }
                    if ($supplierProduct->product_id) {
                        $stats['restored_from_archive'] += $this->restoreProductFromArchive((int) $supplierProduct->product_id, $now);
                        $this->refreshProductAvailability((int) $supplierProduct->product_id, $now);
                    }
                }

                if ($supplierProduct->product_id) {
                    $changedProductIds[(int) $supplierProduct->product_id] = true;
                }

                $stats['updated']++;
                if (($retailMatch['price'] ?? null) !== null) {
                    $stats['retail_price_suggested']++;
                } else {
                    $stats['retail_price_missing']++;
                }
                $this->addReportRow($row, $match, $syncRetailPrices && $retailChanged ? 'supplier_cost_stock_retail_updated' : 'supplier_cost_stock_updated');
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->addReportRow($row, ['reason' => $e->getMessage()], 'error');
            }
        }

        $markMissingOutOfStock = (bool) $this->option('mark-missing-out-of-stock');
        $archiveMissingProducts = (bool) $this->option('archive-missing-products');

        if ($markMissingOutOfStock || $archiveMissingProducts) {
            foreach ($supplierProducts as $supplierProduct) {
                if (isset($matchedSupplierProductIds[(int) $supplierProduct->id])) {
                    continue;
                }

                if (! $dryRun) {
                    if ($markMissingOutOfStock && (bool) $supplierProduct->in_stock) {
                        DB::table('supplier_products')->where('id', $supplierProduct->id)->update([
                            'in_stock' => false,
                            'stock_quantity' => 0,
                            'stock_status' => 'out_of_stock',
                            'stock_text' => 'missing from BANIA Google price list',
                            'last_stock_synced_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    if ($supplierProduct->product_id) {
                        if ($archiveMissingProducts) {
                            if ($this->archiveProductIfOnlyBania((int) $supplierProduct->product_id, (int) $supplierProduct->id, $now)) {
                                $stats['missing_archived']++;
                            } else {
                                $stats['missing_kept_other_suppliers']++;
                            }
                        }
                        $this->refreshProductAvailability((int) $supplierProduct->product_id, $now);
                    }
                }

                if ($supplierProduct->product_id) {
                    $changedProductIds[(int) $supplierProduct->product_id] = true;
                }
                $stats['missing_marked_out_of_stock']++;
                $this->addReportRowFromSupplierProduct($supplierProduct, 'missing_marked_out_of_stock');
            }
        }

        $this->writeReports();

        $this->table(['metric', 'count'], array_map(
            fn (string $key, int $value) => [$key, $value],
            array_keys($stats),
            $stats
        ));

        $this->info('Products with recalculated availability: ' . count($changedProductIds));
        $this->info(sprintf('BANIA supplier_products in allowed sync scope: %d of %d', count($supplierProducts), $supplierProductsTotal));
        $this->warn($syncRetailPrices
            ? 'products.price may be updated only from the BANIA retail price list. The wholesale price-list column "OPT with VAT" remains supplier purchase cost.'
            : 'products.price was not updated. Use --sync-retail-prices to apply confirmed BANIA retail price-list values.');

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolvePriceFile(): ?string
    {
        $priceFile = $this->option('price-file');
        if ($priceFile) {
            return (string) $priceFile;
        }

        $url = (string) ($this->option('sheet-url') ?: self::DEFAULT_SHEET_URL);
        return $this->downloadGoogleSheet($url, self::CACHE_PATH, 'Google wholesale price list');
    }

    private function resolveRetailPriceFile(): ?string
    {
        $priceFile = $this->option('retail-price-file');
        if ($priceFile) {
            return (string) $priceFile;
        }

        $url = (string) ($this->option('retail-sheet-url') ?: self::DEFAULT_RETAIL_SHEET_URL);
        return $this->downloadGoogleSheet($url, self::RETAIL_CACHE_PATH, 'Google retail price list');
    }

    private function downloadGoogleSheet(string $url, string $cachePath, string $label): string
    {
        $exportUrl = $this->toExportUrl($url);
        $path = storage_path('app/' . $cachePath);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", [
                    'User-Agent: Mozilla/5.0 (compatible; KotlovBot/1.0)',
                    'Accept: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,*/*',
                ]),
                'timeout' => 45,
                'follow_location' => 1,
                'max_redirects' => 10,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        try {
            $content = @file_get_contents($exportUrl, false, $context);
            if ($content === false || strlen($content) < 1000) {
                throw new \RuntimeException('Could not download Google Sheet export.');
            }

            if (str_starts_with(ltrim($content), '<') || stripos($content, '<html') !== false) {
                throw new \RuntimeException('Google Sheet export returned HTML instead of XLSX.');
            }

            $tmpPath = $path . '.download.xlsx';
            file_put_contents($tmpPath, $content);

            try {
                $this->assertReadableSpreadsheet($tmpPath);
            } catch (\Throwable $e) {
                @unlink($tmpPath);
                throw new \RuntimeException('Google Sheet export is not a readable XLSX: ' . $e->getMessage());
            }

            rename($tmpPath, $path);
            $this->line('Downloaded ' . $label . ': ' . $path);

            return $path;
        } catch (\Throwable $e) {
            if (file_exists($path)) {
                try {
                    $this->assertReadableSpreadsheet($path);
                    $this->warn($label . ' download failed; using previous cached file: ' . $path);

                    return $path;
                } catch (\Throwable) {
                    // Cached file is broken too; report the original download error.
                }
            }

            throw $e;
        }
    }

    private function assertReadableSpreadsheet(string $path): void
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $spreadsheet->disconnectWorksheets();
    }

    private function toExportUrl(string $url): string
    {
        if (str_contains($url, '/export?')) {
            $parts = parse_url($url);
            parse_str((string) ($parts['query'] ?? ''), $query);
            $query['format'] = 'xlsx';
            unset($query['gid']);

            return sprintf(
                '%s://%s%s?%s',
                $parts['scheme'] ?? 'https',
                $parts['host'] ?? 'docs.google.com',
                $parts['path'] ?? '',
                http_build_query($query)
            );
        }

        if (! preg_match('~/spreadsheets/d/([^/]+)~', $url, $matches)) {
            return $url;
        }

        return sprintf('https://docs.google.com/spreadsheets/d/%s/export?format=xlsx', $matches[1]);
    }

    private function readPriceRows(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rawRows = $sheet->toArray(null, true, true, false);

        $rows = [];
        $seen = [];

        foreach ($rawRows as $index => $row) {
            $name = $this->cell($row, 0);
            $article = $this->cell($row, 2);
            $price = $this->parseMoney($this->cell($row, 5));
            $stockText = $this->cell($row, 6);

            if ($index < 5 || $name === '' || str_contains(mb_strtolower($name), 'номенклатура')) {
                continue;
            }

            $normalizedName = $this->normalizeName($name);
            if ($normalizedName === '') {
                continue;
            }

            $key = $this->normalizeArticle($article) ?: sha1($normalizedName . '|' . $price . '|' . $stockText);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $rows[] = [
                'row' => $index + 1,
                'name' => $name,
                'article' => $article,
                'norm_article' => $this->normalizeArticle($article),
                'price' => $price,
                'stock_text' => $stockText,
                'stock_status' => $this->stockStatus($stockText),
                'normalized_name' => $normalizedName,
            ];
        }

        return $rows;
    }

    private function readRetailRows(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rawRows = $sheet->toArray(null, true, true, false);

        $rows = [];
        $seen = [];

        foreach ($rawRows as $index => $row) {
            if ($index < 3) {
                continue;
            }

            $parsed = $this->parseRetailRow($row, $index + 1);
            if (! $parsed) {
                continue;
            }

            $key = $parsed['norm_article'] ?: sha1($parsed['normalized_name'] . '|' . $parsed['price']);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $rows[] = $parsed;
        }

        return $rows;
    }

    private function parseRetailRow(array $row, int $rowNumber): ?array
    {
        $cells = array_values(array_map(fn ($value): string => trim((string) $value), $row));
        $joined = mb_strtolower(implode(' ', $cells));
        if ($joined === '' || str_contains($joined, 'номенклатура') || str_contains($joined, 'рознич')) {
            return null;
        }

        $name = $this->cell($cells, 0);
        if ($name === '') {
            $name = $this->firstTextCell($cells);
        }

        $article = $this->cell($cells, 1);
        if ($this->normalizeArticle($article) === '') {
            $article = $this->cell($cells, 2);
        }
        if ($this->normalizeArticle($article) === '') {
            $article = $this->firstArticleCell($cells);
        }

        $price = $this->lastMoneyCell($cells);
        $normalizedName = $this->normalizeName($name);
        if ($normalizedName === '' || $price === null || $price <= 0) {
            return null;
        }

        return [
            'row' => $rowNumber,
            'name' => $name,
            'article' => $article,
            'norm_article' => $this->normalizeArticle($article),
            'price' => $price,
            'normalized_name' => $normalizedName,
        ];
    }

    private function buildRetailIndexes(array $rows): array
    {
        $byArticle = [];
        foreach ($rows as $row) {
            if ($row['norm_article'] !== '') {
                $byArticle[$row['norm_article']][] = $row;
            }
        }

        return ['article' => $byArticle, 'rows' => $rows];
    }

    private function loadBaniaSupplierProducts(int $supplierId): array
    {
        return DB::table('supplier_products as sp')
            ->leftJoin('products as p', 'p.id', '=', 'sp.product_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->where('sp.supplier_id', $supplierId)
            ->select([
                'sp.id',
                'sp.product_id',
                'sp.product_sku',
                'sp.supplier_article',
                'sp.supplier_article_normalized',
                'sp.supplier_name',
                'sp.source_url',
                'sp.price',
                'sp.price_byn',
                'sp.in_stock',
                'sp.stock_quantity',
                'sp.stock_status',
                'sp.stock_text',
                'sp.raw',
                'p.sku as catalog_product_sku',
                'p.name as product_name',
                'p.price as product_price',
                'p.in_stock as product_in_stock',
                'b.name as brand_name',
                'c.slug as category_slug',
                'c.name as category_name',
            ])
            ->get()
            ->all();
    }

    private function buildIndexes(array $supplierProducts): array
    {
        $byArticle = [];
        foreach ($supplierProducts as $supplierProduct) {
            foreach ([$supplierProduct->supplier_article_normalized, $supplierProduct->supplier_article] as $article) {
                $norm = $this->normalizeArticle((string) $article);
                if ($norm === '') {
                    continue;
                }
                $byArticle[$norm][] = $supplierProduct;
            }
        }

        return ['article' => $byArticle];
    }

    private function matchRow(array $row, array $indexes, array $supplierProducts): array
    {
        if ($row['norm_article'] !== '' && isset($indexes['article'][$row['norm_article']])) {
            $best = $this->bestCandidate($row, $indexes['article'][$row['norm_article']]);
            if ($best['score'] >= 55) {
                return [
                    'supplier_product' => $best['supplier_product'],
                    'match_type' => 'article',
                    'confidence' => $best['score'],
                    'reason' => '',
                ];
            }

            return [
                'action' => 'manual_review',
                'match_type' => 'article_ambiguous',
                'confidence' => $best['score'],
                'supplier_product' => $best['supplier_product'] ?? null,
                'reason' => 'article matched but title compatibility is low',
            ];
        }

        $best = $this->bestCandidate($row, $supplierProducts);
        if ($best['score'] >= 95) {
            return [
                'supplier_product' => $best['supplier_product'],
                'match_type' => 'title',
                'confidence' => $best['score'],
                'reason' => '',
            ];
        }

        if ($best['score'] >= 80 && $this->needsSupplierCostRepair($best['supplier_product'] ?? null)) {
            return [
                'supplier_product' => $best['supplier_product'],
                'match_type' => 'title_repair_equal_retail',
                'confidence' => $best['score'],
                'reason' => 'supplier cost equals product retail; repairing from BANIA price list',
            ];
        }

        if ($best['score'] >= 70 && $this->canRepairSaunaStoveCostByTitle($row, $best)) {
            return [
                'supplier_product' => $best['supplier_product'],
                'match_type' => 'title_repair_sauna_stove_equal_retail',
                'confidence' => $best['score'],
                'reason' => 'sauna stove supplier cost equals retail and has no price-list link; repairing from BANIA price list',
            ];
        }

        if ($best['score'] >= 72) {
            return [
                'action' => 'manual_review',
                'match_type' => 'title_possible',
                'confidence' => $best['score'],
                'supplier_product' => $best['supplier_product'],
                'reason' => 'similar title requires manual approval',
            ];
        }

        return [
            'action' => 'skipped_unrelated',
            'match_type' => 'not_found',
            'confidence' => $best['score'] ?? 0,
            'supplier_product' => $best['supplier_product'] ?? null,
            'reason' => 'no linked BANIA supplier product found',
        ];
    }

    private function matchRetailForPriceRow(array $row, array $indexes, ?object $supplierProduct = null): ?array
    {
        $articles = array_values(array_filter(array_unique([
            $this->normalizeArticle((string) ($row['norm_article'] ?? '')),
            $this->normalizeArticle((string) ($row['article'] ?? '')),
        ])));

        foreach ($articles as $article) {
            $candidates = $indexes['article'][$article] ?? [];
            if ($candidates === []) {
                continue;
            }

            $best = null;
            $bestScore = 0;
            foreach ($candidates as $candidate) {
                $score = $this->candidateScore((string) ($row['normalized_name'] ?? ''), (string) $candidate['normalized_name']);
                if ($score > $bestScore) {
                    $best = $candidate;
                    $bestScore = $score;
                }
            }

            if ($best) {
                return $best + [
                    'match_type' => 'retail_price_article',
                    'confidence' => max($bestScore, 95),
                ];
            }
        }

        return $supplierProduct ? $this->matchRetailForSupplierProduct($supplierProduct, $indexes) : null;
    }

    private function matchRetailForSupplierProduct(object $supplierProduct, array $indexes): ?array
    {
        $articles = array_values(array_filter(array_unique([
            $this->normalizeArticle((string) ($supplierProduct->supplier_article_normalized ?? '')),
            $this->normalizeArticle((string) ($supplierProduct->supplier_article ?? '')),
        ])));

        $candidates = [];
        foreach ($articles as $article) {
            foreach ($indexes['article'][$article] ?? [] as $row) {
                $candidates[] = $row + ['match_type' => 'retail_article'];
            }
        }

        if ($candidates === []) {
            $candidates = array_map(
                fn (array $row): array => $row + ['match_type' => 'retail_title'],
                $indexes['rows'] ?? []
            );
        }

        $needle = $this->normalizeName(trim((string) ($supplierProduct->supplier_name ?? '') . ' ' . (string) ($supplierProduct->product_name ?? '')));
        $best = null;
        $bestScore = 0;

        foreach ($candidates as $candidate) {
            $score = $this->candidateScore($needle, (string) $candidate['normalized_name']);
            if (($candidate['match_type'] ?? '') === 'retail_article') {
                $score = max($score, 85);
            }

            if ($score > $bestScore) {
                $best = $candidate;
                $bestScore = $score;
            }
        }

        if (! $best || $bestScore < 72) {
            return null;
        }

        return $best + ['confidence' => $bestScore];
    }

    private function bestCandidate(array $row, array $candidates): array
    {
        $best = ['supplier_product' => null, 'score' => 0];
        foreach ($candidates as $candidate) {
            $score = max(
                $this->candidateScore($row['normalized_name'], (string) $candidate->supplier_name),
                $this->candidateScore($row['normalized_name'], (string) $candidate->product_name)
            );

            if ($score > $best['score']) {
                $best = ['supplier_product' => $candidate, 'score' => $score];
            }
        }

        return $best;
    }

    private function needsSupplierCostRepair(?object $supplierProduct): bool
    {
        if (! $supplierProduct || $supplierProduct->price_byn === null || $supplierProduct->product_price === null) {
            return false;
        }

        return abs((float) $supplierProduct->price_byn - (float) $supplierProduct->product_price) < 0.01;
    }

    private function supplierCostAboveRetail(object $supplierProduct, float $newSupplierCost, ?float $suggestedRetailPrice = null): bool
    {
        if ($newSupplierCost <= 0 || $supplierProduct->product_price === null) {
            return false;
        }

        $retail = (float) $supplierProduct->product_price;
        if ($retail <= 0) {
            return false;
        }

        if ($suggestedRetailPrice !== null && $suggestedRetailPrice >= $newSupplierCost - 0.01) {
            return false;
        }

        return $newSupplierCost > $retail + 0.01;
    }

    private function hasRetailPriceChange(object $supplierProduct, ?array $retailMatch): bool
    {
        if (! $retailMatch || ! $supplierProduct->product_id || empty($retailMatch['price'])) {
            return false;
        }

        $newRetail = (float) $retailMatch['price'];
        if ($newRetail <= 0) {
            return false;
        }

        $oldRetail = $supplierProduct->product_price !== null ? (float) $supplierProduct->product_price : null;
        return $oldRetail === null || abs($oldRetail - $newRetail) > 0.01;
    }

    private function updateProductRetailPrice(object $supplierProduct, array $retailMatch, $now): void
    {
        if (! $supplierProduct->product_id || empty($retailMatch['price'])) {
            return;
        }

        DB::table('products')->where('id', $supplierProduct->product_id)->update([
            'price' => $retailMatch['price'],
            'updated_at' => $now,
        ]);
    }

    private function retailPriceAction(?object $supplierProduct, ?array $retailMatch): string
    {
        if (! $supplierProduct || ! $supplierProduct->product_id) {
            return 'retail_skipped_no_product';
        }

        if (! $retailMatch || empty($retailMatch['price'])) {
            return 'retail_price_missing';
        }

        $newRetail = (float) $retailMatch['price'];
        $oldRetail = $supplierProduct->product_price !== null ? (float) $supplierProduct->product_price : null;
        $supplierCost = $supplierProduct->price_byn !== null ? (float) $supplierProduct->price_byn : null;

        if ($supplierCost !== null && $oldRetail !== null && $oldRetail < $supplierCost - 0.01) {
            return 'retail_current_below_cost';
        }

        if ($oldRetail !== null && abs($oldRetail - $newRetail) <= 0.01) {
            return 'retail_price_unchanged';
        }

        return 'retail_price_can_sync';
    }

    private function canRepairSaunaStoveCostByTitle(array $row, array $best): bool
    {
        $supplierProduct = $best['supplier_product'] ?? null;
        if (! $this->needsSupplierCostRepair($supplierProduct) || ! $this->isSaunaStoveSupplierProduct($supplierProduct)) {
            return false;
        }

        $raw = json_decode((string) ($supplierProduct->raw ?? ''), true);
        if (is_array($raw) && ! empty($raw['google_price_list'])) {
            return false;
        }

        $priceNumbers = $this->modelNumbers((string) ($row['normalized_name'] ?? ''));
        $candidateNumbers = array_values(array_unique(array_merge(
            $this->modelNumbers($this->normalizeName((string) ($supplierProduct->supplier_name ?? ''))),
            $this->modelNumbers($this->normalizeName((string) ($supplierProduct->product_name ?? '')))
        )));

        if ($priceNumbers !== [] && $candidateNumbers !== [] && array_intersect($priceNumbers, $candidateNumbers) === []) {
            return false;
        }

        return true;
    }

    private function isSaunaStoveSupplierProduct(?object $supplierProduct): bool
    {
        if (! $supplierProduct) {
            return false;
        }

        return in_array((string) ($supplierProduct->category_slug ?? ''), [
            'drovyanye-pechi-dlya-bani',
            'pechi-dlya-bani',
            'dlya-bani',
            'bani-i-sauny',
            'elektrokamenki',
            'pechi-sauna',
            'pechi-kamenka',
        ], true);
    }

    private function candidateScore(string $priceName, string $candidateName): int
    {
        $candidateName = $this->normalizeName($candidateName);
        $score = $this->similarity($priceName, $candidateName);

        if ($this->hasDimensionConflict($priceName, $candidateName)) {
            return min($score, 45);
        }

        $priceNumbers = $this->modelNumbers($priceName);
        $candidateNumbers = $this->modelNumbers($candidateName);
        if ($priceNumbers !== [] && $candidateNumbers !== [] && array_intersect($priceNumbers, $candidateNumbers) === []) {
            return min($score, 70);
        }

        if ($this->hasQualifierConflict($priceName, $candidateName)) {
            return min($score, 78);
        }

        return $score;
    }

    private function hasDimensionConflict(string $left, string $right): bool
    {
        $leftDimensions = $this->extractDimensions($left);
        $rightDimensions = $this->extractDimensions($right);

        if ($leftDimensions === [] || $rightDimensions === []) {
            return false;
        }

        return $leftDimensions !== $rightDimensions;
    }

    private function extractDimensions(string $value): array
    {
        preg_match_all('/\b(\d{2,4})\s*[xх×]\s*(\d{2,4})\b/u', $value, $matches, PREG_SET_ORDER);

        $dimensions = [];
        foreach ($matches as $match) {
            $left = (int) ($match[1] ?? 0);
            $right = (int) ($match[2] ?? 0);

            if ($left <= 0 || $right <= 0) {
                continue;
            }

            $pair = [$left, $right];
            sort($pair);
            $dimensions[] = implode('x', $pair);
        }

        sort($dimensions);

        return array_values(array_unique($dimensions));
    }

    private function hasQualifierConflict(string $left, string $right): bool
    {
        foreach (['inox', 'стекл', 'бак', 'панорам', 'long', 'сетка', 'чугун', 'galaxy', 'heat', 'master'] as $token) {
            if (str_contains($left, $token) !== str_contains($right, $token)) {
                return true;
            }
        }

        return false;
    }

    private function modelNumbers(string $name): array
    {
        preg_match_all('/\b\d{1,3}(?:[.,]\d+)?\b/u', $name, $matches);
        $numbers = [];
        foreach ($matches[0] ?? [] as $match) {
            $number = (float) str_replace(',', '.', $match);
            if ($number > 0 && $number <= 100) {
                $numbers[] = (string) rtrim(rtrim((string) $number, '0'), '.');
            }
        }

        return array_values(array_unique($numbers));
    }

    private function updateSupplierProduct(object $supplierProduct, array $row, bool $inStock, $now): void
    {
        $raw = json_decode((string) $supplierProduct->raw, true);
        if (! is_array($raw)) {
            $raw = [];
        }

        $raw['google_price_list'] = [
            'row' => $row['row'],
            'name' => $row['name'],
            'article' => $row['article'],
            'price_column' => 'OPT with VAT',
            'price_is_supplier_cost' => true,
            'stock_text' => $row['stock_text'],
        ];

        DB::table('supplier_products')->where('id', $supplierProduct->id)->update([
            'price' => $row['price'],
            'currency' => 'BYN',
            'currency_rate' => 1,
            'price_byn' => $row['price'],
            'in_stock' => $inStock,
            'stock_quantity' => $inStock ? 1 : 0,
            'stock_status' => $row['stock_status'],
            'stock_text' => $row['stock_text'] !== '' ? $row['stock_text'] : null,
            'raw' => json_encode($raw, JSON_UNESCAPED_UNICODE),
            'last_stock_synced_at' => $now,
            'last_synced_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function hasSupplierProductChanges(object $supplierProduct, array $row, bool $newInStock): bool
    {
        $oldPrice = $supplierProduct->price_byn !== null ? (float) $supplierProduct->price_byn : null;
        if ($oldPrice === null || abs($oldPrice - (float) $row['price']) > 0.01) {
            return true;
        }

        if ((bool) $supplierProduct->in_stock !== $newInStock) {
            return true;
        }

        return (string) $supplierProduct->stock_status !== $row['stock_status'];
    }

    private function refreshProductAvailability(int $productId, $now): void
    {
        $inStock = DB::table('supplier_products as sp')
            ->join('suppliers as s', 's.id', '=', 'sp.supplier_id')
            ->where('sp.product_id', $productId)
            ->where('s.is_active', true)
            ->where('sp.in_stock', true)
            ->exists();

        DB::table('products')->where('id', $productId)->update([
            'in_stock' => $inStock,
            'availability_status' => $inStock ? Product::AVAILABILITY_IN_STOCK : Product::AVAILABILITY_CHECK,
            'updated_at' => $now,
        ]);
    }

    private function archiveProductIfOnlyBania(int $productId, int $baniaSupplierProductId, $now): bool
    {
        $hasOtherSupplierLinks = DB::table('supplier_products')
            ->where('product_id', $productId)
            ->where('id', '<>', $baniaSupplierProductId)
            ->exists();

        if ($hasOtherSupplierLinks) {
            return false;
        }

        return DB::table('products')->where('id', $productId)->update([
            'is_archived' => true,
            'is_active' => false,
            'in_stock' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
            'updated_at' => $now,
        ]) > 0;
    }

    private function restoreProductFromArchive(int $productId, $now): int
    {
        return DB::table('products')
            ->where('id', $productId)
            ->where('is_archived', true)
            ->update([
                'is_archived' => false,
                'is_active' => true,
                'updated_at' => $now,
            ]);
    }

    private function createMissingProductFromPriceRow(array $row, array $retailIndexes, int $supplierId, bool $dryRun, $now): array
    {
        if ($row['price'] === null || (float) $row['price'] <= 0) {
            $this->addPriceListCreateRow($row, null, 'create_missing_skipped_empty_price', '', '', 'Wholesale price is empty.');
            return ['stat' => 'create_missing_skipped_empty_price'];
        }

        if (! $this->isAvailableStock($row['stock_status'])) {
            $this->addPriceListCreateRow($row, null, 'create_missing_skipped_out_of_stock', '', '', 'Wholesale price-list row is not available.');
            return ['stat' => 'create_missing_skipped_out_of_stock'];
        }

        $supplierArticle = $this->supplierArticleForPriceRow($row);
        if ($supplierArticle === '') {
            $this->addPriceListCreateRow($row, null, 'create_missing_skipped_duplicate_article', '', '', 'Cannot create supplier row without article.');
            return ['stat' => 'create_missing_skipped_duplicate_article'];
        }

        if (DB::table('supplier_products')->where('supplier_id', $supplierId)->where('supplier_article', $supplierArticle)->exists()) {
            $this->addPriceListCreateRow($row, null, 'create_missing_skipped_duplicate_article', '', '', 'BANIA supplier_product already exists for this article.');
            return ['stat' => 'create_missing_skipped_duplicate_article'];
        }

        $retailMatch = $this->matchRetailForPriceRow($row, $retailIndexes);
        if (! $retailMatch || empty($retailMatch['price'])) {
            $this->addPriceListCreateRow($row, null, 'create_missing_skipped_no_retail', '', '', 'Retail price was not found by article.');
            return ['stat' => 'create_missing_skipped_no_retail'];
        }

        $categoryId = $this->resolveCategoryIdForPriceRow($row);
        $brandId = $this->resolveBrandIdForPriceRow($row, $now);
        $productName = trim((string) $row['name']);
        $productSku = $this->nextKotlovSku();

        if ($dryRun) {
            $this->addPriceListCreateRow($row, $retailMatch, 'create_missing_candidate', '', $productSku, 'Would create product from BANIA wholesale price-list row.');
            return ['stat' => 'create_missing_candidate'];
        }

        $productId = (int) DB::table('products')->insertGetId([
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'supplier_id' => null,
            'name' => $productName,
            'slug' => $this->uniqueSlug($productName),
            'h1' => $productName,
            'sku' => $productSku,
            'price' => (float) $retailMatch['price'],
            'price_old' => null,
            'currency' => 'BYN',
            'content' => $this->priceListDescription($productName),
            'short_description' => $this->priceListShortDescription($productName),
            'images' => json_encode([], JSON_UNESCAPED_UNICODE),
            'specs' => json_encode([
                'Артикул поставщика' => $supplierArticle,
                'Поставщик' => 'BANIA.by',
            ], JSON_UNESCAPED_UNICODE),
            'unit' => 'шт',
            'warranty' => null,
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => true,
            'availability_status' => Product::AVAILABILITY_IN_STOCK,
            'stock_qty' => 1,
            'is_featured' => false,
            'is_new' => true,
            'is_sale' => false,
            'sort_order' => 0,
            'meta_title' => $productName . ' купить в %city%',
            'meta_keywords' => $productName . ', BANIA.by',
            'meta_description' => Str::limit(strip_tags($this->priceListShortDescription($productName)), 250, ''),
            'rating' => 0,
            'reviews_count' => 0,
            'views_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $raw = [
            'source' => 'bania_google_wholesale_price_list',
            'needs_enrichment' => true,
            'google_price_list' => [
                'row' => $row['row'],
                'name' => $row['name'],
                'article' => $row['article'],
                'price_column' => 'OPT with VAT',
                'price_is_supplier_cost' => true,
                'stock_text' => $row['stock_text'],
            ],
            'google_retail_price_list' => [
                'row' => $retailMatch['row'] ?? null,
                'name' => $retailMatch['name'] ?? null,
                'article' => $retailMatch['article'] ?? null,
                'price' => $retailMatch['price'] ?? null,
            ],
        ];

        $supplierProductId = (int) DB::table('supplier_products')->insertGetId([
            'supplier_id' => $supplierId,
            'supplier_sync_id' => null,
            'product_id' => $productId,
            'product_sku' => $productSku,
            'supplier_article' => $supplierArticle,
            'supplier_article_normalized' => $this->normalizeArticle($supplierArticle),
            'supplier_name' => $productName,
            'source_url' => null,
            'source_wp_id' => null,
            'price' => (float) $row['price'],
            'currency' => 'BYN',
            'currency_rate' => 1,
            'price_byn' => (float) $row['price'],
            'in_stock' => true,
            'stock_quantity' => 1,
            'stock_status' => $row['stock_status'],
            'stock_text' => $row['stock_text'] !== '' ? $row['stock_text'] : null,
            'match_status' => 'created_from_price_list',
            'match_confidence' => '100',
            'raw' => json_encode($raw, JSON_UNESCAPED_UNICODE),
            'last_stock_synced_at' => $now,
            'last_synced_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->addPriceListCreateRow($row, $retailMatch, 'created_from_price_list', (string) $productId, $productSku, 'Created from BANIA wholesale price-list row.');

        return [
            'stat' => 'created_from_price_list',
            'product_id' => $productId,
            'supplier_product_id' => $supplierProductId,
        ];
    }

    private function supplierArticleForPriceRow(array $row): string
    {
        $article = trim((string) ($row['article'] ?? ''));
        if ($this->normalizeArticle($article) !== '') {
            return $article;
        }

        $rowNumber = (int) ($row['row'] ?? 0);
        return $rowNumber > 0 ? 'BANIA-PRICE-ROW-' . $rowNumber : '';
    }

    private function resolveBrandIdForPriceRow(array $row, $now): int
    {
        $name = (string) ($row['name'] ?? '');
        $brandMap = [
            'doorwood' => ['DoorWood', 'doorwood'],
            'aston' => ['ASTON', 'aston'],
            'harvia' => ['Harvia', 'harvia'],
            'tmf' => ['TMF', 'tmf'],
            'термофор' => ['TMF', 'tmf'],
            'везувий' => ['Везувий', 'vezuvij'],
            'былина' => ['Теплодар', 'teplodar'],
            'сибирский утес' => ['Теплодар', 'teplodar'],
            'сибирский утёс' => ['Теплодар', 'teplodar'],
            'сибирь' => ['НМК', 'nmk'],
            'теплодар' => ['Теплодар', 'teplodar'],
            'эверест' => ['Эверест', 'everest'],
            'everest' => ['Эверест', 'everest'],
            'этна' => ['ЭТНА', 'etna'],
            'факел' => ['Факел', 'fakel'],
        ];

        $normalized = $this->normalizeName($name);
        foreach ($brandMap as $needle => [$brandName, $slug]) {
            if (str_contains($normalized, $this->normalizeName($needle))) {
                return $this->ensureBrand($brandName, $slug, $now);
            }
        }

        return $this->ensureBrand('Банька', 'bania', $now);
    }

    private function ensureBrand(string $name, string $slug, $now): int
    {
        $brand = DB::table('brands')->where('slug', $slug)->orWhere('name', $name)->first(['id']);
        if ($brand) {
            return (int) $brand->id;
        }

        return (int) DB::table('brands')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function resolveCategoryIdForPriceRow(array $row): int
    {
        $name = $this->normalizeName((string) ($row['name'] ?? ''));
        $slug = match (true) {
            str_contains($name, 'электро') || str_contains($name, 'harvia') => 'elektrokamenki',
            str_contains($name, 'дверц') || str_contains($name, 'дверк') || (str_contains($name, 'двер') && str_contains($name, 'печ')) => 'pechnoe-i-kaminnoe-lite',
            (str_contains($name, 'двер') && ! str_contains($name, 'печ')) => 'dveri-dlya-ban-i-saun',
            str_contains($name, 'топк') => 'topki',
            str_contains($name, 'котел') || str_contains($name, 'котёл') || str_contains($name, 'купер') => 'kotly',
            str_contains($name, 'печь камин') || str_contains($name, 'печь-камин') => 'pechi-kaminy',
            str_contains($name, 'мангал') || str_contains($name, 'казан') || str_contains($name, 'грил') || str_contains($name, 'шашлык') => 'mangaly',
            str_contains($name, 'камень') || str_contains($name, 'жадеит') || str_contains($name, 'нефрит') || str_contains($name, 'талько') => 'aksessuary-dlya-bani',
            str_contains($name, 'печь') || str_contains($name, 'пб ') || str_contains($name, 'бан') => 'drovyanye-pechi-dlya-bani',
            default => 'aksessuary-dlya-bani',
        };

        $id = DB::table('categories')->where('slug', $slug)->value('id');
        if ($id) {
            return (int) $id;
        }

        return (int) DB::table('categories')->where('slug', 'aksessuary-dlya-bani')->value('id');
    }

    private function priceListShortDescription(string $productName): string
    {
        return 'Товар доступен к заказу. Актуальная цена и наличие указаны на странице.';
    }

    private function priceListDescription(string $productName): string
    {
        return '<p>' . e($productName) . ' доступен к заказу. Актуальная цена и наличие указаны на странице; дополнительные параметры можно уточнить у менеджера.</p>';
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'bania-product';
        $slug = $base;
        $i = 2;
        while (DB::table('products')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    private function nextKotlovSku(): string
    {
        $max = DB::table('products')
            ->where('sku', 'like', 'KOTLOV-%')
            ->pluck('sku')
            ->map(fn ($sku) => preg_match('/^KOTLOV-(\d+)$/', (string) $sku, $match) ? (int) $match[1] : 0)
            ->max() ?? 0;

        $next = $max + 1;
        do {
            $sku = sprintf('KOTLOV-%06d', $next++);
        } while (DB::table('products')->where('sku', $sku)->exists());

        return $sku;
    }

    private function stockStatus(string $text): string
    {
        $normalized = $this->normalizeText($text);
        if ($normalized === '') {
            return 'out_of_stock';
        }

        if (str_contains($normalized, 'нет') || str_contains($normalized, 'отсутств') || str_contains($normalized, 'снят')) {
            return 'out_of_stock';
        }

        if (str_contains($normalized, 'заказ')) {
            return 'preorder';
        }

        if (str_contains($normalized, 'мало')) {
            return 'low_stock';
        }

        return 'in_stock';
    }

    private function isAvailableStock(string $status): bool
    {
        return in_array($status, ['in_stock', 'low_stock', 'preorder'], true);
    }

    private function parseMoney(string $value): ?float
    {
        if (preg_match('/[A-Za-zА-Яа-яЁё]/u', $value)) {
            return null;
        }

        $normalized = trim(str_replace(["\xc2\xa0", ' '], '', $value));
        $normalized = preg_replace('/[^0-9,.\-]/u', '', $normalized) ?? '';
        if ($normalized === '' || $normalized === '-') {
            return null;
        }

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace(',', '', $normalized);
        } else {
            $normalized = str_replace(',', '.', $normalized);
        }

        return is_numeric($normalized) ? round((float) $normalized, 2) : null;
    }

    private function cell(array $row, int $index): string
    {
        return trim((string) ($row[$index] ?? ''));
    }

    private function firstTextCell(array $cells): string
    {
        foreach ($cells as $cell) {
            $value = trim((string) $cell);
            if ($value !== '' && preg_match('/[A-Za-zА-Яа-яЁё]/u', $value)) {
                return $value;
            }
        }

        return '';
    }

    private function firstArticleCell(array $cells): string
    {
        foreach ($cells as $cell) {
            $value = trim((string) $cell);
            $article = $this->normalizeArticle($value);
            if ($article !== '' && preg_match('/[0-9]/', $article) && mb_strlen($article) >= 4) {
                return $value;
            }
        }

        return '';
    }

    private function lastMoneyCell(array $cells): ?float
    {
        $price = null;
        foreach ($cells as $cell) {
            if (preg_match('/[A-Za-zА-Яа-яЁё]/u', (string) $cell)) {
                continue;
            }

            $value = $this->parseMoney((string) $cell);
            if ($value !== null && $value > 0) {
                $price = $value;
            }
        }

        return $price;
    }

    private function normalizeArticle(string $value): string
    {
        return mb_strtoupper(preg_replace('/[^0-9A-ZА-ЯЁ]+/u', '', trim($value)) ?? '');
    }

    private function normalizeName(string $value): string
    {
        $value = $this->normalizeText($value);
        $value = preg_replace('/\b(печь|для|бани|банная|электрокаменка|каменка|электрическая|дровяная|пб)\b/u', ' ', $value) ?? $value;
        $value = preg_replace('/[^0-9a-zа-яё]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function isProductionBrand(string $brand): bool
    {
        $normalized = $this->normalizeBrand($brand);
        foreach (self::PRODUCTION_BRANDS as $allowedBrand) {
            if ($normalized === $this->normalizeBrand($allowedBrand)) {
                return true;
            }
        }

        return false;
    }

    private function isAllowedSupplierProduct(object $supplierProduct): bool
    {
        if ($this->isProductionBrand((string) ($supplierProduct->brand_name ?? ''))) {
            return true;
        }

        return in_array((string) ($supplierProduct->category_slug ?? ''), self::ALLOWED_CATEGORY_SLUGS, true)
            || in_array((string) ($supplierProduct->category_name ?? ''), self::ALLOWED_CATEGORY_NAMES, true);
    }

    private function normalizeBrand(string $brand): string
    {
        return $this->normalizeName($brand);
    }

    private function normalizeText(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace('ё', 'е', $value);
        $value = str_replace(["\xc2\xa0", '–', '—', '‑'], [' ', '-', '-', '-'], $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function similarity(string $left, string $right): int
    {
        if ($left === '' || $right === '') {
            return 0;
        }

        if ($left === $right) {
            return 100;
        }

        if (str_contains($left, $right) || str_contains($right, $left)) {
            return 92;
        }

        similar_text($left, $right, $percent);
        return (int) round($percent);
    }

    private function addPriceListCreateRow(array $row, ?array $retailMatch, string $action, string $productId, string $productSku, string $note): void
    {
        $this->reportRows[] = [
            'price_row' => $row['row'] ?? '',
            'price_title' => $row['name'] ?? '',
            'price_article' => $row['article'] ?? '',
            'supplier_product_id' => '',
            'product_id' => $productId,
            'product_sku' => $productSku,
            'brand' => '',
            'supplier_title' => $row['name'] ?? '',
            'source_url' => '',
            'old_supplier_price' => '',
            'new_supplier_cost' => isset($row['price']) && $row['price'] !== null ? $this->formatDecimal((float) $row['price']) : '',
            'product_retail_price' => '',
            'suggested_retail_price' => isset($retailMatch['price']) ? $this->formatDecimal((float) $retailMatch['price']) : '',
            'suggested_retail_row' => $retailMatch['row'] ?? '',
            'suggested_retail_title' => $retailMatch['name'] ?? '',
            'suggested_retail_article' => $retailMatch['article'] ?? '',
            'suggested_retail_confidence' => $retailMatch['confidence'] ?? '',
            'retail_price_action' => $retailMatch ? 'retail_price_used_for_new_product' : 'retail_price_missing',
            'old_stock_status' => '',
            'new_stock_status' => $row['stock_status'] ?? '',
            'product_in_stock_before' => '',
            'match_type' => 'price_list_only',
            'confidence' => $retailMatch ? '100' : '',
            'action' => $action,
            'note' => $note,
        ];
    }

    private function addReportRow(array $row, array $match, string $action): void
    {
        $supplierProduct = $match['supplier_product'] ?? null;
        $retailMatch = $match['retail_match'] ?? null;
        $this->reportRows[] = [
            'price_row' => $row['row'] ?? '',
            'price_title' => $row['name'] ?? '',
            'price_article' => $row['article'] ?? '',
            'supplier_product_id' => $supplierProduct->id ?? '',
            'product_id' => $supplierProduct->product_id ?? '',
            'product_sku' => $this->supplierProductSku($supplierProduct),
            'brand' => $supplierProduct->brand_name ?? '',
            'supplier_title' => $supplierProduct->supplier_name ?? '',
            'source_url' => $supplierProduct->source_url ?? '',
            'old_supplier_price' => isset($supplierProduct->price_byn) ? $this->formatDecimal((float) $supplierProduct->price_byn) : '',
            'new_supplier_cost' => isset($row['price']) && $row['price'] !== null ? $this->formatDecimal((float) $row['price']) : '',
            'product_retail_price' => isset($supplierProduct->product_price) ? $this->formatDecimal((float) $supplierProduct->product_price) : '',
            'suggested_retail_price' => isset($retailMatch['price']) ? $this->formatDecimal((float) $retailMatch['price']) : '',
            'suggested_retail_row' => $retailMatch['row'] ?? '',
            'suggested_retail_title' => $retailMatch['name'] ?? '',
            'suggested_retail_article' => $retailMatch['article'] ?? '',
            'suggested_retail_confidence' => $retailMatch['confidence'] ?? '',
            'retail_price_action' => $this->retailPriceAction($supplierProduct, $retailMatch),
            'old_stock_status' => $supplierProduct->stock_status ?? '',
            'new_stock_status' => $row['stock_status'] ?? '',
            'product_in_stock_before' => isset($supplierProduct->product_in_stock) ? (int) $supplierProduct->product_in_stock : '',
            'match_type' => $match['match_type'] ?? '',
            'confidence' => $match['confidence'] ?? '',
            'action' => $action,
            'note' => $match['reason'] ?? '',
        ];
    }

    private function addReportRowFromSupplierProduct(object $supplierProduct, string $action): void
    {
        $this->reportRows[] = [
            'price_row' => '',
            'price_title' => '',
            'price_article' => '',
            'supplier_product_id' => $supplierProduct->id,
            'product_id' => $supplierProduct->product_id,
            'product_sku' => $this->supplierProductSku($supplierProduct),
            'brand' => $supplierProduct->brand_name,
            'supplier_title' => $supplierProduct->supplier_name,
            'source_url' => $supplierProduct->source_url,
            'old_supplier_price' => isset($supplierProduct->price_byn) ? $this->formatDecimal((float) $supplierProduct->price_byn) : '',
            'new_supplier_cost' => '',
            'product_retail_price' => isset($supplierProduct->product_price) ? $this->formatDecimal((float) $supplierProduct->product_price) : '',
            'suggested_retail_price' => '',
            'suggested_retail_row' => '',
            'suggested_retail_title' => '',
            'suggested_retail_article' => '',
            'suggested_retail_confidence' => '',
            'retail_price_action' => 'retail_price_missing',
            'old_stock_status' => $supplierProduct->stock_status,
            'new_stock_status' => 'out_of_stock',
            'product_in_stock_before' => (int) $supplierProduct->product_in_stock,
            'match_type' => 'missing_from_price_list',
            'confidence' => '',
            'action' => $action,
            'note' => 'No matching row in dynamic BANIA price list',
        ];
    }

    private function addManualRow(array $row, array $match): void
    {
        $supplierProduct = $match['supplier_product'] ?? null;
        $retailMatch = $match['retail_match'] ?? null;
        $this->manualRows[] = [
            'price_row' => $row['row'] ?? '',
            'price_title' => $row['name'] ?? '',
            'price_article' => $row['article'] ?? '',
            'possible_supplier_product_id' => $supplierProduct->id ?? '',
            'possible_product_id' => $supplierProduct->product_id ?? '',
            'possible_product_sku' => $this->supplierProductSku($supplierProduct),
            'possible_supplier_title' => $supplierProduct->supplier_name ?? '',
            'possible_product_title' => $supplierProduct->product_name ?? '',
            'old_supplier_price' => isset($supplierProduct->price_byn) ? $this->formatDecimal((float) $supplierProduct->price_byn) : '',
            'new_supplier_cost' => isset($row['price']) && $row['price'] !== null ? $this->formatDecimal((float) $row['price']) : '',
            'product_retail_price' => isset($supplierProduct->product_price) ? $this->formatDecimal((float) $supplierProduct->product_price) : '',
            'suggested_retail_price' => isset($retailMatch['price']) ? $this->formatDecimal((float) $retailMatch['price']) : '',
            'suggested_retail_row' => $retailMatch['row'] ?? '',
            'suggested_retail_title' => $retailMatch['name'] ?? '',
            'suggested_retail_article' => $retailMatch['article'] ?? '',
            'suggested_retail_confidence' => $retailMatch['confidence'] ?? '',
            'retail_price_action' => $this->retailPriceAction($supplierProduct, $retailMatch),
            'match_type' => $match['match_type'] ?? '',
            'confidence' => $match['confidence'] ?? '',
            'reason' => $match['reason'] ?? '',
        ];
    }

    private function supplierProductSku(?object $supplierProduct): string
    {
        if (! $supplierProduct) {
            return '';
        }

        return (string) (
            $supplierProduct->catalog_product_sku
            ?? $supplierProduct->product_sku
            ?? ''
        );
    }

    private function writeReports(): void
    {
        $dir = storage_path('app/reports/bania');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $stamp = now()->format('Ymd-His');
        if ($this->reportRows !== []) {
            $path = $dir . '/price-list-sync-' . $stamp . '.csv';
            $this->writeCsv($path, $this->reportRows);
            $this->info('Price-list report written: ' . $path);
        }

        if ($this->manualRows !== []) {
            $path = $dir . '/price-list-manual-review-' . $stamp . '.csv';
            $this->writeCsv($path, $this->manualRows);
            $this->warn('Manual review written: ' . $path);
        }
    }

    private function writeCsv(string $path, array $rows): void
    {
        $handle = fopen($path, 'w');
        fputcsv($handle, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
    }

    private function formatDecimal(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function loadAppliedManualLinkIndex(): array
    {
        $index = [];

        $rows = SupplierReviewDecision::query()
            ->where('supplier_code', self::SUPPLIER_CODE)
            ->where('decision', SupplierReviewDecision::DECISION_LINK)
            ->where('status', SupplierReviewDecision::STATUS_APPLIED)
            ->get(['supplier_product_id', 'product_id', 'supplier_article']);

        foreach ($rows as $row) {
            $supplierProductId = (int) ($row->supplier_product_id ?? 0);
            $productId = (int) ($row->product_id ?? 0);
            $article = $this->normalizeArticle((string) ($row->supplier_article ?? ''));

            if ($supplierProductId <= 0 || $productId <= 0 || $article === '') {
                continue;
            }

            $index[$supplierProductId . '|' . $productId . '|' . $article] = true;
        }

        return $index;
    }

    private function wasManualLinkApproved(array $row, array $match): bool
    {
        $supplierProduct = $match['supplier_product'] ?? null;
        if (! $supplierProduct) {
            return false;
        }

        $supplierProductId = (int) ($supplierProduct->id ?? 0);
        $productId = (int) ($supplierProduct->product_id ?? 0);
        $article = $this->normalizeArticle((string) ($row['article'] ?? ''));

        if ($supplierProductId <= 0 || $productId <= 0 || $article === '') {
            return false;
        }

        return isset($this->appliedManualLinkIndex[$supplierProductId . '|' . $productId . '|' . $article]);
    }
}

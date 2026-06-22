<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SyncRnProfiCommand extends Command
{
    protected $signature = 'supplier:sync-rn-profi
        {--dry-run : Preview, write nothing}
        {--apply : Update matched RN-Profi supplier_products}
        {--limit= : Process only the first N parsed rows}
        {--price-file= : Local XLSX/CSV file, skips Google Sheet download}
        {--sheet-url= : Google Sheets URL}
        {--brand=* : Process only these resolved brands, repeatable or comma-separated}
        {--exclude-brand=* : Skip these resolved brands, repeatable or comma-separated}
        {--available-only : Keep only rows that are in stock or have short delivery}
        {--max-delivery-days= : Maximum delivery days for --available-only short-delivery rows}
        {--teplodvor : Match price rows to storage/teplodvor_index.json product cards}
        {--teplodvor-min-score=0.70 : Minimum slug score for Teplodvor matching}
        {--teplodvor-slug-filter= : Limit Teplodvor candidates by slug substring, defaults to resolved brand slug}
        {--teplodvor-brand-page= : Teplodvor brand listing URL to crawl for article matches}
        {--teplodvor-crawl-pages=40 : Maximum Teplodvor brand/listing pages to crawl}
        {--rn-profi-cards : Match price rows to rn-profi.by product cards by article}
        {--refresh-rn-profi-cards : Ignore cached RN-Profi card matches and re-check the site}
        {--rn-profi-crawl-pages=160 : Maximum RN-Profi site pages to crawl for card/article index}
        {--rn-profi-card-limit=100 : Maximum uncached RN-Profi articles to check per run, 0 means no limit}
        {--sync-retail-prices : Update products.price from detected retail price column}
        {--mark-missing-out-of-stock : Mark existing RN-Profi links absent from the sheet as out_of_stock}';

    protected $description = 'Audit and sync RN-Profi Google price list: brands, stock, wholesale and retail prices.';

    private const SUPPLIER_CODE = 'rn-profi';
    private const SUPPLIER_NAME = 'RN-Profi';
    private const SYNC_KEY = 'rn_profi_price';
    private const SOURCE_URL = 'https://rn-profi.by/';
    private const DEFAULT_SHEET_URL = 'https://docs.google.com/spreadsheets/d/1g9C8C7JMO0zQGXdQRCWVQoldSOW6Fyljnd-QJYpTvnQ/edit?gid=1126489059#gid=1126489059';
    private const CACHE_PATH = 'supplier-cache/rn-profi-pricelist.xlsx';
    private const RN_PROFI_CARD_CACHE = 'supplier-cache/rn-profi-card-index.json';
    private const TEPLODVOR_INDEX_FILE = 'teplodvor_index.json';

    private const TEPLODVOR_CATEGORY_MAP = [
        'teplyy-pol/teplye-vodyanye-poly' => 325,
        'teplyy-pol/termoregulyatory-datchiki' => 58,
        'teplyy-pol' => 109,
        'raditory/komplektuyuschie-k-radiatoram' => 195,
        'raditory/konvektory' => 324,
        'raditory/stalnye' => 235,
        'raditory/bimetalicheskie' => 236,
        'raditory/allyminevye' => 233,
        'komplektuyuschie-otopleniya/golovki-termostaticheskie' => 58,
        'komplektuyuschie-otopleniya/nasosy' => 60,
        'komplektuyuschie-otopleniya/raspredelitelnye-kollektory' => 93,
        'komplektuyuschie-otopleniya/shkafy-kollektornye' => 195,
        'komplektuyuschie-otopleniya/nasosno-smesitelnye-uzly' => 195,
        'komplektuyuschie-otopleniya' => 195,
        'nasosy/tsirkulyatsionnye-nasosy' => 60,
        'nasosy' => 265,
    ];

    private const TEPLODVOR_STOPWORDS = [
        'bez', 'dlya', 'so', 'na', 'po', 'iz', 'ot', 'ob', 'pri', 'ili', 'ne', 'do',
        'komplekt', 'sht', 'mm', 'm', 'sm', 'dn', 'ek', 'nr', 'vr',
    ];

    private const TEPLODVOR_SLUG_NORM = [
        'eco' => 'eko',
    ];

    private array $sheetReports = [];
    private array $brandById = [];
    private array $brandByName = [];
    private array $brandTokens = [];
    private array $indexBySupplierArticle = [];
    private array $indexBySku = [];
    private array $indexByBrandModel = [];
    private array $availabilityFilterStats = [];
    private ?array $rnProfiCardCache = null;
    private ?array $teplodvorIndex = null;

    public function handle(): int
    {
        $apply = (bool) $this->option('apply') && ! $this->option('dry-run');
        $limit = $this->option('limit') !== null ? max(0, (int) $this->option('limit')) : null;

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: matched RN-Profi supplier links will be updated.</>'
            : '<fg=yellow;options=bold>DRY RUN: database will not be changed.</>');

        try {
            $file = $this->resolvePriceFile();
            $rows = $this->readPriceRows($file);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->buildIndex();
        $classified = array_map(fn (array $row): array => $this->classify($row), $rows);
        $classified = $this->filterByBrandOptions($classified);
        $classified = $this->filterByAvailabilityOptions($classified);

        if ($limit !== null && $limit > 0) {
            $classified = array_slice($classified, 0, $limit);
        }

        if ($this->option('teplodvor')) {
            $classified = $this->attachTeplodvorMatches($classified);
        }
        if ($this->option('rn-profi-cards')) {
            $classified = $this->attachRnProfiCardMatches($classified);
        }

        return $apply ? $this->applyChanges($classified) : $this->showDryRun($classified);
    }

    private function resolvePriceFile(): string
    {
        $local = $this->option('price-file');
        if (is_string($local) && trim($local) !== '') {
            if (! file_exists($local)) {
                throw new \RuntimeException("Price file not found: {$local}");
            }

            return $local;
        }

        $url = (string) ($this->option('sheet-url') ?: self::DEFAULT_SHEET_URL);
        $exportUrl = $this->toExportUrl($url);
        $path = storage_path('app/' . self::CACHE_PATH);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->line("Downloading RN-Profi Google Sheet: {$exportUrl}");
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 90,
                'follow_location' => 1,
                'max_redirects' => 10,
                'header' => "User-Agent: Mozilla/5.0 (compatible; KotlovBot/1.0)\r\nAccept: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,*/*\r\n",
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);

        $content = @file_get_contents($exportUrl, false, $context);
        if ($content === false || strlen($content) < 1024) {
            throw new \RuntimeException('Google Sheet download failed or returned an empty file.');
        }

        $this->assertXlsxContent($content, $path);

        file_put_contents($path, $content);

        return $path;
    }

    private function assertXlsxContent(string $content, string $targetPath): void
    {
        if (str_starts_with($content, "PK\x03\x04")) {
            return;
        }

        $debugPath = preg_replace('/\.xlsx$/i', '.download-debug.txt', $targetPath) ?: ($targetPath . '.download-debug.txt');
        file_put_contents($debugPath, substr($content, 0, 4096));

        $preview = trim(preg_replace('/\s+/u', ' ', substr($content, 0, 220)) ?? '');
        if ($preview === '') {
            $preview = bin2hex(substr($content, 0, 32));
        }

        throw new \RuntimeException(
            'Google Sheet returned non-XLSX content. '
            . 'Saved first bytes to ' . $debugPath . '. '
            . 'Preview: ' . mb_substr($preview, 0, 180)
        );
    }

    private function toExportUrl(string $url): string
    {
        if (preg_match('#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $url, $m)) {
            return "https://docs.google.com/spreadsheets/d/{$m[1]}/export?format=xlsx";
        }

        return $url;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function readPriceRows(string $path): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension === 'csv') {
            $raw = $this->csvToRows((string) file_get_contents($path));
            return $this->normaliseSheetRows('csv', $raw);
        }

        $content = (string) file_get_contents($path);
        $this->assertXlsxContent($content, $path);

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            $debugPath = preg_replace('/\.xlsx$/i', '.load-debug.txt', $path) ?: ($path . '.load-debug.txt');
            file_put_contents($debugPath, substr($content, 0, 4096));

            throw new \RuntimeException(
                'Downloaded RN-Profi file exists but PhpSpreadsheet cannot read it. '
                . 'Size: ' . filesize($path) . ' bytes. '
                . 'First bytes saved to ' . $debugPath . '. '
                . 'Original error: ' . $e->getMessage()
            );
        }

        $all = [];
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $rows = $sheet->toArray(null, true, true, false);
            $parsed = $this->normaliseSheetRows($sheet->getTitle(), $rows);
            foreach ($parsed as $row) {
                $all[] = $row;
            }
        }

        return $this->dedupeRows($all);
    }

    private function normaliseSheetRows(string $sheetName, array $raw): array
    {
        [$headerIndex, $columns] = $this->detectHeader($raw);
        $this->sheetReports[] = [
            'sheet' => $sheetName,
            'header_row' => $headerIndex >= 0 ? $headerIndex + 1 : null,
            'columns' => implode(', ', array_keys($columns)),
            'raw_rows' => count($raw),
        ];

        if ($headerIndex < 0 || ! isset($columns['name'])) {
            return [];
        }

        $items = [];
        $section = '';
        $brandHint = implode(' ', array_map(fn ($value): string => $this->clean((string) ($value ?? '')), $raw[$headerIndex] ?? []));

        for ($i = $headerIndex + 1; $i < count($raw); $i++) {
            $row = array_map(fn ($value): string => $this->clean((string) ($value ?? '')), $raw[$i] ?? []);
            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $name = $this->cell($row, $columns, 'name');
            $article = $this->cell($row, $columns, 'article');
            $price = $this->money($this->cell($row, $columns, 'price'));
            $retail = $this->money($this->cell($row, $columns, 'retail_price'));
            $stockText = $this->cell($row, $columns, 'stock');
            $qty = $this->quantity($this->cell($row, $columns, 'qty'));
            $brand = $this->cell($row, $columns, 'brand');
            $category = $this->cell($row, $columns, 'category');

            if ($name !== '' && $article === '' && $price === null && $retail === null && $stockText === '') {
                $section = $name;
                continue;
            }

            if ($name === '' || ($price === null && $retail === null && $article === '')) {
                continue;
            }

            if ($section !== '' && $this->needsSectionPrefix($name)) {
                $name = trim($section . ' ' . $name);
            }

            $items[] = [
                'sheet' => $sheetName,
                'row_number' => $i + 1,
                'article' => $article,
                'norm_article' => $this->normArticle($article),
                'brand' => $brand,
                'name' => $name,
                'category_text' => $category !== '' ? $category : $section,
                'brand_hint' => trim($brandHint . ' ' . $sheetName),
                'price' => $price,
                'retail_price' => $retail,
                'stock_text' => $stockText,
                'qty' => $qty,
            ];
        }

        return $items;
    }

    private function detectHeader(array $rows): array
    {
        $bestIndex = -1;
        $bestColumns = [];
        $bestScore = 0;

        foreach (array_slice($rows, 0, 80, true) as $index => $row) {
            $columns = [];
            foreach ($row as $columnIndex => $value) {
                $key = $this->headerKey((string) ($value ?? ''));
                if ($key !== null && ! isset($columns[$key])) {
                    $columns[$key] = (int) $columnIndex;
                }
            }

            $score = count($columns);
            if (isset($columns['name'])) {
                $score += 3;
            }
            if (isset($columns['price']) || isset($columns['retail_price'])) {
                $score += 2;
            }
            if (isset($columns['article'])) {
                $score += 1;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIndex = (int) $index;
                $bestColumns = $columns;
            }
        }

        if ($bestScore < 4) {
            return [-1, $bestColumns];
        }

        return [$bestIndex, $this->inferMissingColumns($rows, $bestIndex, $bestColumns)];
    }

    private function headerKey(string $value): ?string
    {
        $value = $this->normaliseHeader($value);
        if ($value === '') {
            return null;
        }

        $aliases = [
            'article' => ['артикул', 'код', 'код товара', 'sku', 'vendor code', 'номенклатурный номер'],
            'brand' => ['бренд', 'производитель', 'тм', 'торговая марка', 'brand'],
            'name' => ['наименование', 'название', 'товар', 'модель', 'номенклатура', 'размер', 'радиатор', 'радиаторы', 'name'],
            'category' => ['категория', 'группа', 'раздел', 'подраздел', 'category'],
            'price' => ['опт', 'оптовая', 'закуп', 'закупка', 'дилер', 'цена опт', 'цена поставщика'],
            'retail_price' => ['ррц', 'розница', 'розничная', 'мрц', 'цена розница', 'цена сайта'],
            'stock' => ['наличие', 'статус', 'склад', 'доступно', 'availability'],
            'qty' => ['остаток', 'кол-во', 'количество', 'qty', 'stock'],
        ];

        foreach ($aliases as $key => $list) {
            foreach ($list as $alias) {
                if ($value === $alias || str_contains($value, $alias)) {
                    return $key;
                }
            }
        }

        return null;
    }

    private function inferMissingColumns(array $rows, int $headerIndex, array $columns): array
    {
        $priceColumn = $columns['price'] ?? $columns['retail_price'] ?? null;
        $header = $rows[$headerIndex] ?? [];

        if (! isset($columns['name']) && $priceColumn !== null) {
            $candidates = [];
            for ($i = 0; $i < $priceColumn; $i++) {
                if (isset($columns['stock'], $columns['qty']) && in_array($i, [$columns['stock'], $columns['qty']], true)) {
                    continue;
                }

                $headerText = $this->clean((string) ($header[$i] ?? ''));
                $dataScore = 0;
                foreach (array_slice($rows, $headerIndex + 1, 25) as $row) {
                    $text = $this->clean((string) ($row[$i] ?? ''));
                    if ($text !== '' && preg_match('/[A-Za-zА-Яа-яЁё]/u', $text)) {
                        $dataScore += mb_strlen($text) > 12 ? 2 : 1;
                    }
                }
                if ($headerText !== '' && preg_match('/[A-Za-zА-Яа-яЁё]/u', $headerText)) {
                    $dataScore++;
                }
                if (preg_match('/(номенклатура|наименование|название|товар|модель|радиатор|котел|котёл|бойлер|насос|конвектор|полотенц)/iu', $headerText)) {
                    $dataScore += 8;
                }
                if ($dataScore > 0) {
                    $candidates[$i] = $dataScore;
                }
            }

            if ($candidates !== []) {
                arsort($candidates);
                $columns['name'] = (int) array_key_first($candidates);
            }
        }

        if (! isset($columns['article']) && isset($columns['name'])) {
            $nameColumn = (int) $columns['name'];
            foreach ([$nameColumn + 1, $nameColumn - 1, 0, 1, 2] as $candidate) {
                if ($candidate < 0 || $candidate === $nameColumn || $candidate === ($columns['price'] ?? -1) || $candidate === ($columns['retail_price'] ?? -1)) {
                    continue;
                }

                $score = 0;
                foreach (array_slice($rows, $headerIndex + 1, 30) as $row) {
                    $text = $this->clean((string) ($row[$candidate] ?? ''));
                    if ($this->looksLikeArticle($text)) {
                        $score++;
                    }
                }
                if ($score >= 3) {
                    $columns['article'] = (int) $candidate;
                    break;
                }
            }
        }

        return $columns;
    }

    private function buildIndex(): void
    {
        DB::table('brands')->get(['id', 'name'])->each(function (object $brand): void {
            $name = $this->clean((string) $brand->name);
            $this->brandById[(int) $brand->id] = $name;
            $this->brandByName[$this->brandKey($name)] = (int) $brand->id;
            $token = $this->brandToken($name);
            if ($token !== '') {
                $this->brandTokens[$token] = (int) $brand->id;
            }
        });

        $supplierId = $this->supplierId();
        if ($supplierId > 0) {
            DB::table('supplier_products')
                ->where('supplier_id', $supplierId)
                ->whereNotNull('product_id')
                ->get(['supplier_article', 'product_id'])
                ->each(function (object $row): void {
                    $article = $this->normArticle((string) $row->supplier_article);
                    if ($article !== '') {
                        $this->indexBySupplierArticle[$article] = (int) $row->product_id;
                    }
                });
        }

        DB::table('products')
            ->where('is_archived', false)
            ->get(['id', 'sku', 'name', 'brand_id'])
            ->each(function (object $product): void {
                $sku = mb_strtoupper(trim((string) $product->sku));
                if ($sku !== '') {
                    $this->indexBySku[$sku] = (int) $product->id;
                }

                $brandId = (int) $product->brand_id;
                if ($brandId > 0) {
                    $model = $this->model((string) $product->name, $this->brandById[$brandId] ?? '');
                    if ($model !== '') {
                        $this->indexByBrandModel[$brandId][$model] = (int) $product->id;
                    }
                }
            });
    }

    private function classify(array $row): array
    {
        $brandId = $this->resolveBrand($row['brand'], trim($row['name'] . ' ' . $row['category_text'] . ' ' . $row['brand_hint'] . ' ' . $row['sheet']));
        $match = $this->match($row, $brandId);
        $stock = $this->stock($row['stock_text'], $row['qty']);

        $action = match (true) {
            $row['price'] === null => 'price_missing',
            $match !== null => 'matched',
            $brandId === null => 'brand_missing',
            default => 'unmatched',
        };

        return $row + [
            'resolved_brand_id' => $brandId,
            'resolved_brand' => $brandId ? ($this->brandById[$brandId] ?? '') : null,
            'matched_product_id' => $match['product_id'] ?? null,
            'matched_sku' => $match['sku'] ?? null,
            'confidence' => $match['confidence'] ?? null,
            'stock' => $stock,
            'action' => $action,
        ];
    }

    private function filterByBrandOptions(array $rows): array
    {
        $only = $this->brandOptionKeys((array) $this->option('brand'));
        $exclude = $this->brandOptionKeys((array) $this->option('exclude-brand'));

        if ($only === [] && $exclude === []) {
            return $rows;
        }

        $filtered = array_values(array_filter($rows, function (array $row) use ($only, $exclude): bool {
            $brand = $this->brandKey((string) ($row['resolved_brand'] ?: $row['brand'] ?: 'NO BRAND'));

            if ($only !== [] && ! in_array($brand, $only, true)) {
                return false;
            }

            return ! in_array($brand, $exclude, true);
        }));

        $this->line(sprintf(
            'Brand filter: %d of %d rows selected%s%s.',
            count($filtered),
            count($rows),
            $only !== [] ? ' only=' . implode(',', $only) : '',
            $exclude !== [] ? ' exclude=' . implode(',', $exclude) : ''
        ));

        return $filtered;
    }

    private function brandOptionKeys(array $values): array
    {
        $keys = [];
        foreach ($values as $value) {
            foreach (explode(',', (string) $value) as $part) {
                $key = $this->brandKey($part);
                if ($key !== '') {
                    $keys[] = $key;
                }
            }
        }

        return array_values(array_unique($keys));
    }

    private function filterByAvailabilityOptions(array $rows): array
    {
        $availableOnly = (bool) $this->option('available-only');
        $maxDelivery = $this->option('max-delivery-days');
        $maxDelivery = $maxDelivery !== null && $maxDelivery !== ''
            ? max(0, (int) $maxDelivery)
            : null;

        if (! $availableOnly && $maxDelivery === null) {
            return $rows;
        }

        $filtered = array_values(array_filter($rows, function (array $row) use ($maxDelivery): bool {
            $stock = $row['stock'] ?? [];
            if (($stock['in_stock'] ?? false) === true) {
                return true;
            }

            $days = $stock['delivery_days'] ?? null;
            return $maxDelivery !== null && $days !== null && $days <= $maxDelivery;
        }));

        $this->availabilityFilterStats = [
            'before' => count($rows),
            'after' => count($filtered),
            'max_delivery_days' => $maxDelivery,
        ];

        $this->line(sprintf(
            'Availability filter: %d of %d rows selected%s.',
            count($filtered),
            count($rows),
            $maxDelivery !== null ? " max_delivery_days={$maxDelivery}" : ''
        ));

        return $filtered;
    }

    private function match(array $row, ?int $brandId): ?array
    {
        if ($row['norm_article'] !== '' && isset($this->indexBySupplierArticle[$row['norm_article']])) {
            $productId = $this->indexBySupplierArticle[$row['norm_article']];
            return ['product_id' => $productId, 'sku' => $this->sku($productId), 'confidence' => 'exact_supplier_article'];
        }

        $article = mb_strtoupper($row['norm_article']);
        if ($article !== '' && isset($this->indexBySku[$article])) {
            return ['product_id' => $this->indexBySku[$article], 'sku' => $article, 'confidence' => 'exact_sku'];
        }

        if ($brandId !== null) {
            $model = $this->model($row['name'], $this->brandById[$brandId] ?? '');
            if ($model !== '' && isset($this->indexByBrandModel[$brandId][$model])) {
                $productId = $this->indexByBrandModel[$brandId][$model];
                return ['product_id' => $productId, 'sku' => $this->sku($productId), 'confidence' => 'brand_model'];
            }
        }

        return null;
    }

    private function attachRnProfiCardMatches(array $rows): array
    {
        $cache = $this->loadRnProfiCardCache();
        $refresh = (bool) $this->option('refresh-rn-profi-cards');
        $limit = max(0, (int) $this->option('rn-profi-card-limit'));
        $checked = 0;
        $changed = false;

        if ($refresh) {
            $cache = [];
        }

        [$cache, $crawlChanged, $pagesCrawled] = $this->crawlRnProfiCardsForRows($rows, $cache);
        $changed = $changed || $crawlChanged;

        foreach ($rows as $index => $row) {
            $article = $this->normArticle((string) ($row['article'] ?? ''));
            if ($article === '') {
                $rows[$index] += $this->emptyRnProfiCardFields();
                continue;
            }

            if (! $refresh && array_key_exists($article, $cache)) {
                $rows[$index] += $this->rnProfiCardFields($cache[$article]);
                continue;
            }

            if ($limit > 0 && $checked >= $limit) {
                $rows[$index] += $this->emptyRnProfiCardFields();
                continue;
            }

            $checked++;
            $card = $this->findRnProfiCardForArticle((string) ($row['article'] ?: $article));
            $cache[$article] = $card;
            $changed = true;
            $rows[$index] += $this->rnProfiCardFields($card);
        }

        if ($changed) {
            $this->saveRnProfiCardCache($cache);
        }

        $this->line(sprintf(
            'RN-Profi cards: cache=%d, crawled_pages=%d, checked_live=%d%s.',
            count($cache),
            $pagesCrawled,
            $checked,
            $limit > 0 ? ", live_limit={$limit}" : ''
        ));

        return $rows;
    }

    private function crawlRnProfiCardsForRows(array $rows, array $cache): array
    {
        $targetArticles = [];
        foreach ($rows as $row) {
            $article = $this->normArticle((string) ($row['article'] ?? ''));
            if ($article !== '' && ! array_key_exists($article, $cache)) {
                $targetArticles[$article] = true;
            }
        }

        if ($targetArticles === []) {
            return [$cache, false, 0];
        }

        $maxPages = max(0, (int) $this->option('rn-profi-crawl-pages'));
        if ($maxPages === 0) {
            return [$cache, false, 0];
        }

        $queue = [
            self::SOURCE_URL,
            self::SOURCE_URL . 'index.php?route=information/sitemap',
        ];
        $visited = [];
        $changed = false;
        $pages = 0;

        while ($queue !== [] && $pages < $maxPages && $targetArticles !== []) {
            $url = array_shift($queue);
            $url = strtok((string) $url, '#') ?: (string) $url;
            if (isset($visited[$url])) {
                continue;
            }
            $visited[$url] = true;

            $html = $this->fetchRnProfiPage($url);
            if ($html === null) {
                continue;
            }
            $pages++;

            if ($this->looksLikeRnProfiProductPage($html)) {
                $card = $this->parseRnProfiCard($url, $html, 'site_crawl');
                $cardTokens = array_flip($card['article_tokens'] ?? []);
                foreach (array_keys($targetArticles) as $article) {
                    if (! isset($cardTokens[$article]) && ! $this->pageContainsArticle($html, $article)) {
                        continue;
                    }
                    $cache[$article] = $card;
                    unset($targetArticles[$article]);
                    $changed = true;
                }
            }

            foreach ($this->extractRnProfiInternalUrls($html) as $nextUrl) {
                if (! isset($visited[$nextUrl]) && count($queue) < ($maxPages * 4)) {
                    $queue[] = $nextUrl;
                }
            }
        }

        return [$cache, $changed, $pages];
    }

    private function emptyRnProfiCardFields(): array
    {
        return [
            'rn_profi_url' => null,
            'rn_profi_title' => null,
            'rn_profi_brand' => null,
            'rn_profi_image' => null,
            'rn_profi_confidence' => null,
        ];
    }

    private function rnProfiCardFields(?array $card): array
    {
        if ($card === null) {
            return $this->emptyRnProfiCardFields();
        }

        return [
            'rn_profi_url' => $card['url'] ?? null,
            'rn_profi_title' => $card['title'] ?? null,
            'rn_profi_brand' => $card['brand'] ?? null,
            'rn_profi_image' => $card['image'] ?? null,
            'rn_profi_confidence' => $card['confidence'] ?? null,
        ];
    }

    private function loadRnProfiCardCache(): array
    {
        if ($this->rnProfiCardCache !== null) {
            return $this->rnProfiCardCache;
        }

        $path = storage_path('app/' . self::RN_PROFI_CARD_CACHE);
        if (! is_file($path)) {
            return $this->rnProfiCardCache = [];
        }

        $data = json_decode((string) file_get_contents($path), true);
        return $this->rnProfiCardCache = is_array($data) ? $data : [];
    }

    private function saveRnProfiCardCache(array $cache): void
    {
        $path = storage_path('app/' . self::RN_PROFI_CARD_CACHE);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        ksort($cache);
        file_put_contents($path, json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->rnProfiCardCache = $cache;
    }

    private function findRnProfiCardForArticle(string $article): ?array
    {
        $normArticle = $this->normArticle($article);
        $searchUrl = self::SOURCE_URL . 'index.php?route=product/search&search=' . rawurlencode($article);
        $html = $this->fetchRnProfiPage($searchUrl);
        if ($html === null) {
            return null;
        }

        $candidateUrls = $this->extractRnProfiProductUrls($html);
        if ($this->pageContainsArticle($html, $normArticle) && $this->looksLikeRnProfiProductPage($html)) {
            array_unshift($candidateUrls, $searchUrl);
        }

        foreach (array_slice(array_values(array_unique($candidateUrls)), 0, 8) as $url) {
            $cardHtml = $url === $searchUrl ? $html : $this->fetchRnProfiPage($url);
            if ($cardHtml === null) {
                continue;
            }

            $card = $this->parseRnProfiCard($url, $cardHtml, 'article_search');
            if (in_array($normArticle, $card['article_tokens'] ?? [], true) || $this->pageContainsArticle($cardHtml, $normArticle)) {
                return $card;
            }
        }

        return null;
    }

    private function fetchRnProfiPage(string $url): ?string
    {
        return $this->fetchHttpPage($url);
    }

    private function fetchHttpPage(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 25,
                'follow_location' => 1,
                'max_redirects' => 5,
                'header' => "User-Agent: Mozilla/5.0 (compatible; KotlovBot/1.0)\r\nAccept: text/html,*/*\r\n",
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);

        $html = @file_get_contents($url, false, $context);
        if (! is_string($html) || strlen($html) < 300) {
            return null;
        }

        return $html;
    }

    private function extractRnProfiProductUrls(string $html): array
    {
        return array_values(array_filter(
            $this->extractRnProfiInternalUrls($html),
            fn (string $url): bool => $this->rnProfiUrlLooksLikeProduct($url)
        ));
    }

    private function extractRnProfiInternalUrls(string $html): array
    {
        preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $matches);
        $urls = [];
        foreach ($matches[1] ?? [] as $href) {
            $url = html_entity_decode((string) $href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, 'javascript:')) {
                continue;
            }
            if (str_starts_with($url, '/')) {
                $url = rtrim(self::SOURCE_URL, '/') . $url;
            }
            if (! str_starts_with($url, self::SOURCE_URL)) {
                continue;
            }
            if (str_contains($url, 'route=account')
                || str_contains($url, 'route=checkout')
                || str_contains($url, 'route=common')
                || str_contains($url, 'route=affiliate')
                || str_contains($url, 'route=product/compare')
            ) {
                continue;
            }
            $urls[] = strtok($url, '#') ?: $url;
        }

        return array_values(array_unique($urls));
    }

    private function rnProfiUrlLooksLikeProduct(string $url): bool
    {
        if (str_contains($url, 'route=product/product')) {
            return true;
        }

        $path = trim((string) (parse_url($url, PHP_URL_PATH) ?: ''), '/');
        return $path !== ''
            && ! str_contains($path, '/')
            && ! in_array($path, ['about_us', 'payment', 'delivery', 'contact-us', 'brands'], true);
    }

    private function looksLikeRnProfiProductPage(string $html): bool
    {
        return (bool) preg_match('/<h1[^>]*>.*?<\/h1>/is', $html)
            && (str_contains($html, 'Код товара') || str_contains($html, 'В корзину'));
    }

    private function pageContainsArticle(string $html, string $normArticle): bool
    {
        if ($normArticle === '') {
            return false;
        }

        return str_contains($this->normArticle(strip_tags($html)), $normArticle);
    }

    private function parseRnProfiCard(string $url, string $html, string $confidence): array
    {
        $title = null;
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m)) {
            $title = $this->clean(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if ($title === null && preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            $title = $this->clean(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $image = null;
        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            $image = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        } elseif (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]+alt=["\'][^"\']*' . preg_quote((string) $title, '/') . '/iu', $html, $m)) {
            $image = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if (is_string($image) && str_starts_with($image, '/')) {
            $image = rtrim(self::SOURCE_URL, '/') . $image;
        }

        $text = $this->clean(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $brand = null;
        if (preg_match('/Бренд:\s*([^\s].*?)(?:\s+Код товара:|\s+Производитель:|\s+Импортер:|$)/u', $text, $m)) {
            $brand = $this->clean($m[1]);
        }

        return [
            'url' => strtok($url, '#') ?: $url,
            'title' => $title,
            'brand' => $brand,
            'image' => $image,
            'article_tokens' => $this->extractSupplierArticleTokens($html . ' ' . $url),
            'confidence' => $confidence,
        ];
    }

    private function extractSupplierArticleTokens(string $text): array
    {
        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = strip_tags($decoded) . ' ' . $decoded;
        $tokens = [];

        preg_match_all('/\b(?:VM|VMDV|VMCP|VMC|VMP|VMS|VT|PS|KOTLOV)[A-ZА-Я0-9\-\/\.]{2,}\b/iu', $plain, $matches);
        foreach ($matches[0] ?? [] as $token) {
            $norm = $this->normArticle($token);
            if (mb_strlen($norm) >= 4) {
                $tokens[$norm] = true;
            }
        }

        preg_match_all('/\b\d{5,8}\b/u', $plain, $matches);
        foreach ($matches[0] ?? [] as $token) {
            $tokens[$this->normArticle($token)] = true;
        }

        return array_values(array_keys($tokens));
    }

    private function attachTeplodvorMatches(array $rows): array
    {
        $index = $this->loadTeplodvorIndex();
        $brandPageMatches = $this->crawlTeplodvorBrandPageMatches($rows);
        if ($index === []) {
            $this->warn('Teplodvor index is empty. Run: php artisan supplier:enrich-teplodvor --build-index');
        } else {
            $this->line(sprintf('Teplodvor index: %d product URLs.', count($index)));
        }

        if ($brandPageMatches !== []) {
            $this->line(sprintf('Teplodvor brand page article matches: %d.', count($brandPageMatches)));
        }

        return array_map(function (array $row) use ($index, $brandPageMatches): array {
            $article = $this->normArticle((string) ($row['article'] ?? ''));
            $match = $article !== '' && isset($brandPageMatches[$article])
                ? $brandPageMatches[$article]
                : $this->findTeplodvorMatch($row, $index);

            return $row + [
                'teplodvor_url' => $match['url'] ?? null,
                'teplodvor_score' => $match['score'] ?? null,
                'teplodvor_confidence' => $match['confidence'] ?? null,
                'teplodvor_category_id' => isset($match['url']) ? $this->detectTeplodvorCategory((string) $match['url']) : null,
            ];
        }, $rows);
    }

    private function crawlTeplodvorBrandPageMatches(array $rows): array
    {
        $targetArticles = [];
        foreach ($rows as $row) {
            $article = $this->normArticle((string) ($row['article'] ?? ''));
            if ($article !== '') {
                $targetArticles[$article] = true;
            }
        }

        if ($targetArticles === []) {
            return [];
        }

        $brandPage = trim((string) $this->option('teplodvor-brand-page'));
        if ($brandPage === '') {
            $brand = '';
            foreach ($rows as $row) {
                $brand = (string) ($row['resolved_brand'] ?: $row['brand'] ?: '');
                if ($brand !== '') {
                    break;
                }
            }
            if ($brand === '') {
                return [];
            }
            $brandPage = 'https://www.teplodvor.by/shop/' . Str::slug($brand) . '/';
        }

        $maxPages = max(0, (int) $this->option('teplodvor-crawl-pages'));
        if ($maxPages === 0) {
            return [];
        }

        $queue = [$brandPage];
        $visited = [];
        $matches = [];
        $pages = 0;

        while ($queue !== [] && $pages < $maxPages && count($matches) < count($targetArticles)) {
            $url = array_shift($queue);
            $url = strtok((string) $url, '#') ?: (string) $url;
            if (isset($visited[$url])) {
                continue;
            }
            $visited[$url] = true;

            $html = $this->fetchHttpPage($url);
            if ($html === null) {
                continue;
            }
            $pages++;

            $tokens = array_flip($this->extractSupplierArticleTokens($html . ' ' . $url));
            foreach (array_keys($targetArticles) as $article) {
                if (! isset($tokens[$article])) {
                    continue;
                }
                $matches[$article] = [
                    'url' => $url,
                    'score' => 1.0,
                    'confidence' => 'brand_page_article',
                ];
            }

            foreach ($this->extractTeplodvorInternalUrls($html) as $nextUrl) {
                if (! isset($visited[$nextUrl]) && count($queue) < ($maxPages * 5)) {
                    $queue[] = $nextUrl;
                }
            }
        }

        if ($pages > 0) {
            $this->line(sprintf('Teplodvor brand crawl: %d pages from %s.', $pages, $brandPage));
        }

        return $matches;
    }

    private function extractTeplodvorInternalUrls(string $html): array
    {
        preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $matches);
        $urls = [];
        foreach ($matches[1] ?? [] as $href) {
            $url = html_entity_decode((string) $href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, 'javascript:')) {
                continue;
            }
            if (str_starts_with($url, '/')) {
                $url = 'https://www.teplodvor.by' . $url;
            }
            if (! str_starts_with($url, 'https://www.teplodvor.by/shop/')) {
                continue;
            }
            if (str_contains($url, '/cart') || str_contains($url, '/compare') || str_contains($url, '/wishlist')) {
                continue;
            }
            $urls[] = strtok($url, '#') ?: $url;
        }

        return array_values(array_unique($urls));
    }

    private function loadTeplodvorIndex(): array
    {
        if ($this->teplodvorIndex !== null) {
            return $this->teplodvorIndex;
        }

        $path = storage_path(self::TEPLODVOR_INDEX_FILE);
        if (! is_file($path)) {
            $path = storage_path('app/' . self::TEPLODVOR_INDEX_FILE);
        }
        if (! is_file($path)) {
            return $this->teplodvorIndex = [];
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            return $this->teplodvorIndex = [];
        }

        return $this->teplodvorIndex = array_filter($data, fn ($url, $slug): bool => is_string($slug) && is_string($url), ARRAY_FILTER_USE_BOTH);
    }

    private function findTeplodvorMatch(array $row, array $index): ?array
    {
        $article = $this->articleSlug((string) ($row['article'] ?? ''));
        if ($article !== '' && strlen($article) >= 4) {
            foreach ($index as $slug => $url) {
                if (str_contains($this->compactSlug($slug), $article)) {
                    return ['url' => $url, 'score' => 1.0, 'confidence' => 'article_slug'];
                }
            }
        }

        $brand = (string) ($row['resolved_brand'] ?: $row['brand'] ?: '');
        $brandSlug = Str::slug($brand);
        $slugFilter = trim((string) $this->option('teplodvor-slug-filter'));
        if ($slugFilter === '' && $brandSlug !== '') {
            $slugFilter = $brandSlug;
        }
        $slugFilter = Str::slug($slugFilter);

        $candidates = $index;
        if ($slugFilter !== '') {
            $candidates = array_filter(
                $index,
                fn ($url, $slug): bool => str_contains((string) $slug, $slugFilter) || str_contains((string) $url, $slugFilter),
                ARRAY_FILTER_USE_BOTH
            );
        }

        if ($candidates === []) {
            $candidates = $index;
        }

        $query = trim(implode(' ', array_filter([
            $brand,
            $row['category_text'] ?? '',
            $row['name'] ?? '',
            $row['article'] ?? '',
        ])));
        $querySlug = $this->normaliseTeplodvorSlug(Str::slug($query));
        $brandTokens = $this->teplodvorTokens($brandSlug, []);
        $tokens = $this->teplodvorTokens($querySlug, $brandTokens);
        if (count($tokens) < 2) {
            return null;
        }

        $best = ['score' => 0.0, 'url' => null];
        foreach ($candidates as $slug => $url) {
            $score = $this->scoreTeplodvorSlug($tokens, (string) $slug);
            if ($score > $best['score']) {
                $best = ['score' => $score, 'url' => $url];
            }
        }

        $minScore = max(0.1, min(1.0, (float) $this->option('teplodvor-min-score')));
        if ($best['url'] !== null && $best['score'] >= $minScore) {
            return ['url' => $best['url'], 'score' => $best['score'], 'confidence' => 'slug_score'];
        }

        return null;
    }

    private function teplodvorTokens(string $slug, array $brandTokens): array
    {
        $slug = $this->normaliseTeplodvorSlug($slug);

        return array_values(array_unique(array_filter(
            explode('-', strtolower($slug)),
            fn (string $token): bool => (strlen($token) >= 2 || ctype_digit($token))
                && ! in_array($token, self::TEPLODVOR_STOPWORDS, true)
                && ! array_filter($brandTokens, fn (string $brandToken): bool => levenshtein($token, $brandToken) <= 1)
                && ! (strlen($token) >= 10 && preg_match('/[a-z]/', $token) && preg_match('/\d/', $token))
        )));
    }

    private function scoreTeplodvorSlug(array $tokens, string $slug): float
    {
        $slug = $this->normaliseTeplodvorSlug($slug);
        $numerics = array_values(array_filter($tokens, 'ctype_digit'));
        $numConcat = count($numerics) >= 2 ? implode('', $numerics) : null;
        $numConcatUsed = false;

        foreach ($numerics as $number) {
            if (preg_match('/(?:^|-)' . preg_quote($number, '/') . '(?:-|$)/', $slug)) {
                continue;
            }
            if ($numConcat !== null && preg_match('/(?:^|-)' . preg_quote($numConcat, '/') . '(?:-|$)/', $slug)) {
                $numConcatUsed = true;
                continue;
            }

            return 0.0;
        }

        $total = array_sum(array_map('strlen', $tokens));
        if ($total <= 0) {
            return 0.0;
        }

        $matched = 0;
        foreach ($tokens as $token) {
            $hit = (ctype_digit($token) && $numConcatUsed)
                || (bool) preg_match('/(?:^|-)' . preg_quote($token, '/') . '(?:-|$)/', $slug);
            if ($hit) {
                $matched += strlen($token);
            }
        }

        return $matched / $total;
    }

    private function normaliseTeplodvorSlug(string $slug): string
    {
        $slug = strtolower($slug);
        $slug = str_replace(array_keys(self::TEPLODVOR_SLUG_NORM), array_values(self::TEPLODVOR_SLUG_NORM), $slug);

        return preg_replace('/-+/', '-', $slug) ?? $slug;
    }

    private function articleSlug(string $article): string
    {
        return $this->compactSlug(Str::slug($article));
    }

    private function compactSlug(string $slug): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower($slug)) ?? '';
    }

    private function detectTeplodvorCategory(string $url): ?int
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path)) {
            return null;
        }

        $path = preg_replace('#^/?shop/#', '', trim($path, '/')) ?? '';
        $path = trim($path, '/');
        foreach (self::TEPLODVOR_CATEGORY_MAP as $prefix => $categoryId) {
            if (str_starts_with($path, $prefix . '/') || $path === $prefix) {
                return $categoryId;
            }
        }

        return null;
    }

    private function showDryRun(array $rows): int
    {
        $this->newLine();
        $this->info('Sheets / detected columns:');
        $this->table(['sheet', 'header row', 'columns', 'raw rows'], array_map(
            fn (array $report): array => [$report['sheet'], $report['header_row'] ?? '-', $report['columns'], $report['raw_rows']],
            $this->sheetReports
        ));

        $actions = $this->counts($rows, 'action');
        $stocks = [];
        foreach ($rows as $row) {
            $stocks[$row['stock']['status']] = ($stocks[$row['stock']['status']] ?? 0) + 1;
        }

        $this->info('RN-Profi audit:');
        $this->table(['metric', 'count'], [
            ['parsed rows', count($rows)],
            ['rows with wholesale price', count(array_filter($rows, fn ($r) => $r['price'] !== null))],
            ['rows with retail price', count(array_filter($rows, fn ($r) => $r['retail_price'] !== null))],
            ['matched existing products', $actions['matched'] ?? 0],
            ['new/unmatched candidates', $actions['unmatched'] ?? 0],
            ['missing/unknown brands', $actions['brand_missing'] ?? 0],
            ['missing wholesale price', $actions['price_missing'] ?? 0],
        ]);

        $this->info('Stock statuses:');
        $this->table(['stock_status', 'rows'], $this->mapCounts($stocks));

        if ($this->availabilityFilterStats !== []) {
            $this->info('Availability filter:');
            $this->table(['metric', 'count'], [
                ['before filter', $this->availabilityFilterStats['before'] ?? 0],
                ['after filter', $this->availabilityFilterStats['after'] ?? 0],
                ['max delivery days', $this->availabilityFilterStats['max_delivery_days'] ?? '-'],
            ]);
        }

        if ($this->option('rn-profi-cards')) {
            $this->info('RN-Profi card matches:');
            $matchedRnProfi = array_values(array_filter($rows, fn (array $row): bool => ! empty($row['rn_profi_url'])));
            $this->table(['metric', 'count'], [
                ['matched card URLs', count($matchedRnProfi)],
                ['missing card URLs', count($rows) - count($matchedRnProfi)],
            ]);

            $this->info('RN-Profi matches by sheet:');
            $this->table(['sheet', 'matched', 'missing', 'rows'], $this->rnProfiSheetRows($rows));
        }

        if ($this->option('teplodvor')) {
            $this->info('Teplodvor card matches:');
            $matchedTeplodvor = array_values(array_filter($rows, fn (array $row): bool => ! empty($row['teplodvor_url'])));
            $this->table(['metric', 'count'], [
                ['matched card URLs', count($matchedTeplodvor)],
                ['missing card URLs', count($rows) - count($matchedTeplodvor)],
                ['matched by article in slug', count(array_filter($matchedTeplodvor, fn (array $row): bool => ($row['teplodvor_confidence'] ?? '') === 'article_slug'))],
                ['matched by brand page article', count(array_filter($matchedTeplodvor, fn (array $row): bool => ($row['teplodvor_confidence'] ?? '') === 'brand_page_article'))],
                ['matched by slug score', count(array_filter($matchedTeplodvor, fn (array $row): bool => ($row['teplodvor_confidence'] ?? '') === 'slug_score'))],
            ]);

            $this->info('Teplodvor matches by sheet:');
            $this->table(['sheet', 'matched', 'missing', 'rows'], $this->teplodvorSheetRows($rows));
        }

        $this->info('Actions by sheet:');
        $this->table(
            ['sheet', 'matched', 'unmatched', 'brand_missing', 'price_missing', 'rows'],
            $this->sheetActionRows($rows)
        );

        $brandRows = [];
        foreach ($rows as $row) {
            $brand = $row['resolved_brand'] ?: ($row['brand'] ?: 'NO BRAND');
            $brandRows[$brand]['rows'] = ($brandRows[$brand]['rows'] ?? 0) + 1;
            $brandRows[$brand]['exists'] = $row['resolved_brand_id'] !== null;
        }
        uasort($brandRows, fn ($a, $b) => $b['rows'] <=> $a['rows']);
        $this->info('Brands in price list:');
        $this->table(['brand', 'rows', 'in catalog', 'catalog products'], array_map(
            fn ($brand, $item): array => [$brand, $item['rows'], $item['exists'] ? 'yes' : 'NO', $this->catalogProductCountForBrand($brand)],
            array_keys(array_slice($brandRows, 0, 60, true)),
            array_values(array_slice($brandRows, 0, 60, true))
        ));

        $matched = array_values(array_filter($rows, fn (array $row): bool => $row['action'] === 'matched'));
        if ($matched !== []) {
            $this->info('Matched examples:');
            $this->table($this->exampleHeaders(), $this->exampleRows(array_slice($matched, 0, 15)));
        }

        if ($this->option('teplodvor')) {
            $teplodvorMatched = array_values(array_filter($rows, fn (array $row): bool => ! empty($row['teplodvor_url'])));
            if ($teplodvorMatched !== []) {
                $this->info('Teplodvor card examples:');
                $this->table($this->exampleHeaders(), $this->exampleRows(array_slice($teplodvorMatched, 0, 20)));
            }
        }

        if ($this->option('rn-profi-cards')) {
            $rnProfiMatched = array_values(array_filter($rows, fn (array $row): bool => ! empty($row['rn_profi_url'])));
            if ($rnProfiMatched !== []) {
                $this->info('RN-Profi card examples:');
                $this->table($this->exampleHeaders(), $this->exampleRows(array_slice($rnProfiMatched, 0, 20)));
            }
        }

        $unmatched = array_values(array_filter($rows, fn (array $row): bool => $row['action'] !== 'matched'));
        if ($unmatched !== []) {
            $this->info('Unmatched examples:');
            $this->table($this->exampleHeaders(), $this->exampleRows(array_slice($unmatched, 0, 20)));
        }

        $this->newLine();
        $this->line('Next: run with <fg=green>--apply</> only after checking detected columns and matches.');

        return self::SUCCESS;
    }

    private function applyChanges(array $rows): int
    {
        $now = now();
        $supplierId = $this->ensureSupplier($now);
        $syncId = $this->ensureSync($now);
        $stats = array_fill_keys(['matched_updated', 'retail_synced', 'skipped', 'missing_marked_out_of_stock', 'errors'], 0);
        $presentArticles = [];

        foreach ($rows as $row) {
            if ($row['norm_article'] !== '') {
                $presentArticles[] = $row['norm_article'];
            }

            if ($row['action'] !== 'matched' || $row['matched_product_id'] === null || $row['price'] === null) {
                $stats['skipped']++;
                continue;
            }

            try {
                $this->upsertSupplierProduct($row, $supplierId, $syncId, $now);
                $stats['matched_updated']++;

                if ($this->option('sync-retail-prices') && $row['retail_price'] !== null) {
                    DB::table('products')->where('id', $row['matched_product_id'])->update([
                        'price' => $row['retail_price'],
                        'updated_at' => $now,
                    ]);
                    $stats['retail_synced']++;
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn("[error] {$row['article']} {$row['name']}: {$e->getMessage()}");
            }
        }

        if ($this->option('mark-missing-out-of-stock')) {
            $stats['missing_marked_out_of_stock'] = $this->markMissingOutOfStock($supplierId, $presentArticles, $now);
        }

        $this->table(['metric', 'count'], $this->mapCounts($stats));

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function upsertSupplierProduct(array $row, int $supplierId, ?int $syncId, $now): void
    {
        $payload = [
            'supplier_sync_id' => $syncId,
            'product_id' => (int) $row['matched_product_id'],
            'product_sku' => $row['matched_sku'],
            'supplier_name' => trim(($row['resolved_brand'] ?: $row['brand']) . ' ' . $row['name']),
            'source_url' => self::SOURCE_URL,
            'price' => $row['price'],
            'currency' => 'BYN',
            'currency_rate' => 1.0,
            'price_byn' => $row['price'],
            'in_stock' => $row['stock']['in_stock'],
            'stock_quantity' => $row['qty'],
            'stock_status' => $row['stock']['status'],
            'stock_text' => $row['stock_text'] !== '' ? $row['stock_text'] : null,
            'delivery_days' => $row['stock']['delivery_days'],
            'match_status' => 'matched',
            'match_confidence' => $row['confidence'],
            'raw' => json_encode([
                'sheet' => $row['sheet'],
                'row' => $row['row_number'],
                'article' => $row['article'],
                'brand' => $row['brand'],
                'category' => $row['category_text'],
                'retail_price' => $row['retail_price'],
            ], JSON_UNESCAPED_UNICODE),
            'last_synced_at' => $now,
            'last_stock_synced_at' => $now,
            'updated_at' => $now,
        ];

        $existing = DB::table('supplier_products')
            ->where('supplier_id', $supplierId)
            ->where('product_id', $row['matched_product_id'])
            ->value('id');

        if ($existing) {
            DB::table('supplier_products')->where('id', $existing)->update($payload + [
                'supplier_article' => $row['norm_article'] ?: $row['article'],
                'supplier_article_normalized' => $row['norm_article'],
            ]);
            return;
        }

        DB::table('supplier_products')->updateOrInsert(
            ['supplier_id' => $supplierId, 'supplier_article' => $row['norm_article'] ?: $row['article']],
            $payload + [
                'supplier_article_normalized' => $row['norm_article'],
                'created_at' => $now,
            ]
        );
    }

    private function markMissingOutOfStock(int $supplierId, array $presentArticles, $now): int
    {
        $query = DB::table('supplier_products')->where('supplier_id', $supplierId);
        $presentArticles = array_values(array_unique(array_filter($presentArticles)));
        if ($presentArticles !== []) {
            $query->whereNotIn('supplier_article_normalized', $presentArticles);
        }

        return $query->update([
            'in_stock' => false,
            'stock_status' => 'out_of_stock',
            'updated_at' => $now,
            'last_stock_synced_at' => $now,
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
                'notes' => 'RN-Profi Google price list. Wholesale and stock come from spreadsheet; product content is enriched separately from rn-profi.by or teplodvor.by.',
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
                'title' => 'RN-Profi: price and stock',
                'description' => 'Reads RN-Profi Google price list, audits brands/matches, updates supplier_products only for confident matches.',
                'command' => 'supplier:sync-rn-profi',
                'source_url' => self::SOURCE_URL,
                'image_disk_path' => 'img/products/rn-profi',
                'is_active' => true,
                'last_run_at' => $now,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        return (int) DB::table('supplier_syncs')->where('key', self::SYNC_KEY)->value('id');
    }

    private function resolveBrand(string $brand, string $name): ?int
    {
        $brand = $this->clean($brand);
        if ($brand !== '') {
            $key = $this->brandKey($brand);
            if (isset($this->brandByName[$key])) {
                return $this->brandByName[$key];
            }
        }

        $nameToken = ' ' . $this->searchToken($name) . ' ';
        foreach ($this->brandTokens as $token => $id) {
            if ($token !== '' && str_contains($nameToken, ' ' . $token . ' ')) {
                return $id;
            }
        }

        return null;
    }

    private function stock(string $text, ?float $qty): array
    {
        $low = mb_strtolower($text);
        if ($qty !== null && $qty > 0) {
            return ['status' => 'in_stock', 'in_stock' => true, 'delivery_days' => 0];
        }
        if (str_contains($low, 'под заказ') || str_contains($low, 'заказ')) {
            return ['status' => 'preorder', 'in_stock' => false, 'delivery_days' => null];
        }
        if (str_contains($low, 'нет') || str_contains($low, 'отсут') || str_contains($low, 'снят')) {
            return ['status' => 'out_of_stock', 'in_stock' => false, 'delivery_days' => null];
        }
        if (str_contains($low, 'есть') || str_contains($low, 'налич') || str_contains($low, 'склад')) {
            return ['status' => 'in_stock', 'in_stock' => true, 'delivery_days' => 0];
        }

        return ['status' => 'unknown', 'in_stock' => false, 'delivery_days' => null];
    }

    private function money(string $value): ?float
    {
        $value = trim(str_replace(["\xc2\xa0", 'BYN', 'руб.', 'руб', 'р.'], ' ', $value));
        if ($value === '' || preg_match('/^(нет|n\/a|-|—)$/iu', $value)) {
            return null;
        }
        $value = preg_replace('/[^\d,.\-]/u', '', $value) ?? '';
        if ($value === '') {
            return null;
        }
        if (substr_count($value, ',') === 1 && substr_count($value, '.') === 0) {
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }

        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private function quantity(string $value): ?float
    {
        $value = preg_replace('/[^\d,.\-]/u', '', $value) ?? '';
        if ($value === '') {
            return null;
        }
        $value = str_replace(',', '.', $value);

        return is_numeric($value) ? (float) $value : null;
    }

    private function csvToRows(string $csv): array
    {
        $head = substr($csv, 0, 16384);
        $delimiter = substr_count($head, ';') > substr_count($head, ',') ? ';' : ',';
        $rows = [];
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csv);
        rewind($handle);
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    private function cell(array $row, array $columns, string $key): string
    {
        if (! isset($columns[$key])) {
            return '';
        }

        return $this->clean((string) ($row[$columns[$key]] ?? ''));
    }

    private function clean(string $value): string
    {
        $value = str_replace(["\xc2\xa0", "\r", "\n", "\t"], ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function normaliseHeader(string $value): string
    {
        $value = mb_strtolower($this->clean($value));
        $value = str_replace(['№', '#'], '', $value);

        return trim($value);
    }

    private function normArticle(string $article): string
    {
        return mb_strtoupper(preg_replace('/[^A-Za-zА-Яа-яЁё0-9]+/u', '', $article) ?? '');
    }

    private function looksLikeArticle(string $value): bool
    {
        if ($value === '') {
            return false;
        }
        if (substr_count($value, ' ') > 1 && ! preg_match('/^(НС|PS|VM|VT|KOTLOV)/iu', $value)) {
            return false;
        }
        if (preg_match('/^(НС|PS|VM|VT|KOTLOV|[A-ZА-Я]{1,6}[-\d])/iu', $value)) {
            return true;
        }

        return (bool) preg_match('/[A-Za-zА-Яа-яЁё]/u', $value) && (bool) preg_match('/\d/u', $value) && mb_strlen($value) <= 24;
    }

    private function needsSectionPrefix(string $name): bool
    {
        $name = $this->clean($name);
        if (mb_strlen($name) < 18) {
            return true;
        }

        return ! preg_match('/[А-Яа-яЁё]{3,}/u', $name);
    }

    private function brandKey(string $brand): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($brand)) ?? '');
    }

    private function brandToken(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^a-zа-яё0-9]+/u', ' ', $value) ?? '';
        $parts = array_values(array_filter(explode(' ', trim($value)), fn (string $part): bool => mb_strlen($part) > 1));

        return implode(' ', array_slice($parts, 0, 3));
    }

    private function searchToken(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^a-zа-яё0-9]+/u', ' ', $value) ?? '';
        $parts = array_values(array_filter(explode(' ', trim($value)), fn (string $part): bool => mb_strlen($part) > 1));

        return implode(' ', $parts);
    }

    private function model(string $name, string $brand): string
    {
        $name = mb_strtolower($name);
        $brand = mb_strtolower($brand);
        if ($brand !== '') {
            $name = str_replace($brand, ' ', $name);
        }
        $name = preg_replace('/[^a-zа-яё0-9]+/u', ' ', $name) ?? '';
        $stop = ['котел', 'котёл', 'насос', 'радиатор', 'конвектор', 'водонагреватель', 'бойлер', 'печь', 'камин'];
        $parts = array_values(array_filter(explode(' ', trim($name)), fn (string $part): bool => mb_strlen($part) > 1 && ! in_array($part, $stop, true)));

        return implode(' ', $parts);
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if ($this->clean((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function dedupeRows(array $rows): array
    {
        $seen = [];
        $result = [];
        foreach ($rows as $row) {
            $key = $row['norm_article'] !== '' ? $row['norm_article'] : mb_strtolower($row['name'] . '|' . ($row['price'] ?? ''));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $row;
        }

        return $result;
    }

    private function counts(array $rows, string $key): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $value = (string) ($row[$key] ?? '');
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }

        return $counts;
    }

    private function mapCounts(array $counts): array
    {
        return array_map(fn ($key, $value): array => [$key, $value], array_keys($counts), array_values($counts));
    }

    private function sheetActionRows(array $rows): array
    {
        $stats = [];
        foreach ($rows as $row) {
            $sheet = $row['sheet'];
            $stats[$sheet] ??= ['matched' => 0, 'unmatched' => 0, 'brand_missing' => 0, 'price_missing' => 0, 'rows' => 0];
            $action = $row['action'];
            if (isset($stats[$sheet][$action])) {
                $stats[$sheet][$action]++;
            }
            $stats[$sheet]['rows']++;
        }

        return array_map(
            fn (string $sheet, array $row): array => [
                mb_substr($sheet, 0, 32),
                $row['matched'],
                $row['unmatched'],
                $row['brand_missing'],
                $row['price_missing'],
                $row['rows'],
            ],
            array_keys($stats),
            array_values($stats)
        );
    }

    private function teplodvorSheetRows(array $rows): array
    {
        $stats = [];
        foreach ($rows as $row) {
            $sheet = $row['sheet'];
            $stats[$sheet] ??= ['matched' => 0, 'missing' => 0, 'rows' => 0];
            if (! empty($row['teplodvor_url'])) {
                $stats[$sheet]['matched']++;
            } else {
                $stats[$sheet]['missing']++;
            }
            $stats[$sheet]['rows']++;
        }

        return array_map(
            fn (string $sheet, array $row): array => [
                mb_substr($sheet, 0, 32),
                $row['matched'],
                $row['missing'],
                $row['rows'],
            ],
            array_keys($stats),
            array_values($stats)
        );
    }

    private function rnProfiSheetRows(array $rows): array
    {
        $stats = [];
        foreach ($rows as $row) {
            $sheet = $row['sheet'];
            $stats[$sheet] ??= ['matched' => 0, 'missing' => 0, 'rows' => 0];
            if (! empty($row['rn_profi_url'])) {
                $stats[$sheet]['matched']++;
            } else {
                $stats[$sheet]['missing']++;
            }
            $stats[$sheet]['rows']++;
        }

        return array_map(
            fn (string $sheet, array $row): array => [
                mb_substr($sheet, 0, 32),
                $row['matched'],
                $row['missing'],
                $row['rows'],
            ],
            array_keys($stats),
            array_values($stats)
        );
    }

    private function exampleHeaders(): array
    {
        $headers = ['sheet', 'row', 'article', 'brand', 'name', 'wholesale', 'retail', 'stock', 'action', 'matched_sku', 'confidence'];
        if ($this->option('rn-profi-cards')) {
            $headers[] = 'rn_profi';
            $headers[] = 'rn_title';
        }
        if ($this->option('teplodvor')) {
            $headers[] = 'teplodvor';
            $headers[] = 'td_score';
            $headers[] = 'cat_id';
        }

        return $headers;
    }

    private function exampleRows(array $rows): array
    {
        return array_map(function (array $row): array {
            $data = [
                mb_substr($row['sheet'], 0, 16),
                $row['row_number'],
                mb_substr($row['article'], 0, 18),
                mb_substr($row['resolved_brand'] ?: $row['brand'], 0, 16),
                mb_substr($row['name'], 0, 38),
                $row['price'] !== null ? number_format($row['price'], 2, '.', '') : '-',
                $row['retail_price'] !== null ? number_format($row['retail_price'], 2, '.', '') : '-',
                $row['stock']['status'],
                $row['action'],
                $row['matched_sku'] ?? '-',
                $row['confidence'] ?? '-',
            ];

            if ($this->option('rn-profi-cards')) {
                $data[] = $row['rn_profi_url'] ? mb_substr((string) $row['rn_profi_url'], 0, 56) : '-';
                $data[] = $row['rn_profi_title'] ? mb_substr((string) $row['rn_profi_title'], 0, 34) : '-';
            }

            if ($this->option('teplodvor')) {
                $data[] = $row['teplodvor_url'] ? mb_substr((string) $row['teplodvor_url'], 0, 56) : '-';
                $data[] = $row['teplodvor_score'] !== null ? number_format((float) $row['teplodvor_score'], 2, '.', '') : '-';
                $data[] = $row['teplodvor_category_id'] ?? '-';
            }

            return $data;
        }, $rows);
    }

    private function sku(int $productId): string
    {
        return (string) DB::table('products')->where('id', $productId)->value('sku');
    }

    private function catalogProductCountForBrand(string $brand): int|string
    {
        $brandId = $this->brandByName[$this->brandKey($brand)] ?? null;
        if ($brandId === null) {
            return '-';
        }

        return (int) DB::table('products')
            ->where('brand_id', $brandId)
            ->where('is_archived', false)
            ->count();
    }

    private function supplierId(): int
    {
        return (int) (DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id') ?? 0);
    }
}

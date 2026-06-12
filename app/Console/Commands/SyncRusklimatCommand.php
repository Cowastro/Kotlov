<?php

namespace App\Console\Commands;

use App\Services\AiContentEnricher;
use App\Services\Pricing\CurrencyPriceConverter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Synchronise Русклимат stock/prices with KOTLOV Marketplace.
 *
 * Reads a CSV or XLSX file exported from the supplier Google Sheet
 * (https://docs.google.com/spreadsheets/d/1knVFWPz3a6_aH7nvtsaeSka2nH0yAriGvZAEsJ6o-gE)
 * and links rows to existing KOTLOV products via a 5-level matching cascade.
 * Products that already exist in KOTLOV are never duplicated.
 * New products are created only when --create-new is passed.
 */
class SyncRusklimatCommand extends Command
{
    protected $signature = 'supplier:sync-rusklimat
        {--dry-run : Preview what would happen — nothing is written to the database}
        {--apply : Write changes to the database}
        {--limit= : Process only the first N rows (for testing)}
        {--stock-file= : Path to a local CSV/XLSX file (skips Google Sheet download)}
        {--price-file= : Alias for --stock-file}
        {--sheet-url= : Google Sheets URL to download (overrides built-in default)}
        {--no-images : Skip downloading product images}
        {--create-new : Create new products for rows that have no match in KOTLOV}
        {--only-existing : Only update supplier_products for already-matched products; skip create_candidates}
        {--enrich : Generate AI descriptions for new products (requires ANTHROPIC_API_KEY or AI_API_KEY)}';

    protected $description = 'Sync Русклимат prices and stock from Google Sheets (auto-download) or a local CSV/XLSX file.';

    private const SUPPLIER_CODE = 'rusklimat';
    private const SYNC_KEY      = 'rusklimat_stock';
    private const SOURCE_URL    = 'https://rusklimat.by/';

    /** Public Google Sheet with Rusklimat stock data */
    private const DEFAULT_SHEET_URL = 'https://docs.google.com/spreadsheets/d/1knVFWPz3a6_aH7nvtsaeSka2nH0yAriGvZAEsJ6o-gE/edit?gid=1615075392#gid=1615075392';

    /** Local cache path for downloaded sheet */
    private const SHEET_CACHE_PATH = 'supplier-cache/rusklimat.csv';

    // ── Stock status vocabulary ───────────────────────────────────────────────────

    private const STOCK_STATUS_MAP = [
        'в наличии'          => 'in_stock',
        'есть в наличии'     => 'in_stock',
        'на складе'          => 'in_stock',
        'in stock'           => 'in_stock',
        'мало'               => 'low_stock',
        'мало на складе'     => 'low_stock',
        'низкий остаток'     => 'low_stock',
        'под заказ'          => 'preorder',
        'на заказ'           => 'preorder',
        'под заказ (есть)'   => 'preorder',
        'preorder'           => 'preorder',
        'нет в наличии'      => 'out_of_stock',
        'нет'                => 'out_of_stock',
        'отсутствует'        => 'out_of_stock',
        'out of stock'       => 'out_of_stock',
        'снято'              => 'discontinued',
        'снято с производства' => 'discontinued',
        'discontinued'       => 'discontinued',
    ];

    // ── Rusklimat category keyword → KOTLOV category_id ──────────────────────────

    private const CATEGORY_MAP = [
        'газовый котел'            => 53,
        'газовые котлы'            => 53,
        'heating-boilers'          => 53,
        'gas-boilers'              => 53,
        'gas_boilers'              => 53,
        'твердотопливный'          => 54,
        'solid_fuel'               => 54,
        'электрокотел'             => 55,
        'electric_boilers'         => 55,
        'электрический котел'      => 55,
        'накопительный водонагреватель' => 98,
        'storage_water_heaters'    => 98,
        'электрический водонагреватель' => 98,
        'проточный водонагреватель' => 227,
        'instantaneous_water_heaters' => 227,
        'газовая колонка'          => 99,
        'gazovye-kolonki'          => 99,
        'бойлер косвенного нагрева' => 100,
        'bojlery-kosvennogo-nagreva' => 100,
        'комбинированный бойлер'   => 101,
        'kombinirovannye-bojlery'  => 101,
        'циркуляционный насос'     => 60,
        'circulating_pumps'        => 60,
        'насосная станция'         => 251,
        'pumping_stations'         => 251,
        'скважинный насос'         => 272,
        'borehole_pumps'           => 272,
        'канализационная установка' => 265,
        'sewer_installations'      => 265,
        'алюминиевый радиатор'     => 233,
        'heating-radiators-alum'   => 233,
        'биметаллический радиатор' => 236,
        'стальной панельный радиатор' => 235,
        'стальной трубчатый радиатор' => 235,
        'мембранный бак'           => 89,
        'расширительный бак'       => 89,
        'gidroakkumulatory'        => 89,
        'электрический конвектор'  => 281,
        'elektricheskie-konvektory' => 281,
        'конвектор'                => 281,  // generic convector (incl. шасси для конвектора)
        'тепловой вентилятор'      => 280,
        'teploventilyatory'        => 280,
        'автоматика'               => 58,
        'терморегулятор'           => 58,
        'termoregulyatory'         => 58,
        'стабилизатор напряжения'  => 59,
        'stabilizatory-napryazheniya' => 59,
        'фильтр'                   => 203,
        'filter'                   => 203,
        'счетчик газа'             => 96,
        'кондиционер'              => 304,
        'kondicionery'             => 304,
        'wall_mounted_air_conditioners' => 304,
        'mobile_air_conditioners'      => 304,
        'cassette_air_conditioners'    => 304,
        'duct_air_conditioners'        => 304,
        'floor_and_ceiling_air_conditioners' => 304,
        // ── Rusklimat-specific categories ────────────────────────────────────────
        'газовые колонки'                        => 99,
        'радиаторная арматура'                   => 195,
        'аксессуары и комплектующие для радиатор' => 195,
        'аксессуары для обогревател'             => 202,
        'тепловое оборудование'                  => 202,
        'тепловые насос'                         => 286,  // covers тепловые насосы (plural)
        'тепловой насос'                         => 286,
        'каминокомплект'                         => 105,
        'портал'                                 => 111,  // Портал/Порталы для каминов → Облицовки и порталы
        'облицовк'                               => 111,  // Облицовка/Облицовки
        'электрокамин'                           => 104,
        'биокамин'                               => 104,
        'умный дом'                              => 58,   // Smart home → Автоматика и терморегуляторы
        'зарядн'                                 => 202,  // Зарядная станция / Зарядных станций
        'климатический комплекс'                 => 304,
        'мойки воздуха'                          => 304,
        'сушилк'                                 => 304,  // Сушилки для рук, волос
        'монтажный профиль'                      => 195,  // → Комплектующие для отопления
        'монтажный комплект'                     => 285,  // Монтажный комплект → Монтажные комплекты
        'аксесуары к'                            => 195,  // Аксессуары к котлам (с опечаткой)
        'аксессуары к'                           => 195,
        'кабель для модуля'                      => 58,   // HDN/WFN cable → Автоматика
        'теплые полы'                            => 109,
        'теплый пол'                             => 109,
        'коллектор'                              => 242,
        'дренажный насос'                        => 265,
        'установки для отвода'                   => 265,
        'канализационн'                          => 265,  // Канализационная установка / установка канализационная
        'насос циркуляционный'                   => 60,   // "Насос циркуляционный" (обратный порядок слов)
        'центробежный насос'                     => 263,  // Центробежный насос → Центробежные
        'трап душев'                             => 193,  // Трап душевой → Трубы и фитинги
        'насос повышения давления'               => 60,
        'тэн'                                    => 299,
        'оборудование для систем'                => 195,
        'водоснабжени'                           => 251,  // Автоматическая установка для водоснабжения → Насосные станции
        // Stems (drop last char) to match both singular -ль/-ль and plural -ли
        'увлажнител'                             => 304,  // увлажнитель / увлажнители
        'осушител'                               => 304,  // осушитель / осушители
        'очистител'                              => 304,  // очиститель / очистители
        'вентилятор'                             => 304,
        'вытяжн'                                 => 304,  // вытяжная / вытяжной / вытяжных
        'полупромышленные'                       => 304,
        'труба'                                  => 193,
        'обогревател'                            => 202,  // обогреватель / обогреватели
        // Broad fallbacks — must remain last so specific entries above win
        'водонагревател'                         => 98,   // водонагреватель / водонагреватели
        'радиатор'                               => 87,
    ];

    // ── Words to strip when normalising a product name for matching ───────────────

    private const STRIP_WORDS = [
        'газовый', 'газовая', 'газовое', 'котел', 'котёл', 'котлы', 'настенный',
        'напольный', 'настенная', 'напольная', 'конденсационный', 'конвекционный',
        'двухконтурный', 'одноконтурный', 'одноконтурная', 'двухконтурная',
        'электрический', 'электрическая', 'твердотопливный', 'комбинированный',
        'водонагреватель', 'колонка', 'бойлер', 'котельная', 'косвенный',
        'накопительный', 'проточный', 'радиатор', 'алюминиевый', 'биметаллический',
        'стальной', 'чугунный', 'насос', 'циркуляционный', 'скважинный',
        'станция', 'насосная', 'установка', 'дымоход', 'коаксиальный',
        'кондиционер', 'конвектор', 'обогреватель', 'стабилизатор', 'напряжения',
        'система', 'фильтр', 'счётчик', 'счетчик', 'датчик', 'автоматика',
        'котлов', 'котла', 'настенный', 'напольный',
    ];

    // ── Index built from DB at startup ────────────────────────────────────────────

    /** @var array<string,int>  normalized_sku → product_id */
    private array $indexBySku = [];

    /** @var array<string,int>  supplier_article → product_id (already in supplier_products) */
    private array $indexBySupplierArticle = [];

    /** @var array<int,array<string,int>>  brand_id → [normalized_model → product_id] */
    private array $indexByBrandModel = [];

    /** @var array<int,string>  brand_id → brand_name (our DB) */
    private array $brandById = [];

    /** @var array<string,int>  lower(name) → brand_id */
    private array $brandByName = [];

    /** @var array<int,string>  category_id → name */
    private array $categoryById = [];

    // ── Entry point ───────────────────────────────────────────────────────────────

    public function handle(): int
    {
        $apply        = (bool) $this->option('apply');
        $dryRun       = (bool) $this->option('dry-run') || ! $apply;
        $limit        = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $createNew    = (bool) $this->option('create-new');
        $onlyExisting = (bool) $this->option('only-existing');
        $noImages     = (bool) $this->option('no-images');

        $this->line($apply && ! $this->option('dry-run')
            ? '<fg=red;options=bold>APPLY: database will be updated.</>'
            : '<fg=yellow;options=bold>DRY RUN: database will not be changed.</>');

        // ── Resolve stock file ────────────────────────────────────────────────────
        $stockFile = $this->option('stock-file') ?: $this->option('price-file');

        if ($stockFile !== null) {
            // Local file explicitly provided
            if (! file_exists($stockFile)) {
                $this->error("Stock file not found: {$stockFile}");
                return self::FAILURE;
            }
            $this->line("Using local file: {$stockFile}");
        } else {
            // Download from Google Sheets
            $sheetUrl = $this->option('sheet-url') ?: self::DEFAULT_SHEET_URL;
            try {
                $stockFile = $this->downloadGoogleSheet($sheetUrl);
            } catch (\RuntimeException $e) {
                if ($e->getMessage() === 'private') {
                    $this->error('Google Sheet недоступен для публичного экспорта.');
                    $this->line('Откройте доступ "Anyone with the link" на странице таблицы,');
                    $this->line('или используйте --stock-file=path/to/rusklimat.csv');
                    return self::FAILURE;
                }
                $this->error('Failed to download Google Sheet: ' . $e->getMessage());
                return self::FAILURE;
            }
        }

        // ── Parse file ────────────────────────────────────────────────────────────
        try {
            $rows = $this->parseFile($stockFile);
        } catch (\Throwable $e) {
            $this->error('Failed to parse file: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info(sprintf('Parsed %d rows from %s', count($rows), basename($stockFile)));

        if ($limit !== null && $limit > 0) {
            $rows = array_slice($rows, 0, $limit);
        }

        // ── Build lookup index ────────────────────────────────────────────────────
        $this->line('Building product index…');
        $this->buildIndex();
        $this->line(sprintf(
            'Index: %d SKUs, %d brand+model pairs, %d existing supplier articles',
            count($this->indexBySku),
            array_sum(array_map('count', $this->indexByBrandModel)),
            count($this->indexBySupplierArticle)
        ));

        // ── Classify every row ────────────────────────────────────────────────────
        $classified = $this->classify($rows, $onlyExisting);

        // ── Output or apply ───────────────────────────────────────────────────────
        if ($dryRun && ! ($apply && ! $this->option('dry-run'))) {
            return $this->showDryRun($classified);
        }

        return $this->applyChanges($classified, $createNew, $noImages);
    }

    // ── Google Sheet download ─────────────────────────────────────────────────────

    /**
     * Download the public Google Sheet as CSV, cache it locally and return the path.
     *
     * @throws \RuntimeException  'private' if the sheet is not publicly accessible.
     * @throws \RuntimeException  message on network/parse error.
     */
    private function downloadGoogleSheet(string $sheetUrl): string
    {
        $exportUrl = $this->buildExportUrl($sheetUrl);

        $this->line("Downloading stock sheet from Google Sheets…");

        $context = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'header'          => implode("\r\n", [
                    'User-Agent: Mozilla/5.0 (compatible; KotlovBot/1.0)',
                    'Accept: text/csv,text/plain,*/*',
                ]),
                'timeout'         => 45,
                'follow_location' => 1,
                'max_redirects'   => 10,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $content = @file_get_contents($exportUrl, false, $context);

        if ($content === false) {
            // Try without SSL verification as a fallback (some Windows setups lack CA bundle)
            $contextNoSsl = stream_context_create([
                'http' => [
                    'method'          => 'GET',
                    'header'          => 'User-Agent: Mozilla/5.0 (compatible; KotlovBot/1.0)',
                    'timeout'         => 45,
                    'follow_location' => 1,
                    'max_redirects'   => 10,
                ],
                'ssl' => [
                    'verify_peer'      => false,
                    'verify_peer_name' => false,
                ],
            ]);
            $content = @file_get_contents($exportUrl, false, $contextNoSsl);
        }

        if ($content === false || trim($content) === '') {
            throw new \RuntimeException(
                'Could not fetch URL. Check network access and that the sheet URL is correct.'
            );
        }

        // Detect login/auth redirect: Google returns an HTML page when sheet is private
        $trimmed = ltrim($content);
        if (str_starts_with($trimmed, '<') || stripos($content, '<html') !== false) {
            throw new \RuntimeException('private');
        }

        // Persist to cache
        $cachePath = storage_path('app/' . self::SHEET_CACHE_PATH);
        $cacheDir  = dirname($cachePath);

        if (! is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        file_put_contents($cachePath, $content);

        $this->info('Downloaded stock sheet to storage/app/' . self::SHEET_CACHE_PATH);

        return $cachePath;
    }

    /**
     * Convert a Google Sheets edit/view URL to a CSV export URL.
     *
     * Handles both formats:
     *   .../edit?gid=1615075392#gid=1615075392
     *   .../edit#gid=1615075392
     *   .../d/ID/edit
     */
    private function buildExportUrl(string $url): string
    {
        if (! preg_match('#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $url, $idMatch)) {
            throw new \RuntimeException(
                'Invalid Google Sheets URL — expected .../spreadsheets/d/{ID}/...'
            );
        }

        $id = $idMatch[1];

        // gid may appear as ?gid=, &gid=, or #gid=
        $gid = '0';
        if (preg_match('/[#?&]gid=(\d+)/', $url, $gidMatch)) {
            $gid = $gidMatch[1];
        }

        return "https://docs.google.com/spreadsheets/d/{$id}/export?format=csv&gid={$gid}";
    }

    // ── File parsing ──────────────────────────────────────────────────────────────

    /**
     * Load file and return an array of normalised row arrays.
     * Supports .csv, .xlsx, .xls, .ods.
     */
    private function parseFile(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($ext === 'csv') {
            return $this->parseCsv($path);
        }

        return $this->parseSpreadsheet($path);
    }

    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new \RuntimeException('Cannot open CSV: ' . $path);
        }

        // Detect delimiter
        $first = fgets($handle);
        rewind($handle);
        $delimiter = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';

        $rawRows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rawRows[] = array_map(fn ($v) => $this->cleanValue((string) $v), $row);
        }
        fclose($handle);

        return $this->normaliseRawRows($rawRows);
    }

    private function parseSpreadsheet(string $path): array
    {
        $sheet    = IOFactory::load($path)->getActiveSheet();
        $rawRows  = $sheet->toArray(null, true, true, false);
        $rawRows  = array_map(
            fn ($row) => array_map(fn ($v) => $this->cleanValue((string) ($v ?? '')), $row),
            $rawRows
        );

        return $this->normaliseRawRows($rawRows);
    }

    /**
     * Detect header row, map column names, return structured items.
     */
    private function normaliseRawRows(array $rawRows): array
    {
        // Find header row (first row that has ≥3 non-empty cells)
        $headerIdx = 0;
        foreach ($rawRows as $i => $row) {
            $filled = count(array_filter($row, fn ($v) => trim($v) !== ''));
            if ($filled >= 3) {
                $headerIdx = $i;
                break;
            }
        }

        $header = $rawRows[$headerIdx];
        $colMap = $this->detectColumns($header);

        if (empty($colMap)) {
            throw new \RuntimeException('Could not detect required columns (article/name) in header row: ' . implode(', ', $header));
        }

        $items = [];
        $seen  = [];

        for ($i = $headerIdx + 1; $i < count($rawRows); $i++) {
            $row     = $rawRows[$i];
            $article = $this->getCol($row, $colMap, 'article');

            if ($article === '') {
                continue;
            }

            $normArticle = $this->normaliseArticle($article);

            if (isset($seen[$normArticle])) {
                $items[] = array_merge($this->extractRow($row, $colMap, $article, $normArticle), ['_action' => 'skipped_duplicate']);
                continue;
            }

            $seen[$normArticle] = true;
            $items[]            = $this->extractRow($row, $colMap, $article, $normArticle);
        }

        return $items;
    }

    private function extractRow(array $row, array $colMap, string $article, string $normArticle): array
    {
        $priceRaw       = $this->getCol($row, $colMap, 'price');
        $price          = $priceRaw !== '' ? (float) str_replace([' ', ','], ['', '.'], $priceRaw) : null;
        $retailPriceRaw = $this->getCol($row, $colMap, 'retail_price');
        $retailPrice    = $retailPriceRaw !== '' ? (float) str_replace([' ', ','], ['', '.'], $retailPriceRaw) : null;

        $qtyRaw = $this->getCol($row, $colMap, 'quantity');
        $qty    = is_numeric($qtyRaw) ? (int) $qtyRaw : null;

        $currency = mb_strtoupper($this->getCol($row, $colMap, 'currency') ?: 'BYN');

        $statusText = $this->getCol($row, $colMap, 'status');
        if ($statusText === '' && $qty !== null) {
            $statusText = $qty > 0 ? 'В наличии' : 'Нет в наличии';
        }

        return [
            'article'      => $article,
            'norm_article' => $normArticle,
            'name'         => $this->getCol($row, $colMap, 'name'),
            'brand'        => $this->getCol($row, $colMap, 'brand'),
            'category'     => $this->getCol($row, $colMap, 'category'),
            'price'        => $price,
            'retail_price' => $retailPrice,
            'currency'     => $currency,
            'quantity'     => $qty,
            'status_text'  => $statusText,
            'warehouse'    => $this->getCol($row, $colMap, 'warehouse'),
            '_action'      => null,
        ];
    }

    /**
     * Map header cell values to column indices.
     * Returns ['article' => 2, 'name' => 3, …]
     */
    private function detectColumns(array $header): array
    {
        $map = [];

        $patterns = [
            'article'   => ['артикул', 'article', 'sku', 'код', 'code', 'vendor_code', 'арт'],
            'name'      => ['наименование', 'название', 'товар', 'name', 'product', 'позиция', 'модель', 'model'],
            'brand'     => ['бренд', 'производитель', 'brand', 'марка', 'manufacturer'],
            'category'  => ['категория', 'группа', 'раздел', 'category', 'group', 'section'],
            'price'        => ['дилер', 'дилерская', 'закупка', 'закупочная', 'цена дилера', 'price', 'цена', 'стоимость'],
            'retail_price' => ['розница', 'мрц', 'рекомендованная', 'ррц', 'retail', 'розничная'],
            'currency'  => ['валюта', 'currency', 'curr'],
            'quantity'  => ['остаток', 'количество', 'кол-во', 'qty', 'quantity', 'stock', 'наличие'],
            'status'    => ['статус', 'наличие', 'status', 'availability', 'доступность'],
            'warehouse' => ['склад', 'warehouse', 'место', 'хранение'],
        ];

        // Two-pass: collect best match per key (lowest pattern-priority wins over CSV position)
        $candidates = []; // key => [pattern_priority, col_idx]

        foreach ($header as $idx => $cell) {
            $lower = mb_strtolower(trim($cell));
            foreach ($patterns as $key => $keywords) {
                foreach ($keywords as $priority => $kw) {
                    if ($lower === $kw || str_starts_with($lower, $kw)) {
                        if (! isset($candidates[$key]) || $priority < $candidates[$key][0]) {
                            $candidates[$key] = [$priority, $idx];
                        }
                        break;
                    }
                }
            }
        }

        foreach ($candidates as $key => [, $idx]) {
            $map[$key] = $idx;
        }

        // Accept file if at minimum article + (name or price) are detected
        if (! isset($map['article']) || (! isset($map['name']) && ! isset($map['price']))) {
            return [];
        }

        return $map;
    }

    private function getCol(array $row, array $colMap, string $key): string
    {
        if (! isset($colMap[$key])) {
            return '';
        }
        return trim((string) ($row[$colMap[$key]] ?? ''));
    }

    // ── Product index ─────────────────────────────────────────────────────────────

    private function buildIndex(): void
    {
        // Brands
        DB::table('brands')->get(['id', 'name'])->each(function ($b) {
            $this->brandById[(int) $b->id]       = $b->name;
            $this->brandByName[mb_strtolower($b->name)] = (int) $b->id;
        });

        // Categories
        DB::table('categories')->get(['id', 'name'])->each(function ($c) {
            $this->categoryById[(int) $c->id] = $c->name;
        });

        // Existing supplier_products for this supplier
        $supplierId = (int) (DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id') ?? 0);
        if ($supplierId > 0) {
            DB::table('supplier_products')
                ->where('supplier_id', $supplierId)
                ->whereNotNull('product_id')
                ->get(['supplier_article', 'product_id'])
                ->each(function ($sp) {
                    $this->indexBySupplierArticle[$this->normaliseArticle($sp->supplier_article)] = (int) $sp->product_id;
                });
        }

        // Products — index by SKU and by brand+model
        DB::table('products')
            ->where('is_archived', false)
            ->get(['id', 'sku', 'name', 'brand_id', 'price', 'in_stock'])
            ->each(function ($p) {
                // L2: by exact SKU
                $this->indexBySku[mb_strtoupper(trim($p->sku ?? ''))] = (int) $p->id;

                // L3: by brand + normalised model
                $brandId = (int) $p->brand_id;
                if ($brandId > 0) {
                    $model = $this->extractModel((string) $p->name, $this->brandById[$brandId] ?? '');
                    if ($model !== '') {
                        $this->indexByBrandModel[$brandId][$model] = (int) $p->id;
                    }
                }
            });
    }

    // ── Classification ────────────────────────────────────────────────────────────

    private function classify(array $rows, bool $onlyExisting): array
    {
        $classified = [];

        foreach ($rows as $row) {
            if ($row['_action'] === 'skipped_duplicate') {
                $classified[] = $row + [
                    'matched_product_id'  => null,
                    'matched_sku'         => null,
                    'confidence'          => null,
                    'action'              => 'skipped_duplicate',
                    'resolved_brand_id'   => null,
                    'resolved_category_id' => null,
                    'stock_status'        => null,
                    'price_byn'           => null,
                ];
                continue;
            }

            $match     = $this->matchProduct($row);
            $brandId   = $this->resolveBrand($row['brand']);
            $catId     = $this->resolveCategory($row['category'], $row['name']);
            $stockStat = $this->normaliseStockStatus($row['status_text'], $row['quantity']);
            $priceByn  = $row['currency'] === 'BYN'
                ? $row['price']
                : CurrencyPriceConverter::convertToByn($row['price'], $row['currency'], $this->supplierCurrencyRate());

            // Determine action
            if ($match !== null) {
                $existing = DB::table('supplier_products')
                    ->where('supplier_id', $this->supplierId())
                    ->where('supplier_article', $row['norm_article'])
                    ->first(['price_byn', 'in_stock', 'stock_quantity', 'stock_status']);

                if ($existing) {
                    $priceChanged = $priceByn !== null && abs((float) $existing->price_byn - $priceByn) > 0.01;
                    $stockChanged = ((int) $existing->stock_quantity !== $row['quantity'])
                        || ($existing->stock_status !== $stockStat);

                    $action = match (true) {
                        $priceChanged && $stockChanged => 'update_price',
                        $priceChanged                  => 'update_price',
                        $stockChanged                  => 'update_stock',
                        default                        => 'matched',
                    };
                } else {
                    $action = 'update_price'; // first time linking
                }
            } elseif ($brandId === null) {
                $action = 'brand_missing';
            } elseif ($catId === null) {
                $action = 'category_missing';
            } elseif ($onlyExisting) {
                $action = 'skipped_no_match';
            } elseif (! in_array($stockStat, ['in_stock', 'low_stock', 'preorder'], true)) {
                $action = 'skipped_zero_stock';
            } else {
                $action = 'create_candidate';
            }

            $classified[] = $row + [
                'matched_product_id'   => $match ? $match['product_id'] : null,
                'matched_sku'          => $match ? $match['sku'] : null,
                'confidence'           => $match ? $match['confidence'] : null,
                'action'               => $action,
                'resolved_brand_id'    => $brandId,
                'resolved_category_id' => $catId,
                'stock_status'         => $stockStat,
                'price_byn'            => $priceByn,
            ];
        }

        return $classified;
    }

    // ── Matching ──────────────────────────────────────────────────────────────────

    /**
     * Returns ['product_id' => int, 'sku' => string, 'confidence' => string] or null.
     *
     * Matching levels:
     *   L1 – exact supplier_article already in supplier_products for this supplier
     *   L2 – article matches products.sku
     *   L3 – brand + normalised model name
     *   L4 – brand + article as model tokens
     *   L5 – low confidence → manual_review flag
     */
    private function matchProduct(array $row): ?array
    {
        $normArticle = $row['norm_article'];
        $brandId     = $this->resolveBrand($row['brand']);

        // L1: already mapped in supplier_products
        if (isset($this->indexBySupplierArticle[$normArticle])) {
            $pid = $this->indexBySupplierArticle[$normArticle];
            $sku = (string) DB::table('products')->where('id', $pid)->value('sku');
            return ['product_id' => $pid, 'sku' => $sku, 'confidence' => 'exact_supplier_article'];
        }

        // L2: article matches products.sku
        $upperArticle = mb_strtoupper($normArticle);
        if (isset($this->indexBySku[$upperArticle])) {
            $pid = $this->indexBySku[$upperArticle];
            return ['product_id' => $pid, 'sku' => $upperArticle, 'confidence' => 'exact_sku'];
        }

        // L3 + L4 require a resolved brand
        if ($brandId === null) {
            return null;
        }

        $brandModels = $this->indexByBrandModel[$brandId] ?? [];

        if (empty($brandModels)) {
            return null;
        }

        // L3: brand + model extracted from supplier name
        $supplierModel = $this->extractModel($row['name'], $this->brandById[$brandId] ?? '');
        if ($supplierModel !== '' && isset($brandModels[$supplierModel])) {
            $pid = $brandModels[$supplierModel];
            $sku = (string) DB::table('products')->where('id', $pid)->value('sku');
            return ['product_id' => $pid, 'sku' => $sku, 'confidence' => 'brand_model_name'];
        }

        // L4: brand + article treated as model code
        $articleModel = $this->normaliseModel($normArticle);
        if ($articleModel !== '' && isset($brandModels[$articleModel])) {
            $pid = $brandModels[$articleModel];
            $sku = (string) DB::table('products')->where('id', $pid)->value('sku');
            return ['product_id' => $pid, 'sku' => $sku, 'confidence' => 'brand_article_model'];
        }

        // L5: partial token match — flag for manual review
        if ($supplierModel !== '') {
            $supplierTokens = explode(' ', $supplierModel);
            foreach ($brandModels as $indexModel => $pid) {
                $indexTokens = explode(' ', $indexModel);
                $common      = count(array_intersect($supplierTokens, $indexTokens));
                $total       = max(count($supplierTokens), count($indexTokens));
                if ($total > 0 && ($common / $total) >= 0.7) {
                    $sku = (string) DB::table('products')->where('id', $pid)->value('sku');
                    return ['product_id' => $pid, 'sku' => $sku, 'confidence' => 'manual_review'];
                }
            }
        }

        return null;
    }

    // ── Normalisation helpers ─────────────────────────────────────────────────────

    private function normaliseArticle(string $s): string
    {
        $s = mb_strtoupper(trim($s));
        $s = str_replace(["\u{AB}", "\u{BB}", '"', "'", "\u{2013}", "\u{2014}"], ['', '', '', '', '-', '-'], $s);
        return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    }

    /**
     * Extract the model portion from a full product name.
     * e.g. "Газовый котел Ariston GENUS 36 FF NG" → "GENUS 36 FF NG"
     */
    private function extractModel(string $productName, string $brandName): string
    {
        $name = mb_strtoupper($productName);

        // Strip brand name first
        if ($brandName !== '') {
            $name = preg_replace('/' . preg_quote(mb_strtoupper($brandName), '/') . '/u', '', $name) ?? $name;
        }

        // Strip category-type words
        foreach (self::STRIP_WORDS as $word) {
            $name = preg_replace('/\b' . preg_quote(mb_strtoupper($word), '/') . '\b/u', '', $name) ?? $name;
        }

        return $this->normaliseModel($name);
    }

    private function normaliseModel(string $s): string
    {
        $s = mb_strtoupper($s);
        $s = preg_replace('/[^А-ЯЁA-Z0-9\-\/+.]/u', ' ', $s) ?? $s;
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return trim($s);
    }

    private function normaliseStockStatus(string $text, ?int $qty): string
    {
        if ($text !== '') {
            $key = mb_strtolower(trim($text));
            if (isset(self::STOCK_STATUS_MAP[$key])) {
                return self::STOCK_STATUS_MAP[$key];
            }
        }

        if ($qty === null) {
            return 'unknown';
        }

        if ($qty > 5) {
            return 'in_stock';
        }

        if ($qty > 0) {
            return 'low_stock';
        }

        return 'out_of_stock';
    }

    private function resolveBrand(string $brandName): ?int
    {
        if ($brandName === '') {
            return null;
        }

        $key = mb_strtolower(trim($brandName));

        return $this->brandByName[$key]
            ?? $this->partialBrandMatch($key);
    }

    private function partialBrandMatch(string $key): ?int
    {
        foreach ($this->brandByName as $dbName => $id) {
            if (str_starts_with($key, $dbName) || str_starts_with($dbName, $key)) {
                return $id;
            }
        }
        return null;
    }

    private function resolveCategory(string $catRaw, string $name): ?int
    {
        $combined = mb_strtolower($catRaw . ' ' . $name);

        foreach (self::CATEGORY_MAP as $keyword => $catId) {
            if (str_contains($combined, $keyword)) {
                return $catId;
            }
        }

        return null;
    }

    private function supplierCurrencyRate(): float
    {
        return (float) (DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('currency_rate') ?? 1);
    }

    private function supplierId(): int
    {
        static $id = null;
        $id ??= (int) (DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id') ?? 0);
        return $id;
    }

    private function syncId(): ?int
    {
        static $id = null;
        if ($id === null) {
            $id = DB::table('supplier_syncs')->where('key', self::SYNC_KEY)->value('id');
            $id = $id !== null ? (int) $id : null;
        }
        return $id;
    }

    // ── Dry-run output ────────────────────────────────────────────────────────────

    private function showDryRun(array $classified): int
    {
        $tableRows = [];
        $stats     = array_fill_keys([
            'matched', 'update_price', 'update_stock', 'create_candidate',
            'brand_missing', 'category_missing', 'skipped_duplicate',
            'skipped_no_match', 'skipped_zero_stock', 'manual_review',
        ], 0);

        foreach ($classified as $row) {
            $action = $row['action'] ?? 'unknown';
            if (isset($stats[$action])) {
                $stats[$action]++;
            }

            $confidence = $row['confidence'] ?? '';
            if ($confidence === 'manual_review') {
                $action = 'manual_review';
                $stats['manual_review'] = ($stats['manual_review'] ?? 0) + 1;
            }

            $tableRows[] = [
                mb_substr($row['article'],               0, 28),
                mb_substr($row['name'] ?? '',            0, 44),
                mb_substr($row['brand'] ?? '',           0, 16),
                $row['matched_sku']                   ?? '—',
                $row['price_byn']     !== null ? number_format($row['price_byn'], 2)     : '—',
                $row['retail_price']  !== null ? number_format($row['retail_price'], 2)  : '—',
                $row['quantity']      !== null ? (string) $row['quantity']               : '—',
                $row['stock_status']           ?? '—',
                $action,
                mb_substr($confidence,                   0, 26),
            ];
        }

        $this->table(
            ['article', 'name', 'brand', 'matched_sku', 'price_byn(закупка)', 'retail_price(розница)', 'qty', 'stock_status', 'action', 'confidence'],
            $tableRows
        );

        $this->line('');
        $this->table(['action', 'count'], array_map(
            fn ($k, $v) => [$k, $v],
            array_keys($stats),
            array_values($stats)
        ));

        $this->showMissingSummary($classified);
        $this->line('');
        $this->line('Run with <fg=green>--apply</> to write changes to the database.');
        $this->line('Run with <fg=green>--apply --create-new</> to also create missing products.');

        return self::SUCCESS;
    }

    // ── Apply changes ─────────────────────────────────────────────────────────────

    private function applyChanges(array $classified, bool $createNew, bool $noImages): int
    {
        $now         = now();
        $supplierId  = $this->ensureSupplier($now);
        $syncId      = $this->ensureSupplierSync($now);
        $doEnrich    = (bool) $this->option('enrich');
        $enricher    = new AiContentEnricher();

        if ($doEnrich && ! $enricher->isAvailable()) {
            $this->warn('--enrich: no AI provider configured (set ANTHROPIC_API_KEY or AI_API_KEY), skipping content generation.');
            $doEnrich = false;
        }

        $stats = array_fill_keys([
            'matched', 'update_price', 'update_stock',
            'created', 'create_candidate', 'skipped_duplicate',
            'skipped_no_match', 'skipped_zero_stock', 'brand_missing', 'category_missing',
            'manual_review', 'errors',
        ], 0);

        foreach ($classified as $row) {
            $action     = $row['action'] ?? 'unknown';
            $confidence = $row['confidence'] ?? '';

            if (in_array($action, ['skipped_duplicate', 'skipped_no_match', 'skipped_zero_stock'], true)) {
                $stats[$action] = ($stats[$action] ?? 0) + 1;
                continue;
            }

            if ($action === 'brand_missing' || $action === 'category_missing') {
                $stats[$action]++;
                continue;
            }

            if ($confidence === 'manual_review') {
                $stats['manual_review']++;
                $this->warn(sprintf(
                    '[manual_review] %s  →  %s  (confidence: %s)',
                    $row['article'],
                    $row['matched_sku'] ?? '—',
                    $confidence
                ));
                continue;
            }

            try {
                if ($row['matched_product_id'] !== null) {
                    // Product already exists — upsert supplier_products
                    $this->upsertSupplierProduct($row, (int) $row['matched_product_id'], (string) ($row['matched_sku'] ?? ''), $supplierId, $syncId, $now);

                    if ($action === 'update_price') {
                        $stats['update_price']++;
                        $this->line(sprintf(
                            '[update_price]  %s  %s  %.2f BYN  qty=%s',
                            $row['article'],
                            $row['matched_sku'],
                            $row['price_byn'] ?? 0,
                            $row['quantity'] ?? '—'
                        ));
                    } elseif ($action === 'update_stock') {
                        $stats['update_stock']++;
                        $this->line(sprintf(
                            '[update_stock]  %s  %s  qty=%s  status=%s',
                            $row['article'],
                            $row['matched_sku'],
                            $row['quantity'] ?? '—',
                            $row['stock_status'] ?? '—'
                        ));
                    } else {
                        $stats['matched']++;
                    }
                } elseif ($createNew && $row['resolved_brand_id'] !== null && $row['resolved_category_id'] !== null) {
                    // Create new product
                    $productId = $this->createProduct($row, $supplierId, $now, $noImages);
                    $sku       = (string) DB::table('products')->where('id', $productId)->value('sku');
                    $this->upsertSupplierProduct($row, $productId, $sku, $supplierId, $syncId, $now);
                    $stats['created']++;
                    $this->line(sprintf('[create]  %s  →  %s  %.2f BYN', $row['article'], $sku, $row['price_byn'] ?? 0));

                    // AI content enrichment for newly created products
                    if ($doEnrich) {
                        $brandName = $this->brandById[(int) $row['resolved_brand_id']] ?? '';
                        $aiText    = $enricher->enrich($row['name'], $brandName, null, []);
                        if ($aiText) {
                            DB::table('products')->where('id', $productId)->update(['content' => $aiText]);
                        }
                    }
                } else {
                    $stats['create_candidate']++;
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn(sprintf('[error] %s: %s', $row['article'], $e->getMessage()));
            }
        }

        $this->showMissingSummary($classified);

        $this->line('');
        $this->table(
            ['action', 'count'],
            array_map(fn ($k, $v) => [$k, $v], array_keys($stats), array_values($stats))
        );

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    // ── Persistence ───────────────────────────────────────────────────────────────

    private function upsertSupplierProduct(array $row, int $productId, string $productSku, int $supplierId, ?int $syncId, $now): void
    {
        $inStock = in_array($row['stock_status'], ['in_stock', 'low_stock', 'preorder'], true);

        DB::table('supplier_products')->updateOrInsert(
            ['supplier_id' => $supplierId, 'supplier_article' => $row['norm_article']],
            [
                'supplier_article_normalized' => $row['norm_article'],
                'supplier_sync_id'            => $syncId,
                'product_id'                  => $productId,
                'product_sku'                 => $productSku,
                'supplier_name'               => $row['name'],
                'source_url'                  => self::SOURCE_URL,
                'price'                       => $row['price'],
                'currency'                    => $row['currency'],
                'currency_rate'               => $row['currency'] === 'BYN' ? 1.0 : $this->supplierCurrencyRate(),
                'price_byn'                   => $row['price_byn'],
                'in_stock'                    => $inStock,
                'stock_quantity'              => $row['quantity'],
                'stock_status'                => $row['stock_status'],
                'stock_text'                  => $row['status_text'] !== '' ? $row['status_text'] : null,
                'warehouse_name'              => $row['warehouse'] !== '' ? $row['warehouse'] : null,
                'delivery_days'               => $inStock ? 0 : ($row['stock_status'] === 'preorder' ? 7 : null),
                'match_status'                => 'matched',
                'match_confidence'            => $row['confidence'],
                'raw'                         => json_encode([
                    'article'  => $row['article'],
                    'brand'    => $row['brand'],
                    'category' => $row['category'],
                ], JSON_UNESCAPED_UNICODE),
                'last_synced_at'              => $now,
                'last_stock_synced_at'        => $now,
                'updated_at'                  => $now,
                'created_at'                  => $now,
            ]
        );
    }

    private function createProduct(array $row, int $supplierId, $now, bool $noImages): int
    {
        $brandId  = (int) $row['resolved_brand_id'];
        $catId    = (int) $row['resolved_category_id'];
        $brand    = $this->brandById[$brandId] ?? '';
        $name     = $this->buildProductName($row['name'], $brand);

        // Use retail price from CSV if available; convert currency if needed
        $retailByn = null;
        if ($row['retail_price'] !== null) {
            $retailByn = $row['currency'] === 'BYN'
                ? $row['retail_price']
                : CurrencyPriceConverter::convertToByn($row['retail_price'], $row['currency'], $this->supplierCurrencyRate());
        }

        return (int) DB::table('products')->insertGetId([
            'category_id'       => $catId,
            'brand_id'          => $brandId,
            'supplier_id'       => null,
            'name'              => $name,
            'h1'                => $name,
            'sku'               => $this->nextKotlovSku(),
            'slug'              => $this->uniqueSlug($name),
            'price'             => $retailByn ?? 0,
            'price_old'         => null,
            'currency'          => 'BYN',
            'content'           => null,
            'short_description' => null,
            'images'            => json_encode([]),
            'specs'             => json_encode([]),
            'unit'              => 'шт',
            'warranty'          => null,
            'is_active'         => true,
            'is_archived'       => false,
            'in_stock'          => in_array($row['stock_status'], ['in_stock', 'low_stock'], true),
            'stock_qty'         => $row['quantity'],
            'is_featured'       => false,
            'is_new'            => true,
            'is_sale'           => false,
            'sort_order'        => 0,
            'meta_title'        => $name . ' купить в %city%',
            'meta_keywords'     => $brand . ', ' . $name,
            'meta_description'  => $name . ' — купить по лучшей цене в Беларуси.',
            'rating'            => 0,
            'reviews_count'     => 0,
            'views_count'       => 0,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);
    }

    private function buildProductName(string $supplierName, string $brand): string
    {
        $name = trim($supplierName);
        // If brand not already in name, prepend it
        if ($brand !== '' && ! str_contains(mb_strtolower($name), mb_strtolower($brand))) {
            $name = $brand . ' ' . $name;
        }
        return preg_replace('/\s+/u', ' ', $name) ?? $name;
    }

    // ── Supplier / sync registration ──────────────────────────────────────────────

    private function ensureSupplier($now): int
    {
        $existing = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->first();

        if ($existing) {
            DB::table('suppliers')->where('id', $existing->id)->update([
                'name'       => 'Русклимат',
                'contact'    => self::SOURCE_URL,
                'is_active'  => true,
                'updated_at' => $now,
                // currency_rate intentionally NOT overwritten here
            ]);
            return (int) $existing->id;
        }

        return (int) DB::table('suppliers')->insertGetId([
            'code'          => self::SUPPLIER_CODE,
            'name'          => 'Русклимат',
            'currency'      => 'BYN',
            'currency_rate' => 1,
            'contact'       => self::SOURCE_URL,
            'notes'         => 'Интернет-магазин климатической и отопительной техники (rusklimat.by).',
            'is_active'     => true,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
    }

    private function ensureSupplierSync($now): ?int
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('supplier_syncs')) {
            return null;
        }

        DB::table('supplier_syncs')->updateOrInsert(
            ['key' => self::SYNC_KEY],
            [
                'name'            => 'Русклимат',
                'code'            => self::SUPPLIER_CODE,
                'title'           => 'Русклимат: остатки и цены',
                'description'     => 'Загружает цены и остатки из Google Sheets. Сопоставляет по артикулу и бренд+модель.',
                'command'         => 'supplier:sync-rusklimat',
                'source_url'      => self::SOURCE_URL,
                'image_disk_path' => 'img/products/rusklimat',
                'is_active'       => true,
                'last_run_at'     => $now,
                'last_status'     => 'running',
                'updated_at'      => $now,
                'created_at'      => $now,
            ]
        );

        return (int) DB::table('supplier_syncs')->where('key', self::SYNC_KEY)->value('id');
    }

    // ── Summary / reporting ───────────────────────────────────────────────────────

    private function showMissingSummary(array $classified): void
    {
        $missingBrands     = [];
        $missingCategories = [];

        $emptyBrandCount = 0;
        foreach ($classified as $row) {
            if ($row['action'] === 'brand_missing') {
                if ($row['brand'] !== '') {
                    $missingBrands[$row['brand']] = ($missingBrands[$row['brand']] ?? 0) + 1;
                } else {
                    $emptyBrandCount++;
                }
            }
            if ($row['action'] === 'category_missing' && $row['category'] !== '') {
                $missingCategories[$row['category']] = ($missingCategories[$row['category']] ?? 0) + 1;
            }
        }

        if (! empty($missingBrands)) {
            arsort($missingBrands);
            $this->warn(sprintf("\n⚠  Missing brands (%d unique):", count($missingBrands)));
            $this->table(['brand', 'rows'], array_map(fn ($b, $c) => [$b, $c], array_keys($missingBrands), $missingBrands));
        }
        if ($emptyBrandCount > 0) {
            $this->warn(sprintf("\n⚠  %d row(s) have empty brand in CSV — skipped.", $emptyBrandCount));
        }

        if (! empty($missingCategories)) {
            arsort($missingCategories);
            $this->warn(sprintf("\n⚠  Missing categories (%d unique):", count($missingCategories)));
            $this->table(['category', 'rows'], array_map(fn ($c, $n) => [$c, $n], array_keys($missingCategories), $missingCategories));
        }
    }

    // ── SKU generation ────────────────────────────────────────────────────────────

    private function nextKotlovSku(): string
    {
        $max = DB::table('products')
            ->where('sku', 'like', 'KOTLOV-%')
            ->pluck('sku')
            ->map(fn ($s) => preg_match('/^KOTLOV-(\d+)$/', (string) $s, $m) ? (int) $m[1] : 0)
            ->max() ?? 0;

        $next = max(0, (int) $max) + 1;

        do {
            $sku = sprintf('KOTLOV-%06d', $next++);
        } while (DB::table('products')->where('sku', $sku)->exists());

        return $sku;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'rusklimat-product';
        $slug = $base;
        $i    = 2;

        while (DB::table('products')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    // ── Text helpers ──────────────────────────────────────────────────────────────

    private function cleanValue(string $v): string
    {
        $v = html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $v = str_replace("\u{A0}", ' ', $v); // non-breaking space
        return trim(preg_replace('/\s+/u', ' ', $v) ?? $v);
    }
}

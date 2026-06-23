<?php

namespace App\Console\Commands;

use App\Services\AiContentEnricher;
use App\Services\ProductSourceEnricher;
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
        {--offset=0 : Skip N parsed rows after brand/availability filters}
        {--price-file= : Local XLSX/CSV file, skips Google Sheet download}
        {--sheet-url= : Google Sheets URL}
        {--google-csv-sheet= : Download only one Google Sheet tab as CSV, useful when full XLSX export is too large}
        {--google-csv-gid= : Download only one Google Sheet tab by gid as CSV}
        {--brand=* : Process only these resolved brands, repeatable or comma-separated}
        {--exclude-brand=* : Skip these resolved brands, repeatable or comma-separated}
        {--available-only : Keep only rows that are in stock or have short delivery}
        {--max-delivery-days= : Maximum delivery days for --available-only short-delivery rows}
        {--teplodvor : Match price rows to storage/teplodvor_index.json product cards}
        {--teplodvor-min-score=0.70 : Minimum slug score for Teplodvor matching}
        {--teplodvor-slug-filter= : Limit Teplodvor candidates by slug substring, defaults to resolved brand slug}
        {--teplodvor-brand-page= : Teplodvor brand listing URL to crawl for article matches}
        {--teplodvor-crawl-pages=40 : Maximum Teplodvor brand/listing pages to crawl}
        {--teplodvor-debug : Print Teplodvor crawl URL and article-token samples}
        {--teplodvor-auto-create : Mark confident Teplodvor card matches as safe create_new decisions without AI}
        {--teplodvor-auto-min-score=0.92 : Minimum Teplodvor score for --teplodvor-auto-create}
        {--teplodvor-auto-only : With --teplodvor-auto-create, write only Teplodvor auto decisions and skip AI for remaining rows}
        {--teplodvor-probe-missing : Try likely Teplodvor product URLs for unmatched rows instead of rebuilding the full index}
        {--rn-profi-cards : Match price rows to rn-profi.by product cards by article}
        {--refresh-rn-profi-cards : Ignore cached RN-Profi card matches and re-check the site}
        {--rn-profi-crawl-pages=160 : Maximum RN-Profi site pages to crawl for card/article index}
        {--rn-profi-card-limit=100 : Maximum uncached RN-Profi articles to check per run, 0 means no limit}
        {--varmega-official : Match Varmega rows to official varmega.ru product cards by supplier article}
        {--varmega-sitemap=https://varmega.ru/sitemap-iblock-43.xml : Official Varmega product sitemap URL}
        {--varmega-refresh-index : Rebuild cached official Varmega sitemap index}
        {--varmega-deep-index : Fetch official Varmega product pages and extract articles from page HTML}
        {--varmega-deep-pages=0 : Maximum official Varmega pages to fetch for deep index, 0 means all}
        {--varmega-auto-create : Mark exact official Varmega card matches as safe create_new decisions without AI}
        {--varmega-auto-only : With --varmega-auto-create, write only official Varmega auto decisions and skip AI for remaining rows}
        {--ai-match : Ask configured AI provider to prepare safe match/create decisions without database writes}
        {--ai-match-limit=20 : Maximum rows to send to AI in this run, 0 means all current rows}
        {--ai-batch-size=1 : Send this many rows in one AI request for faster local audits}
        {--ai-min-confidence=85 : Confidence threshold for can_apply_after_review recommendations}
        {--ai-provider= : AI provider override for matching: openai or configured}
        {--ai-model= : Override AI model for matching; defaults to AI_MATCH_MODEL or current AI_MODEL}
        {--ai-output= : Output path for AI JSON decisions; defaults to storage/app/reports/rn-profi}
        {--unmatched-report= : Write unmatched rows report as CSV/JSON; defaults to storage/app/reports/rn-profi when value is "auto"}
        {--apply-ai-decisions= : Read local AI decisions JSON and apply safe link/create actions without calling AI}
        {--enrich-created : After creating products from AI decisions, parse source URL for photos/specs/content}
        {--update-existing-categories : With --apply-ai-decisions, update categories for already linked supplier products from source URL mapping}
        {--category-update-only : With --apply-ai-decisions, only create mapped categories and update existing supplier-linked products; do not link or create products}
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
    private const VARMEGA_OFFICIAL_INDEX_CACHE = 'supplier-cache/varmega-official-index.json';
    private const TEPLODVOR_INDEX_FILE = 'teplodvor_index.json';

    private const TEPLODVOR_CATEGORY_MAP = [
        'kotly/gazovye' => 53,
        'kotly/elektricheskie' => 55,
        'vodonagrevateli/elektricheskie' => 98,
        'vodonagrevateli/elekricheskie' => 98,
        'vodonagrevateli/protochnye' => 98,
        'vodonagrevateli/nakopitelnye' => 98,
        'vodonagrevateli/kosvennye' => 100,
        'vodonagrevateli/kombinirovannye' => 101,
        'vodonagrevateli/gazovye-kolonki' => 298,
        'vodonagrevateli/gazovye_kolonki' => 298,
        'vodonagrevateli/gazovye kolonki' => 298,
        'vodonagrevateli/gazovye' => 298,
        'vodonagrevateli/bufernye-emkosti' => 91,
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

    private const VARMEGA_CATEGORY_MAP = [
        'truby-i-fitingi/metalloplastikovye-pex-i-pert-truby' => 'truby-iz-sshitogo-polietilena',
        'truby-i-fitingi/aksialnye-fitingi' => 'press-fitingi',
        'truby-i-fitingi/rezbozazhimnye-fitingi-kontsovki' => 'kompressionnye-fitingi',
        'truby-i-fitingi/bronzovye-i-latunnye-fitingi-rezba' => 'rezbovye-fitingi',
        'truby-i-fitingi/truboprovodnye-sistemy-press-obzhim-i-payka/komplektuyushchie-i-aksessuary-press-obzhim-i-payka' => 'press-fitingi',
        'truby-i-fitingi/truboprovodnye-sistemy-press-obzhim-i-payka' => 'press-fitingi',
        'truby-i-fitingi/aksessuary-dlya-trub' => 'krepleniya-dlya-trub',
        'radiatornaya-armatura/radiatornye-klapany-ruchnoy-regulirovki' => 'radiatornaya-armatura',
        'radiatornaya-armatura/termostaticheskie-klapany' => 'radiatornaya-armatura',
        'radiatornaya-armatura/termogolovki' => 'radiatornaya-armatura',
        'radiatornaya-armatura/uzly-nizhnego-podklyucheniya' => 'radiatornaya-armatura',
        'radiatornaya-armatura/komplektuyushchie-dlya-radiatornoy-armatury' => 'radiatornaya-armatura',
        'radiatornaya-armatura' => 'radiatornaya-armatura',
        'predokhranitelnaya-i-reguliruyushchaya-armatura/obratnye-klapany' => 'predokhranitelnaya-i-reguliruyushchaya-armatura',
        'predokhranitelnaya-i-reguliruyushchaya-armatura/reduktory-davleniya' => 'predokhranitelnaya-i-reguliruyushchaya-armatura',
        'predokhranitelnaya-i-reguliruyushchaya-armatura/predokhranitelnye-klapany' => 'predokhranitelnaya-i-reguliruyushchaya-armatura',
        'predokhranitelnaya-i-reguliruyushchaya-armatura/gruppy-bezopasnosti-kotla' => 'gruppy-bystrogo-montazha-kotelnyx',
        'predokhranitelnaya-i-reguliruyushchaya-armatura/vozdukhootvodchiki' => 'predokhranitelnaya-i-reguliruyushchaya-armatura',
        'predokhranitelnaya-i-reguliruyushchaya-armatura/klapany-podpitki' => 'predokhranitelnaya-i-reguliruyushchaya-armatura',
        'predokhranitelnaya-i-reguliruyushchaya-armatura/kompensatory-gidroudara' => 'predokhranitelnaya-i-reguliruyushchaya-armatura',
        'predokhranitelnaya-i-reguliruyushchaya-armatura/predokhranitelnaya-armatura-dlya-boylerov' => 'predokhranitelnaya-i-reguliruyushchaya-armatura',
        'predokhranitelnaya-i-reguliruyushchaya-armatura/predokhranitelnaya-armatura-dlya-bakov' => 'predokhranitelnaya-i-reguliruyushchaya-armatura',
        'predokhranitelnaya-i-reguliruyushchaya-armatura' => 'predokhranitelnaya-i-reguliruyushchaya-armatura',
        'smesitelnaya-armatura/nasosno-smesitelnye-uzly' => 'montajnyie-komplektyi',
        'smesitelnaya-armatura' => 'smesitelnaya-armatura',
        'instrument' => 'instrumenty-dlya-montazha',
    ];

    private const VARMEGA_CATEGORY_DEFINITIONS = [
        'press-fitingi' => [
            'name' => 'Пресс-фитинги',
            'parent_slug' => 'truby-i-fitingi',
            'type' => 'catalog',
            'sort_order' => 130,
        ],
        'kompressionnye-fitingi' => [
            'name' => 'Компрессионные фитинги',
            'parent_slug' => 'truby-i-fitingi',
            'type' => 'catalog',
            'sort_order' => 140,
        ],
        'krepleniya-dlya-trub' => [
            'name' => 'Крепления для труб',
            'parent_slug' => 'truby-i-fitingi',
            'type' => 'catalog',
            'sort_order' => 160,
        ],
        'radiatornaya-armatura' => [
            'name' => 'Радиаторная арматура',
            'parent_slug' => 'komplektuyushhie-dlya-otopleniya',
            'type' => 'catalog',
            'sort_order' => 120,
        ],
        'predokhranitelnaya-i-reguliruyushchaya-armatura' => [
            'name' => 'Предохранительная и регулирующая арматура',
            'parent_slug' => 'komplektuyushhie-dlya-otopleniya',
            'type' => 'catalog',
            'sort_order' => 200,
        ],
        'smesitelnaya-armatura' => [
            'name' => 'Смесительная арматура',
            'parent_slug' => 'komplektuyushhie-dlya-otopleniya',
            'type' => 'catalog',
            'sort_order' => 210,
        ],
        'instrumenty-dlya-montazha' => [
            'name' => 'Инструменты для монтажа',
            'parent_slug' => 'komplektuyushhie-dlya-otopleniya',
            'type' => 'catalog',
            'sort_order' => 220,
        ],
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
    private ?array $varmegaOfficialIndex = null;
    private array $varmegaOfficialUrls = [];
    private ?array $teplodvorIndex = null;

    public function handle(): int
    {
        $decisionsPath = trim((string) $this->option('apply-ai-decisions'));
        if ($decisionsPath !== '') {
            return $this->applyAiDecisions($decisionsPath, (bool) $this->option('apply'));
        }

        $apply = (bool) $this->option('apply') && ! $this->option('dry-run');
        $limit = $this->option('limit') !== null ? max(0, (int) $this->option('limit')) : null;
        $offset = max(0, (int) $this->option('offset'));

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

        if ($offset > 0 || ($limit !== null && $limit > 0)) {
            $classified = array_slice($classified, $offset, $limit !== null && $limit > 0 ? $limit : null);
            $this->line(sprintf(
                'Row window: offset=%d%s.',
                $offset,
                $limit !== null && $limit > 0 ? " limit={$limit}" : ''
            ));
        }

        if ($this->option('teplodvor')) {
            $classified = $this->attachTeplodvorMatches($classified);
        }
        if ($this->option('rn-profi-cards')) {
            $classified = $this->attachRnProfiCardMatches($classified);
        }
        if ($this->option('varmega-official')) {
            $classified = $this->attachVarmegaOfficialMatches($classified);
        }
        if ($this->option('ai-match')) {
            $classified = $this->attachAiMatchDecisions($classified);
        }
        if (trim((string) $this->option('unmatched-report')) !== '') {
            $this->writeUnmatchedReport($classified);
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
        $csvGid = trim((string) $this->option('google-csv-gid'));
        if ($csvGid !== '') {
            return $this->downloadGoogleCsvGid($url, $csvGid);
        }

        $csvSheet = trim((string) $this->option('google-csv-sheet'));
        if ($csvSheet !== '') {
            return $this->downloadGoogleCsvSheet($url, $csvSheet);
        }

        $exportUrl = $this->toExportUrl($url);
        $path = storage_path('app/' . self::CACHE_PATH);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->line("Downloading RN-Profi Google Sheet: {$exportUrl}");
        $content = $this->downloadBinary($exportUrl, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,*/*');
        $this->assertXlsxContent($content, $path);
        file_put_contents($path, $content);

        return $path;
    }

    private function downloadBinary(string $url, string $accept): string
    {
        $lastError = '';

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $content = $this->downloadBinaryWithCurl($url, $accept, $lastError);
            if ($content === null) {
                $content = $this->downloadBinaryWithStream($url, $accept, $lastError);
            }

            if ($content !== null && strlen($content) >= 1024) {
                return $content;
            }

            $lastError = $lastError !== '' ? $lastError : 'empty or too small response';
            usleep(250000 * $attempt);
        }

        throw new \RuntimeException('Google Sheet download failed: ' . $lastError);
    }

    private function downloadBinaryWithCurl(string $url, string $accept, string &$lastError): ?string
    {
        if (! function_exists('curl_init')) {
            return null;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT => 240,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; KotlovBot/1.0)',
            CURLOPT_HTTPHEADER => ['Accept: ' . $accept],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $content = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if (! is_string($content) || $content === '' || $status >= 400) {
            $lastError = $error !== '' ? $error : 'HTTP ' . $status;
            return null;
        }

        return $content;
    }

    private function downloadBinaryWithStream(string $url, string $accept, string &$lastError): ?string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 240,
                'follow_location' => 1,
                'max_redirects' => 10,
                'header' => "User-Agent: Mozilla/5.0 (compatible; KotlovBot/1.0)\r\nAccept: {$accept}\r\n",
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);

        $content = @file_get_contents($url, false, $context);
        if (! is_string($content) || $content === '') {
            $lastError = error_get_last()['message'] ?? 'stream download failed';
            return null;
        }

        return $content;
    }

    private function downloadGoogleCsvGid(string $url, string $gid): string
    {
        if (! preg_match('#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $url, $m)) {
            throw new \RuntimeException('Cannot detect Google spreadsheet id for --google-csv-gid.');
        }

        if (! preg_match('/^\d+$/', $gid)) {
            throw new \RuntimeException('--google-csv-gid must be numeric.');
        }

        $exportUrl = sprintf(
            'https://docs.google.com/spreadsheets/d/%s/export?format=csv&gid=%s',
            $m[1],
            $gid
        );
        $path = storage_path('app/supplier-cache/rn-profi-gid-' . $gid . '.csv');

        return $this->downloadGoogleCsv($exportUrl, $path, 'gid ' . $gid);
    }

    private function downloadGoogleCsvSheet(string $url, string $sheetName): string
    {
        if (! preg_match('#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $url, $m)) {
            throw new \RuntimeException('Cannot detect Google spreadsheet id for --google-csv-sheet.');
        }

        $exportUrl = sprintf(
            'https://docs.google.com/spreadsheets/d/%s/gviz/tq?tqx=out:csv&sheet=%s',
            $m[1],
            rawurlencode($sheetName)
        );
        $path = storage_path('app/supplier-cache/rn-profi-' . Str::slug($sheetName) . '.csv');

        return $this->downloadGoogleCsv($exportUrl, $path, "sheet '{$sheetName}'");
    }

    private function downloadGoogleCsv(string $exportUrl, string $path, string $label): string
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->line("Downloading RN-Profi Google CSV {$label}: {$exportUrl}");
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 90,
                'follow_location' => 1,
                'max_redirects' => 10,
                'header' => "User-Agent: Mozilla/5.0 (compatible; KotlovBot/1.0)\r\nAccept: text/csv,*/*\r\n",
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);

        $content = @file_get_contents($exportUrl, false, $context);
        if ($content === false || strlen($content) < 32) {
            throw new \RuntimeException("Google CSV download failed for {$label}.");
        }

        $preview = trim(preg_replace('/\s+/u', ' ', substr($content, 0, 220)) ?? '');
        if (str_starts_with(ltrim($content), '<')) {
            $debugPath = preg_replace('/\.csv$/i', '.download-debug.html', $path) ?: ($path . '.download-debug.html');
            file_put_contents($debugPath, substr($content, 0, 4096));
            throw new \RuntimeException(
                "Google CSV returned HTML instead of CSV for {$label}. "
                . "Saved first bytes to {$debugPath}. Preview: " . mb_substr($preview, 0, 180)
            );
        }

        file_put_contents($path, $content);

        return $path;
    }

    private function assertXlsxContent(string $content, string $targetPath): void
    {
        if (str_starts_with($content, "PK\x03\x04")) {
            $tempPath = tempnam(dirname($targetPath), 'xlsx-check-');
            if ($tempPath !== false) {
                file_put_contents($tempPath, $content);
            }

            $zip = new \ZipArchive();
            $result = $tempPath !== false ? $zip->open($tempPath) : false;
            if ($result === true && $zip->locateName('[Content_Types].xml') !== false && $zip->locateName('xl/workbook.xml') !== false) {
                $zip->close();
                if ($tempPath !== false && is_file($tempPath)) {
                    @unlink($tempPath);
                }
                return;
            }
            if ($result === true) {
                $zip->close();
            }
            if ($tempPath !== false && is_file($tempPath)) {
                @unlink($tempPath);
            }
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

        if (! isset($columns['stock'])) {
            $knownColumns = array_map('intval', array_values($columns));
            $bestColumn = null;
            $bestScore = 0;
            $maxColumns = max(array_map('count', array_slice($rows, $headerIndex, 30)) ?: [0]);

            for ($candidate = 0; $candidate < $maxColumns; $candidate++) {
                if (in_array($candidate, $knownColumns, true)) {
                    continue;
                }

                $score = 0;
                foreach (array_slice($rows, $headerIndex + 1, 80) as $row) {
                    $text = $this->clean((string) ($row[$candidate] ?? ''));
                    if ($this->looksLikeStockText($text)) {
                        $score += 3;
                    } elseif ($text !== '' && preg_match('/^(0|1|yes|no|\+|\-)$/iu', $text)) {
                        $score++;
                    }
                }

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestColumn = $candidate;
                }
            }

            if ($bestColumn !== null && $bestScore >= 6) {
                $columns['stock'] = (int) $bestColumn;
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
        $resolvedBrand = $brandId ? ($this->brandById[$brandId] ?? '') : '';

        if (($row['norm_article'] ?? '') === '') {
            $syntheticArticle = $this->syntheticSupplierArticle($row, $resolvedBrand);
            if ($syntheticArticle !== '') {
                $row['article'] = $syntheticArticle;
                $row['norm_article'] = $this->normArticle($syntheticArticle);
            }
        }

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
            'resolved_brand' => $resolvedBrand !== '' ? $resolvedBrand : null,
            'matched_product_id' => $match['product_id'] ?? null,
            'matched_sku' => $match['sku'] ?? null,
            'confidence' => $match['confidence'] ?? null,
            'stock' => $stock,
            'action' => $action,
        ];
    }

    private function syntheticSupplierArticle(array $row, string $resolvedBrand): string
    {
        if ($this->brandKey($resolvedBrand ?: (string) ($row['brand'] ?? '')) !== $this->brandKey('Thermex')) {
            return '';
        }

        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            return '';
        }

        $model = preg_replace('/\bthermex\b/iu', ' ', $name) ?? $name;
        $model = preg_replace('/\s+/u', ' ', trim($model)) ?? trim($model);
        $token = $this->normArticle($model);

        return $token !== '' ? 'THERMEX-' . $token : '';
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

    private function fetchHttpPage(string $url, int $timeout = 25): ?string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => max(3, $timeout),
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

        preg_match_all('/\b[A-Z0-9][A-Z0-9\-\/\.]{3,}\b/iu', $plain, $matches);
        foreach ($matches[0] ?? [] as $token) {
            $norm = $this->normArticle($token);
            if (mb_strlen($norm) >= 4 && preg_match('/[A-Z]/i', $norm) && preg_match('/\d/', $norm)) {
                $tokens[$norm] = true;
            }
        }

        preg_match_all('/\b\d{5,8}\b/u', $plain, $matches);
        foreach ($matches[0] ?? [] as $token) {
            $tokens[$this->normArticle($token)] = true;
        }

        return array_values(array_keys($tokens));
    }

    private function attachVarmegaOfficialMatches(array $rows): array
    {
        $index = $this->loadVarmegaOfficialIndex();
        if ($this->option('varmega-deep-index')) {
            $index = $this->deepenVarmegaOfficialIndex($index, $rows);
        }
        if ($index === []) {
            $this->warn('Official Varmega index is empty. Check sitemap URL or run with --varmega-refresh-index.');
        } else {
            $this->line(sprintf('Official Varmega index: %d article URLs.', count($index)));
        }

        return array_map(function (array $row) use ($index): array {
            $article = $this->normArticle((string) ($row['article'] ?? ''));
            $brand = $this->brandKey((string) ($row['resolved_brand'] ?: ($row['brand'] ?? '')));
            $match = $article !== '' && $brand === $this->brandKey('Varmega') && isset($index[$article])
                ? $index[$article]
                : null;

            return $row + [
                'varmega_url' => $match['url'] ?? null,
                'varmega_score' => $match ? 1.0 : null,
                'varmega_confidence' => $match ? 'article_sitemap' : null,
                'varmega_category_path' => $match['category_path'] ?? null,
            ];
        }, $rows);
    }

    private function loadVarmegaOfficialIndex(): array
    {
        if ($this->varmegaOfficialIndex !== null && ! $this->option('varmega-refresh-index')) {
            return $this->varmegaOfficialIndex;
        }

        $path = storage_path('app/' . self::VARMEGA_OFFICIAL_INDEX_CACHE);
        if (! $this->option('varmega-refresh-index') && is_file($path)) {
            $data = json_decode((string) file_get_contents($path), true);
            $items = is_array($data) ? ($data['items'] ?? $data) : [];
            $this->varmegaOfficialUrls = is_array($data) && is_array($data['urls'] ?? null) ? $data['urls'] : [];
            if (is_array($items)) {
                return $this->varmegaOfficialIndex = array_filter($items, fn ($item): bool => is_array($item) && ! empty($item['url']));
            }
        }

        $sitemapUrl = trim((string) $this->option('varmega-sitemap'));
        $xml = $this->fetchHttpPage($sitemapUrl);
        if ($xml === null) {
            return $this->varmegaOfficialIndex = [];
        }

        preg_match_all('#<loc>\s*([^<]+)\s*</loc>#i', $xml, $matches);
        $index = [];
        $urls = [];
        foreach ($matches[1] ?? [] as $rawUrl) {
            $url = html_entity_decode(trim((string) $rawUrl), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (! str_starts_with($url, 'https://varmega.ru/product/')) {
                continue;
            }
            if ($this->looksLikeVarmegaProductUrl($url)) {
                $urls[] = $url;
            }

            $tokens = $this->extractSupplierArticleTokens($url);
            foreach ($tokens as $token) {
                if (! str_starts_with($token, 'VM') || mb_strlen($token) < 5) {
                    continue;
                }

                $candidate = [
                    'url' => $url,
                    'category_path' => $this->varmegaCategoryPath($url),
                ];

                if (! isset($index[$token]) || strlen((string) $candidate['url']) > strlen((string) ($index[$token]['url'] ?? ''))) {
                    $index[$token] = $candidate;
                }
            }
        }

        ksort($index);
        $this->varmegaOfficialUrls = array_values(array_unique($urls));
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, json_encode([
            'generated_at' => now()->toDateTimeString(),
            'source' => $sitemapUrl,
            'urls' => $this->varmegaOfficialUrls,
            'items' => $index,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        return $this->varmegaOfficialIndex = $index;
    }

    private function deepenVarmegaOfficialIndex(array $index, array $rows): array
    {
        $targetArticles = [];
        foreach ($rows as $row) {
            $brand = $this->brandKey((string) ($row['resolved_brand'] ?: ($row['brand'] ?? '')));
            $article = $this->normArticle((string) ($row['article'] ?? ''));
            if ($brand === $this->brandKey('Varmega') && $article !== '' && ! isset($index[$article])) {
                $targetArticles[$article] = true;
            }
        }

        if ($targetArticles === [] || $this->varmegaOfficialUrls === []) {
            return $index;
        }

        $maxPages = max(0, (int) $this->option('varmega-deep-pages'));
        $pages = 0;
        $matches = 0;

        foreach ($this->varmegaOfficialUrls as $url) {
            if ($maxPages > 0 && $pages >= $maxPages) {
                break;
            }
            if ($targetArticles === []) {
                break;
            }

            $html = $this->fetchHttpPage((string) $url, 8);
            if ($html === null) {
                continue;
            }

            $pages++;
            $tokens = array_flip($this->extractSupplierArticleTokens($url . ' ' . $html));
            foreach (array_keys($targetArticles) as $article) {
                if (! isset($tokens[$article])) {
                    continue;
                }

                $index[$article] = [
                    'url' => (string) $url,
                    'category_path' => $this->varmegaCategoryPath((string) $url),
                ];
                unset($targetArticles[$article]);
                $matches++;
            }

            if ($pages % 50 === 0) {
                $this->line(sprintf('Official Varmega deep index progress: fetched=%d, new_matches=%d, still_missing=%d.', $pages, $matches, count($targetArticles)));
                $this->saveVarmegaOfficialIndex($index);
            }
        }

        if ($pages > 0) {
            $this->line(sprintf('Official Varmega deep index: fetched=%d, new_matches=%d, still_missing=%d.', $pages, $matches, count($targetArticles)));
            $this->saveVarmegaOfficialIndex($index);
        }

        return $this->varmegaOfficialIndex = $index;
    }

    private function saveVarmegaOfficialIndex(array $index): void
    {
        $path = storage_path('app/' . self::VARMEGA_OFFICIAL_INDEX_CACHE);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        ksort($index);
        file_put_contents($path, json_encode([
            'generated_at' => now()->toDateTimeString(),
            'source' => trim((string) $this->option('varmega-sitemap')),
            'urls' => $this->varmegaOfficialUrls,
            'items' => $index,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    private function varmegaCategoryPath(string $url): string
    {
        $path = trim((string) (parse_url($url, PHP_URL_PATH) ?: ''), '/');
        $parts = array_values(array_filter(explode('/', $path)));
        if (($parts[0] ?? '') === 'product') {
            array_shift($parts);
        }
        if (count($parts) > 1) {
            array_pop($parts);
        }

        return implode('/', $parts);
    }

    private function looksLikeVarmegaProductUrl(string $url): bool
    {
        $path = trim((string) (parse_url($url, PHP_URL_PATH) ?: ''), '/');
        $parts = array_values(array_filter(explode('/', $path)));
        if (($parts[0] ?? '') === 'product') {
            array_shift($parts);
        }

        return count($parts) >= 3;
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

            $trustedMatch = in_array((string) ($match['confidence'] ?? ''), ['article_slug', 'model_slug'], true);
            if ($match !== null && ! $trustedMatch && ! $this->looksLikeSpecificTeplodvorProductUrl((string) ($match['url'] ?? ''), $row)) {
                $match = null;
            }

            if ($match === null && $this->option('teplodvor-probe-missing')) {
                $match = $this->probeTeplodvorProductUrl($row);
            }

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
        $debug = (bool) $this->option('teplodvor-debug');
        $debugPages = [];
        $debugUrls = [];
        $debugTokens = [];
        $brandScope = $this->teplodvorBrandScope($brandPage, $rows);

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
            if ($debug && count($debugPages) < 12) {
                $debugPages[] = $url;
            }

            $pageText = $html . ' ' . $url;
            $pageTokens = $this->extractSupplierArticleTokens($pageText);
            if ($debug && count($debugTokens) < 40) {
                foreach ($pageTokens as $token) {
                    $debugTokens[$token] = true;
                    if (count($debugTokens) >= 40) {
                        break;
                    }
                }
            }
            $tokens = array_flip($pageTokens);
            $normalisedPage = $this->normArticle(html_entity_decode(strip_tags($pageText) . ' ' . $pageText, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if (! $this->looksLikeTeplodvorListingUrl($url, $brandPage)) {
                $urlTokens = array_flip($this->extractSupplierArticleTokens($url));
                foreach (array_keys($targetArticles) as $article) {
                    if ($urlTokens !== [] && ! isset($urlTokens[$article])) {
                        continue;
                    }
                    if ($urlTokens === [] && ! isset($tokens[$article]) && ! str_contains($normalisedPage, $article)) {
                        continue;
                    }
                    $matches[$article] = [
                        'url' => $url,
                        'score' => 1.0,
                        'confidence' => 'brand_page_article',
                    ];
                }
            }

            $nextUrls = array_values(array_filter(
                $this->extractTeplodvorInternalUrls($html),
                fn (string $nextUrl): bool => $this->teplodvorUrlInBrandScope($nextUrl, $brandPage, $brandScope)
            ));
            if ($debug && count($debugUrls) < 24) {
                foreach ($nextUrls as $nextUrl) {
                    $debugUrls[] = $nextUrl;
                    if (count($debugUrls) >= 24) {
                        break;
                    }
                }
            }
            foreach ($nextUrls as $nextUrl) {
                if (! isset($visited[$nextUrl]) && count($queue) < ($maxPages * 5)) {
                    $queue[] = $nextUrl;
                }
            }
        }

        if ($pages > 0) {
            $this->line(sprintf('Teplodvor brand crawl: %d pages from %s.', $pages, $brandPage));
        }
        if ($debug) {
            $this->line('Teplodvor debug target articles: ' . implode(', ', array_slice(array_keys($targetArticles), 0, 20)));
            $this->line('Teplodvor debug pages: ' . implode(' | ', $debugPages));
            $this->line('Teplodvor debug urls: ' . implode(' | ', array_slice(array_values(array_unique($debugUrls)), 0, 24)));
            $this->line('Teplodvor debug article tokens: ' . implode(', ', array_slice(array_keys($debugTokens), 0, 40)));
        }

        return $matches;
    }

    private function looksLikeTeplodvorListingUrl(string $url, string $brandPage): bool
    {
        $urlPath = trim((string) (parse_url($url, PHP_URL_PATH) ?: ''), '/');
        $brandPath = trim((string) (parse_url($brandPage, PHP_URL_PATH) ?: ''), '/');

        if (rtrim($urlPath, '/') === rtrim($brandPath, '/')) {
            return true;
        }

        return $brandPath !== ''
            && preg_match('#^' . preg_quote(rtrim($brandPath, '/'), '#') . '/page\d+/?$#i', $urlPath) === 1;
    }

    private function teplodvorBrandScope(string $brandPage, array $rows): string
    {
        $path = trim((string) (parse_url($brandPage, PHP_URL_PATH) ?: ''), '/');
        $parts = array_values(array_filter(explode('/', $path)));
        $last = end($parts);
        if (is_string($last) && $last !== '' && $last !== 'shop') {
            return Str::slug($last);
        }

        foreach ($rows as $row) {
            $brand = (string) ($row['resolved_brand'] ?: $row['brand'] ?: '');
            if ($brand !== '') {
                return Str::slug($brand);
            }
        }

        return '';
    }

    private function teplodvorUrlInBrandScope(string $url, string $brandPage, string $brandScope): bool
    {
        $url = strtok($url, '#') ?: $url;
        $brandPage = strtok($brandPage, '#') ?: $brandPage;
        if (rtrim($url, '/') === rtrim($brandPage, '/')) {
            return true;
        }

        return $brandScope !== '' && str_contains(Str::slug($url), $brandScope);
    }

    private function extractTeplodvorInternalUrls(string $html): array
    {
        preg_match_all('/(?:href|data-href|data-url)=["\']([^"\']+)["\']/i', $html, $matches);
        $urls = [];
        foreach ($matches[1] ?? [] as $href) {
            $url = html_entity_decode((string) $href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, 'javascript:')) {
                continue;
            }
            if (str_starts_with($url, '//')) {
                $url = 'https:' . $url;
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

        $modelMatch = $this->findTeplodvorModelMatch($row, $index);
        if ($modelMatch !== null) {
            return $modelMatch;
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

    private function findTeplodvorModelMatch(array $row, array $index): ?array
    {
        $brand = (string) ($row['resolved_brand'] ?: $row['brand'] ?: '');
        $brandSlug = Str::slug($brand);
        $model = $this->teplodvorModelKey($row, $brand);

        if ($brandSlug === '' || strlen($model) < 5) {
            return null;
        }

        $brandKey = $this->compactSlug($brandSlug);
        foreach ($index as $slug => $url) {
            $compactSlug = $this->compactSlug((string) $slug);
            $compactUrl = $this->compactSlug((string) $url);
            if (! str_contains($compactSlug . $compactUrl, $brandKey)) {
                continue;
            }
            if (! str_contains($compactSlug . $compactUrl, $model)) {
                continue;
            }
            return ['url' => $url, 'score' => 1.0, 'confidence' => 'model_slug'];
        }

        return null;
    }

    private function probeTeplodvorProductUrl(array $row): ?array
    {
        $brand = (string) ($row['resolved_brand'] ?: $row['brand'] ?: '');
        if ($this->brandKey($brand) !== $this->brandKey('Thermex')) {
            return null;
        }

        $modelSlug = $this->teplodvorModelSlug($row, $brand);
        if ($modelSlug === '') {
            return null;
        }

        foreach ($this->teplodvorThermexUrlCandidates($modelSlug, $row) as $url) {
            $html = $this->fetchHttpPage($url, 4);
            if ($html === null || ! $this->teplodvorPageLooksLikeProduct($html, $row, $brand)) {
                continue;
            }

            return ['url' => $url, 'score' => 1.0, 'confidence' => 'probed_model_url'];
        }

        return null;
    }

    private function teplodvorThermexUrlCandidates(string $modelSlug, array $row): array
    {
        $base = 'thermex-' . $modelSlug;
        $nameKey = $this->compactSlug(Str::slug((string) ($row['name'] ?? '')));
        $isElectric = str_contains($nameKey, 'quantum') || str_contains($nameKey, 'e9') || str_contains($nameKey, 'elektro');
        $isWaterHeater = str_contains($nameKey, 'aqua') || str_contains($nameKey, 'boiler') || str_contains($nameKey, 'boiler') || str_contains($nameKey, 'vodonagrev');

        $gasPaths = [
            'kotly/gazovye/gazovyy-kotyol-' . $base,
            'kotly/gazovye/gazovyy-kotel-' . $base,
            'kotly/gazovye/kotel-gazovyy-' . $base,
            'kotly/gazovye/' . $base,
        ];
        $electricPaths = [
            'kotly/elektricheskie/elektrokotel-' . $base,
            'kotly/elektricheskie/elektricheskiy-kotel-' . $base,
            'kotly/elektricheskie/kotel-elektricheskiy-' . $base,
            'kotly/elektricheskie/' . $base,
        ];
        $waterHeaterPaths = [
            'vodonagrevateli/bojlery/' . $base,
            'vodonagrevateli/protochnye/' . $base,
        ];

        $paths = match (true) {
            $isElectric => $electricPaths,
            $isWaterHeater => $waterHeaterPaths,
            default => $gasPaths,
        };

        return array_map(fn (string $path): string => 'https://www.teplodvor.by/shop/' . $path, array_values(array_unique($paths)));
    }

    private function teplodvorPageLooksLikeProduct(string $html, array $row, string $brand): bool
    {
        $text = $this->compactSlug(Str::slug(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $model = $this->teplodvorModelKey($row, $brand);

        return $model !== ''
            && str_contains($text, $this->compactSlug(Str::slug($brand)))
            && str_contains($text, $model)
            && ! str_contains($text, 'stranitsanenaydena')
            && ! str_contains($text, '404');
    }

    private function teplodvorModelSlug(array $row, string $brand): string
    {
        $name = $this->clean((string) ($row['name'] ?? ''));
        $name = preg_replace('/\b' . preg_quote($brand, '/') . '\b/iu', ' ', $name) ?? $name;
        $name = preg_replace('/\b(model|модель|ĐĽĐľĐ´ĐµĐ»ŃŚ)\b/iu', ' ', $name) ?? $name;
        $slug = $this->normaliseTeplodvorSlug(Str::slug($name));

        return trim($slug, '-');
    }

    private function teplodvorModelKey(array $row, string $brand): string
    {
        $name = $this->clean((string) ($row['name'] ?? ''));
        $name = preg_replace('/\b' . preg_quote($brand, '/') . '\b/iu', ' ', $name) ?? $name;
        $name = preg_replace('/\b(model|модель)\b/iu', ' ', $name) ?? $name;

        return $this->compactSlug(Str::slug($name));
    }

    private function looksLikeSpecificTeplodvorProductUrl(string $url, array $row): bool
    {
        $path = trim((string) (parse_url($url, PHP_URL_PATH) ?: ''), '/');
        $parts = array_values(array_filter(explode('/', $path)));
        if (($parts[0] ?? '') === 'shop') {
            array_shift($parts);
        }

        $leaf = (string) end($parts);
        if ($leaf === '') {
            return false;
        }

        $brand = (string) ($row['resolved_brand'] ?: $row['brand'] ?: '');
        if ($brand !== '' && $leaf === Str::slug($brand)) {
            return false;
        }

        $nameSlug = $this->normaliseTeplodvorSlug(Str::slug((string) ($row['name'] ?? '')));
        $brandTokens = $this->teplodvorTokens(Str::slug($brand), []);
        $nameTokens = $this->teplodvorTokens($nameSlug, $brandTokens);
        $leafTokens = $this->teplodvorTokens($leaf, []);
        $overlap = array_values(array_intersect($nameTokens, $leafTokens));

        return count($overlap) >= 2;
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

    private function attachAiMatchDecisions(array $rows): array
    {
        $limit = max(0, (int) $this->option('ai-match-limit'));
        $minConfidence = max(1, min(100, (int) $this->option('ai-min-confidence')));
        $processableIndexes = array_keys($rows);
        $decisions = [];

        if ($this->option('varmega-auto-create')) {
            foreach ($rows as $rowIndex => $row) {
                if (empty($row['varmega_url']) || ($row['action'] ?? '') === 'matched') {
                    continue;
                }

                $rows[$rowIndex]['ai_decision'] = 'create_new';
                $rows[$rowIndex]['ai_confidence'] = 95;
                $rows[$rowIndex]['ai_reason'] = 'Официальная карточка Varmega найдена по артикулу поставщика.';
                $rows[$rowIndex]['ai_target_product_id'] = null;
                $rows[$rowIndex]['ai_recommended_action'] = 'can_apply_after_review';
                $decisions[] = $this->aiDecisionReportRow($rows[$rowIndex], []);
            }

            $processableIndexes = array_values(array_filter(
                $processableIndexes,
                fn (int $rowIndex): bool => empty($rows[$rowIndex]['ai_decision'])
            ));

            if ($this->option('varmega-auto-only')) {
                $processableIndexes = [];
            }
        }

        if ($this->option('teplodvor-auto-create')) {
            $minTeplodvorScore = max(0.1, min(1.0, (float) $this->option('teplodvor-auto-min-score')));

            foreach ($rows as $rowIndex => $row) {
                if (! empty($row['ai_decision']) || empty($row['teplodvor_url']) || ($row['action'] ?? '') === 'matched') {
                    continue;
                }

                $score = (float) ($row['teplodvor_score'] ?? 0);
                if ($score < $minTeplodvorScore) {
                    continue;
                }

                $rows[$rowIndex]['ai_decision'] = 'create_new';
                $rows[$rowIndex]['ai_confidence'] = 92;
                $rows[$rowIndex]['ai_reason'] = 'Teplodvor card matched by product name with high score.';
                $rows[$rowIndex]['ai_target_product_id'] = null;
                $rows[$rowIndex]['ai_recommended_action'] = 'can_apply_after_review';
                $decisions[] = $this->aiDecisionReportRow($rows[$rowIndex], []);
            }

            $processableIndexes = array_values(array_filter(
                $processableIndexes,
                fn (int $rowIndex): bool => empty($rows[$rowIndex]['ai_decision'])
            ));

            if ($this->option('teplodvor-auto-only')) {
                $processableIndexes = [];
            }
        }

        if ($limit > 0) {
            $processableIndexes = array_slice($processableIndexes, 0, $limit);
        }

        if ($processableIndexes === []) {
            $this->info(sprintf('AI match provider was not needed. Auto decisions: %d.', count($decisions)));
            $this->writeAiDecisionReports($decisions);

            return $rows;
        }

        $ai = $this->aiMatchProvider();
        if (! $ai->isAvailable()) {
            $this->error('No AI provider configured. Set ANTHROPIC_API_KEY or AI_API_KEY + AI_API_URL + AI_MODEL.');
            if ($decisions !== []) {
                $this->writeAiDecisionReports($decisions);
            }

            return $rows;
        }

        $this->info(sprintf(
            'AI match provider: %s. Sending %d of %d current rows%s.',
            $ai->providerName(),
            count($processableIndexes),
            count($rows),
            $decisions !== [] ? sprintf(' (%d auto decisions already prepared)', count($decisions)) : ''
        ));

        $batchSize = max(1, min(25, (int) $this->option('ai-batch-size')));
        $candidateMap = [];

        if ($batchSize > 1) {
            foreach (array_chunk($processableIndexes, $batchSize) as $chunkOffset => $chunk) {
                $this->line(sprintf(
                    '[AI batch %d/%d] rows %d-%d',
                    $chunkOffset + 1,
                    (int) ceil(count($processableIndexes) / $batchSize),
                    $chunkOffset * $batchSize + 1,
                    min(count($processableIndexes), ($chunkOffset + 1) * $batchSize)
                ));

                $items = [];
                foreach ($chunk as $rowIndex) {
                    $candidates = $this->aiProductCandidates($rows[$rowIndex], 6);
                    $candidateMap[$rowIndex] = $candidates;
                    $items[] = [
                        'row_index' => $rowIndex,
                        'data' => $this->aiDecisionPayload($rows[$rowIndex], $candidates),
                    ];
                }

                $batchDecisions = $this->aiDecisionsForBatch($ai, $items);
                foreach ($chunk as $rowIndex) {
                    $decision = $this->normalizeAiDecision($batchDecisions[$rowIndex] ?? [], $candidateMap[$rowIndex] ?? []);
                    $confidence = max(0, min(100, (int) ($decision['confidence'] ?? 0)));
                    $decisionName = (string) ($decision['decision'] ?? 'manual_review');
                    $recommendedAction = in_array($decisionName, ['link_existing', 'create_new'], true) && $confidence >= $minConfidence
                        ? 'can_apply_after_review'
                        : 'keep_manual_review';

                    $rows[$rowIndex]['ai_decision'] = $decisionName;
                    $rows[$rowIndex]['ai_confidence'] = $confidence;
                    $rows[$rowIndex]['ai_reason'] = trim((string) ($decision['reason'] ?? ''));
                    $rows[$rowIndex]['ai_target_product_id'] = $decision['target_product_id'] ?? null;
                    $rows[$rowIndex]['ai_recommended_action'] = $recommendedAction;

                    $decisions[] = $this->aiDecisionReportRow($rows[$rowIndex], $candidateMap[$rowIndex] ?? []);
                }
            }

            $this->writeAiDecisionReports($decisions);

            return $rows;
        }

        foreach ($processableIndexes as $runIndex => $rowIndex) {
            $row = $rows[$rowIndex];
            $this->line(sprintf(
                '[AI %d/%d] %s %s',
                $runIndex + 1,
                count($processableIndexes),
                (string) ($row['article'] ?: '-'),
                mb_substr((string) $row['name'], 0, 70)
            ));

            $candidates = $this->aiProductCandidates($row, 6);
            $decision = $this->aiDecisionForRow($ai, $row, $candidates);
            $confidence = max(0, min(100, (int) ($decision['confidence'] ?? 0)));
            $decisionName = (string) ($decision['decision'] ?? 'manual_review');
            $recommendedAction = in_array($decisionName, ['link_existing', 'create_new'], true) && $confidence >= $minConfidence
                ? 'can_apply_after_review'
                : 'keep_manual_review';

            $rows[$rowIndex]['ai_decision'] = $decisionName;
            $rows[$rowIndex]['ai_confidence'] = $confidence;
            $rows[$rowIndex]['ai_reason'] = trim((string) ($decision['reason'] ?? ''));
            $rows[$rowIndex]['ai_target_product_id'] = $decision['target_product_id'] ?? null;
            $rows[$rowIndex]['ai_recommended_action'] = $recommendedAction;

            $decisions[] = $this->aiDecisionReportRow($rows[$rowIndex], $candidates);
        }

        $this->writeAiDecisionReports($decisions);

        return $rows;
    }

    private function aiMatchModel(): ?string
    {
        $option = trim((string) $this->option('ai-model'));
        if ($option !== '') {
            return $option;
        }

        $config = trim((string) config('services.ai.match_model', ''));
        return $config !== '' ? $config : null;
    }

    private function aiMatchProvider(): AiContentEnricher
    {
        $provider = strtolower(trim((string) $this->option('ai-provider')));
        $model = $this->aiMatchModel();
        $ai = new AiContentEnricher();

        return match ($provider) {
            'openai' => $ai->withOpenAi($model),
            default => $ai->withModel($model),
        };
    }

    private function aiDecisionForRow(AiContentEnricher $ai, array $row, array $candidates): array
    {
        $payload = $this->aiDecisionPayload($row, $candidates);

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $prompt = <<<PROMPT
You are an ecommerce catalog matching assistant for kotlov.by.

Task: decide what to do with one RN-Profi supplier price-list row.
Return ONLY valid JSON with keys:
decision: one of "link_existing", "create_new", "manual_review", "skip"
confidence: integer 0-100
target_product_id: integer product id when decision is "link_existing", otherwise null
reason: short Russian explanation for a manager

Rules:
- Supplier article is the strongest signal when it clearly appears in an existing product, official Varmega card, Teplodvor card or RN-Profi card.
- Brand, model, size, diameter, thread, color, left/right, straight/angle, section count and suffixes are important.
- Do not link products that differ by size/modification.
- If an exact existing product is not present but a reliable official Varmega/Teplodvor/RN-Profi card is found, prefer "create_new".
- If price-list name is too short and there is no reliable card, use "manual_review".
- Use "skip" only for rows that are not real products or have insufficient supplier data.
- Never invent IDs. target_product_id must be one of existing_catalog_candidates ids or null.

Data:
{$json}
PROMPT;

        $response = $ai->complete($prompt, 900);
        if (! $response) {
            return [
                'decision' => 'manual_review',
                'confidence' => 0,
                'target_product_id' => null,
                'reason' => 'AI did not return a response',
            ];
        }

        $data = json_decode($this->extractJson($response), true);
        if (! is_array($data)) {
            return [
                'decision' => 'manual_review',
                'confidence' => 0,
                'target_product_id' => null,
                'reason' => 'AI returned invalid JSON: ' . mb_substr($response, 0, 180),
            ];
        }

        return $this->normalizeAiDecision($data, $candidates);
    }

    private function aiDecisionPayload(array $row, array $candidates): array
    {
        return [
            'price_row' => [
                'sheet' => $row['sheet'] ?? '',
                'row_number' => $row['row_number'] ?? null,
                'article' => $row['article'] ?? '',
                'brand' => $row['resolved_brand'] ?: ($row['brand'] ?? ''),
                'name' => $row['name'] ?? '',
                'category_text' => $row['category_text'] ?? '',
                'wholesale_price_byn' => $row['price'] ?? null,
                'retail_price_byn' => $row['retail_price'] ?? null,
                'stock_status' => $row['stock']['status'] ?? null,
                'stock_text' => $row['stock_text'] ?? '',
            ],
            'found_cards' => [
                'varmega_url' => $row['varmega_url'] ?? null,
                'varmega_score' => $row['varmega_score'] ?? null,
                'varmega_category_path' => $row['varmega_category_path'] ?? null,
                'teplodvor_url' => $row['teplodvor_url'] ?? null,
                'teplodvor_score' => $row['teplodvor_score'] ?? null,
                'teplodvor_category_id' => $row['teplodvor_category_id'] ?? null,
                'rn_profi_url' => $row['rn_profi_url'] ?? null,
                'rn_profi_title' => $row['rn_profi_title'] ?? null,
            ],
            'current_math_match' => [
                'action' => $row['action'] ?? '',
                'product_id' => $row['matched_product_id'] ?? null,
                'sku' => $row['matched_sku'] ?? null,
                'confidence' => $row['confidence'] ?? null,
            ],
            'existing_catalog_candidates' => $candidates,
        ];
    }

    private function aiDecisionsForBatch(AiContentEnricher $ai, array $items): array
    {
        $json = json_encode(['items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $prompt = <<<PROMPT
You are an ecommerce catalog matching assistant for kotlov.by.

Task: decide what to do with multiple RN-Profi supplier price-list rows.
Return ONLY valid JSON with key "decisions": an array of objects with keys:
row_index: copy input row_index
decision: one of "link_existing", "create_new", "manual_review", "skip"
confidence: integer 0-100
target_product_id: integer product id when decision is "link_existing", otherwise null
reason: short Russian explanation for a manager

Rules:
- Supplier article is the strongest signal when it clearly appears in an existing product, official Varmega card, Teplodvor card or RN-Profi card.
- Brand, model, size, diameter, thread, color, left/right, straight/angle, section count and suffixes are important.
- Do not link products that differ by size/modification.
- If an exact existing product is not present but a reliable official Varmega/Teplodvor/RN-Profi card is found, prefer "create_new".
- If price-list name is too short and there is no reliable card, use "manual_review".
- Use "skip" only for rows that are not real products or have insufficient supplier data.
- Never invent IDs. target_product_id must be one of that row's existing_catalog_candidates ids or null.
- Return one decision for every input item.

Data:
{$json}
PROMPT;

        $response = $ai->complete($prompt, max(1800, count($items) * 260));
        if (! $response) {
            return [];
        }

        $data = json_decode($this->extractJson($response), true);
        if (! is_array($data)) {
            return [];
        }

        $rows = is_array($data['decisions'] ?? null) ? $data['decisions'] : $data;
        $byIndex = [];
        foreach ($rows as $decision) {
            if (! is_array($decision) || ! isset($decision['row_index'])) {
                continue;
            }
            $byIndex[(int) $decision['row_index']] = $decision;
        }

        return $byIndex;
    }

    private function normalizeAiDecision(array $data, array $candidates): array
    {
        $allowed = ['link_existing', 'create_new', 'manual_review', 'skip'];
        $decision = in_array(($data['decision'] ?? ''), $allowed, true)
            ? (string) $data['decision']
            : 'manual_review';
        $targetProductId = isset($data['target_product_id']) && (int) $data['target_product_id'] > 0
            ? (int) $data['target_product_id']
            : null;
        $candidateIds = array_map(fn (array $candidate): int => (int) $candidate['id'], $candidates);

        if ($decision === 'link_existing' && ($targetProductId === null || ! in_array($targetProductId, $candidateIds, true))) {
            $decision = 'manual_review';
            $targetProductId = null;
        }

        return [
            'decision' => $decision,
            'confidence' => max(0, min(100, (int) ($data['confidence'] ?? 0))),
            'target_product_id' => $targetProductId,
            'reason' => trim((string) ($data['reason'] ?? '')),
        ];
    }

    private function aiProductCandidates(array $row, int $limit): array
    {
        $brandId = (int) ($row['resolved_brand_id'] ?? 0);
        $query = trim(implode(' ', array_filter([
            $row['resolved_brand'] ?? '',
            $row['article'] ?? '',
            $row['name'] ?? '',
            $row['category_text'] ?? '',
            $row['rn_profi_title'] ?? '',
            basename((string) ($row['varmega_url'] ?? '')),
            basename((string) ($row['teplodvor_url'] ?? '')),
        ])));
        $tokens = $this->aiMatchTokens($query);

        $dbRows = DB::table('products as p')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->where('p.is_archived', false)
            ->when($brandId > 0, fn ($q) => $q->where('p.brand_id', $brandId))
            ->select([
                'p.id',
                'p.sku',
                'p.name',
                'p.price',
                'p.category_id',
                'b.name as brand',
                'c.name as category',
            ])
            ->orderByDesc('p.id')
            ->limit(600)
            ->get();

        $scored = [];
        foreach ($dbRows as $product) {
            $productText = trim(($product->sku ?? '') . ' ' . ($product->name ?? '') . ' ' . ($product->brand ?? '') . ' ' . ($product->category ?? ''));
            $score = $this->aiCandidateScore($tokens, $productText);
            if ($score <= 0 && ! str_contains($this->normArticle($productText), $this->normArticle((string) ($row['article'] ?? '')))) {
                continue;
            }

            $scored[] = [
                'id' => (int) $product->id,
                'sku' => (string) $product->sku,
                'name' => (string) $product->name,
                'brand' => (string) ($product->brand ?? ''),
                'category' => (string) ($product->category ?? ''),
                'category_id' => $product->category_id !== null ? (int) $product->category_id : null,
                'retail_price_byn' => $product->price !== null ? (float) $product->price : null,
                'candidate_score' => $score,
            ];
        }

        usort($scored, fn (array $left, array $right): int => $right['candidate_score'] <=> $left['candidate_score']);

        return array_slice($scored, 0, $limit);
    }

    private function aiMatchTokens(string $text): array
    {
        $slug = Str::slug($text);
        $parts = preg_split('/[^a-z0-9]+/i', $slug) ?: [];
        return array_values(array_unique(array_filter(
            $parts,
            fn (string $token): bool => strlen($token) >= 2 && ! in_array($token, self::TEPLODVOR_STOPWORDS, true)
        )));
    }

    private function aiCandidateScore(array $queryTokens, string $productText): int
    {
        if ($queryTokens === []) {
            return 0;
        }

        $productTokens = array_flip($this->aiMatchTokens($productText));
        $score = 0;
        foreach ($queryTokens as $token) {
            if (isset($productTokens[$token])) {
                $score += ctype_digit($token) ? 6 : strlen($token);
            }
        }

        return $score;
    }

    private function aiDecisionReportRow(array $row, array $candidates): array
    {
        return [
            'sheet' => $row['sheet'] ?? '',
            'row_number' => $row['row_number'] ?? null,
            'article' => $row['article'] ?? '',
            'brand' => $row['resolved_brand'] ?: ($row['brand'] ?? ''),
            'name' => $row['name'] ?? '',
            'category_text' => $row['category_text'] ?? '',
            'wholesale_price_byn' => $row['price'] ?? null,
            'retail_price_byn' => $row['retail_price'] ?? null,
            'stock_status' => $row['stock']['status'] ?? null,
            'varmega_url' => $row['varmega_url'] ?? null,
            'teplodvor_url' => $row['teplodvor_url'] ?? null,
            'teplodvor_category_id' => $row['teplodvor_category_id'] ?? null,
            'rn_profi_url' => $row['rn_profi_url'] ?? null,
            'math_action' => $row['action'] ?? '',
            'math_product_id' => $row['matched_product_id'] ?? null,
            'math_sku' => $row['matched_sku'] ?? null,
            'ai_decision' => $row['ai_decision'] ?? null,
            'ai_confidence' => $row['ai_confidence'] ?? null,
            'ai_target_product_id' => $row['ai_target_product_id'] ?? null,
            'ai_recommended_action' => $row['ai_recommended_action'] ?? null,
            'ai_reason' => $row['ai_reason'] ?? null,
            'candidate_ids' => implode(',', array_map(fn (array $candidate): int => (int) $candidate['id'], $candidates)),
            'candidates' => $candidates,
        ];
    }

    private function writeAiDecisionReports(array $decisions): void
    {
        $jsonPath = $this->aiOutputPath();
        $dir = dirname($jsonPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($jsonPath, json_encode([
            'generated_at' => now()->toDateTimeString(),
            'supplier' => self::SUPPLIER_CODE,
            'decisions' => $decisions,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        $csvPath = preg_replace('/\.json$/i', '.csv', $jsonPath) ?: ($jsonPath . '.csv');
        $this->writeAiDecisionCsv($csvPath, $decisions);

        $counts = $this->counts($decisions, 'ai_decision');
        $this->info('AI match decisions:');
        $this->table(['decision', 'count'], $this->mapCounts($counts));
        $this->info('AI JSON written: ' . $jsonPath);
        $this->info('AI CSV written: ' . $csvPath);
    }

    private function aiOutputPath(): string
    {
        $path = trim((string) $this->option('ai-output'));
        if ($path === '') {
            return storage_path('app/reports/rn-profi/ai-match-rn-profi-' . now()->format('Ymd-His') . '.json');
        }

        if (! str_starts_with($path, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Z]:\\\\/i', $path)) {
            $path = base_path($path);
        }

        return $path;
    }

    private function writeAiDecisionCsv(string $path, array $rows): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            return;
        }

        $headers = [
            'sheet', 'row_number', 'article', 'brand', 'name', 'category_text',
            'wholesale_price_byn', 'retail_price_byn', 'stock_status',
            'varmega_url', 'teplodvor_url', 'rn_profi_url', 'math_action', 'math_product_id', 'math_sku',
            'ai_decision', 'ai_confidence', 'ai_target_product_id', 'ai_recommended_action',
            'ai_reason', 'candidate_ids',
        ];
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn (string $header): mixed => $row[$header] ?? '', $headers));
        }
        fclose($handle);
    }

    private function writeUnmatchedReport(array $rows): void
    {
        $unmatched = array_values(array_filter($rows, fn (array $row): bool => ($row['action'] ?? '') !== 'matched'));
        $reportRows = array_map(fn (array $row): array => $this->unmatchedReportRow($row), $unmatched);
        $path = $this->unmatchedReportPath();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, json_encode([
            'generated_at' => now()->toDateTimeString(),
            'supplier' => self::SUPPLIER_CODE,
            'rows_count' => count($reportRows),
            'rows' => $reportRows,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        $csvPath = preg_replace('/\.json$/i', '.csv', $path) ?: ($path . '.csv');
        $this->writeUnmatchedCsv($csvPath, $reportRows);

        $this->info('Unmatched JSON written: ' . $path);
        $this->info('Unmatched CSV written: ' . $csvPath);
        $this->table(['reason', 'rows'], $this->mapCounts($this->counts($reportRows, 'reason')));
    }

    private function unmatchedReportPath(): string
    {
        $path = trim((string) $this->option('unmatched-report'));
        if ($path === '' || $path === 'auto') {
            return storage_path('app/reports/rn-profi/unmatched-rn-profi-' . now()->format('Ymd-His') . '.json');
        }

        if (! str_ends_with(strtolower($path), '.json')) {
            $path .= '.json';
        }
        if (! str_starts_with($path, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Z]:\\\\/i', $path)) {
            $path = base_path($path);
        }

        return $path;
    }

    private function unmatchedReportRow(array $row): array
    {
        return [
            'sheet' => $row['sheet'] ?? '',
            'row_number' => $row['row_number'] ?? null,
            'article' => $row['article'] ?? '',
            'brand' => $row['resolved_brand'] ?: ($row['brand'] ?? ''),
            'name' => $row['name'] ?? '',
            'category_text' => $row['category_text'] ?? '',
            'wholesale_price_byn' => $row['price'] ?? null,
            'retail_price_byn' => $row['retail_price'] ?? null,
            'stock_status' => $row['stock']['status'] ?? null,
            'action' => $row['action'] ?? '',
            'teplodvor_url' => $row['teplodvor_url'] ?? null,
            'rn_profi_url' => $row['rn_profi_url'] ?? null,
            'reason' => $this->unmatchedReason($row),
            'suggested_action' => $this->unmatchedSuggestedAction($row),
        ];
    }

    private function writeUnmatchedCsv(string $path, array $rows): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            return;
        }

        $headers = [
            'sheet', 'row_number', 'article', 'brand', 'name', 'category_text',
            'wholesale_price_byn', 'retail_price_byn', 'stock_status', 'action',
            'reason', 'suggested_action', 'teplodvor_url', 'rn_profi_url',
        ];
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn (string $header): mixed => $row[$header] ?? '', $headers));
        }
        fclose($handle);
    }

    private function unmatchedReason(array $row): string
    {
        $text = mb_strtolower($this->clean(($row['name'] ?? '') . ' ' . ($row['article'] ?? '') . ' ' . ($row['category_text'] ?? '')));
        $slug = $this->compactSlug(Str::slug($text));

        if (($row['action'] ?? '') === 'brand_missing') {
            return 'brand_missing';
        }
        if (($row['action'] ?? '') === 'price_missing') {
            return 'price_missing';
        }
        if (str_contains($text, '*') || str_contains($slug, 'vkomplekt') || str_contains($slug, 'komplektvhodit')) {
            return 'bundle_note_row';
        }
        if (str_contains($text, 'дымоход') || str_contains($slug, 'dymohod')) {
            return 'accessory_chimney';
        }
        if (str_contains($text, 'кабель') || str_contains($slug, 'kabel')) {
            return 'accessory_cable';
        }
        if (str_contains($slug, 'fl') || str_contains($slug, 'fл') || str_contains($text, 'fл')) {
            return 'model_needs_normalization';
        }
        if (($row['teplodvor_url'] ?? null) === null) {
            return 'source_card_not_found';
        }

        return 'not_matched';
    }

    private function unmatchedSuggestedAction(array $row): string
    {
        return match ($this->unmatchedReason($row)) {
            'bundle_note_row' => 'ignore_or_attach_as_note',
            'accessory_chimney', 'accessory_cable' => 'review_as_accessory',
            'model_needs_normalization' => 'normalize_model_or_check_official_thermex',
            'source_card_not_found' => 'check_official_thermex_or_manual_review',
            'brand_missing' => 'create_or_map_brand',
            'price_missing' => 'skip_until_price_present',
            default => 'manual_review',
        };
    }

    private function extractJson(string $response): string
    {
        $response = trim($response);
        $response = preg_replace('/```(?:json)?/i', '', $response) ?? $response;
        $response = str_replace('```', '', $response);
        $start = strpos($response, '{');
        $end = strrpos($response, '}');
        if ($start === false || $end === false || $end <= $start) {
            return $response;
        }

        return substr($response, $start, $end - $start + 1);
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

        if ($this->option('varmega-official')) {
            $this->info('Official Varmega card matches:');
            $matchedVarmega = array_values(array_filter($rows, fn (array $row): bool => ! empty($row['varmega_url'])));
            $this->table(['metric', 'count'], [
                ['matched card URLs', count($matchedVarmega)],
                ['missing card URLs', count($rows) - count($matchedVarmega)],
                ['matched by article sitemap', count(array_filter($matchedVarmega, fn (array $row): bool => ($row['varmega_confidence'] ?? '') === 'article_sitemap'))],
            ]);

            $this->info('Official Varmega matches by sheet:');
            $this->table(['sheet', 'matched', 'missing', 'rows'], $this->varmegaOfficialSheetRows($rows));
        }

        if ($this->option('teplodvor')) {
            $this->info('Teplodvor card matches:');
            $matchedTeplodvor = array_values(array_filter($rows, fn (array $row): bool => ! empty($row['teplodvor_url'])));
            $this->table(['metric', 'count'], [
                ['matched card URLs', count($matchedTeplodvor)],
                ['missing card URLs', count($rows) - count($matchedTeplodvor)],
                ['matched by article in slug', count(array_filter($matchedTeplodvor, fn (array $row): bool => ($row['teplodvor_confidence'] ?? '') === 'article_slug'))],
                ['matched by brand page article', count(array_filter($matchedTeplodvor, fn (array $row): bool => ($row['teplodvor_confidence'] ?? '') === 'brand_page_article'))],
                ['matched by model in slug', count(array_filter($matchedTeplodvor, fn (array $row): bool => ($row['teplodvor_confidence'] ?? '') === 'model_slug'))],
                ['matched by probed URL', count(array_filter($matchedTeplodvor, fn (array $row): bool => ($row['teplodvor_confidence'] ?? '') === 'probed_model_url'))],
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

        if ($this->option('varmega-official')) {
            $varmegaMatched = array_values(array_filter($rows, fn (array $row): bool => ! empty($row['varmega_url'])));
            if ($varmegaMatched !== []) {
                $this->info('Official Varmega card examples:');
                $this->table($this->exampleHeaders(), $this->exampleRows(array_slice($varmegaMatched, 0, 20)));
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

    private function applyAiDecisions(string $path, bool $apply): int
    {
        $path = $this->resolveLocalPath($path);
        if (! is_file($path)) {
            $this->error('AI decisions file not found: ' . $path);
            return self::FAILURE;
        }

        $data = json_decode((string) file_get_contents($path), true);
        $decisions = is_array($data) ? ($data['decisions'] ?? []) : [];
        if (! is_array($decisions) || $decisions === []) {
            $this->error('AI decisions file has no decisions.');
            return self::FAILURE;
        }

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: safe AI decisions will be written.</>'
            : '<fg=yellow;options=bold>DRY RUN: AI decisions will be previewed only.</>');
        $this->info('AI decisions file: ' . $path);

        $now = now();
        $supplierId = $this->ensureSupplier($now);
        $syncId = $this->ensureSync($now);
        $this->buildIndex();
        $categoryUpdateOnly = (bool) $this->option('category-update-only');
        $stats = array_fill_keys([
            'safe_decisions', 'linked_existing', 'created_products', 'source_previews', 'enriched_created',
            'category_updated', 'skipped_manual_review', 'skipped_low_confidence', 'skipped_duplicate', 'errors',
        ], 0);
        $previewRows = [];

        foreach ($decisions as $decision) {
            if (! is_array($decision)) {
                continue;
            }

            $aiDecision = (string) ($decision['ai_decision'] ?? '');
            $recommended = (string) ($decision['ai_recommended_action'] ?? '');
            $confidence = (int) ($decision['ai_confidence'] ?? 0);
            $article = $this->normArticle((string) ($decision['article'] ?? ''));

            if (! in_array($aiDecision, ['link_existing', 'create_new'], true) || $recommended !== 'can_apply_after_review') {
                $stats['skipped_manual_review']++;
                continue;
            }
            if ($confidence < max(1, min(100, (int) $this->option('ai-min-confidence')))) {
                $stats['skipped_low_confidence']++;
                continue;
            }
            if ($article === '') {
                $stats['errors']++;
                $previewRows[] = [$decision['article'] ?? '-', $aiDecision, '-', '-', 'error_empty_article'];
                continue;
            }

            $stats['safe_decisions']++;
            try {
                if ($categoryUpdateOnly) {
                    $this->categoryIdFromDecision($decision, $apply, $now);
                }

                if ($aiDecision === 'link_existing') {
                    if ($categoryUpdateOnly) {
                        $previewRows[] = [$decision['article'] ?? '-', $aiDecision, '-', '-', 'skip_category_update_only'];
                        continue;
                    }

                    $productId = (int) ($decision['ai_target_product_id'] ?? 0);
                    if ($productId <= 0 || ! DB::table('products')->where('id', $productId)->exists()) {
                        $stats['errors']++;
                        $previewRows[] = [$decision['article'] ?? '-', $aiDecision, '-', '-', 'error_missing_target_product'];
                        continue;
                    }

                    if ($apply) {
                        $this->upsertSupplierProduct($this->decisionToSupplierRow($decision, $productId), $supplierId, $syncId, $now);
                    }
                    $stats['linked_existing']++;
                    $previewRows[] = [$decision['article'] ?? '-', $aiDecision, $productId, $this->sku($productId), $apply ? 'linked' : 'would_link'];
                    continue;
                }

                if (DB::table('supplier_products')->where('supplier_id', $supplierId)->where('supplier_article_normalized', $article)->exists()) {
                    $productId = (int) DB::table('supplier_products')
                        ->where('supplier_id', $supplierId)
                        ->where('supplier_article_normalized', $article)
                        ->value('product_id');
                    if ($this->option('update-existing-categories') && $productId > 0 && $this->updateProductCategoryFromDecision($productId, $decision, $apply, $now)) {
                        $stats['category_updated']++;
                    }
                    $stats['skipped_duplicate']++;
                    $previewRows[] = [$decision['article'] ?? '-', $aiDecision, '-', '-', 'skip_duplicate_supplier_article'];
                    continue;
                }

                if ($categoryUpdateOnly) {
                    $previewRows[] = [$decision['article'] ?? '-', $aiDecision, '-', '-', 'skip_new_category_update_only'];
                    continue;
                }

                $sourcePreview = [];
                if ($aiDecision === 'create_new') {
                    $sourceUrl = $this->decisionSourceUrl($decision);
                    if ($sourceUrl !== '') {
                        try {
                            $sourcePreview = app(ProductSourceEnricher::class)->preview($sourceUrl);
                            $stats['source_previews']++;
                        } catch (\Throwable $e) {
                            $this->warn('[source preview] ' . ($decision['article'] ?? '-') . ': ' . $e->getMessage());
                        }
                    }
                }

                if (! $apply) {
                    $previewRows[] = [$decision['article'] ?? '-', $aiDecision, '-', $this->nextKotlovSku(), 'would_create: ' . mb_substr($this->productNameFromDecision($decision, $sourcePreview), 0, 48)];
                    $stats['created_products']++;
                    continue;
                }

                $productId = $this->createProductFromAiDecision($decision, $now, $sourcePreview);
                $sku = $this->sku($productId);
                $this->upsertSupplierProduct($this->decisionToSupplierRow($decision, $productId, $sourcePreview), $supplierId, $syncId, $now);
                $stats['created_products']++;

                if ((bool) $this->option('enrich-created')) {
                    $sourceUrl = $this->decisionSourceUrl($decision);
                    if ($sourceUrl !== '') {
                        $product = \App\Models\Product::findOrFail($productId);
                        $options = [
                            'update_images' => true,
                            'replace_images' => true,
                            'update_specs' => true,
                            'update_service' => true,
                            'update_content' => true,
                        ];

                        if ($sourcePreview !== []) {
                            app(ProductSourceEnricher::class)->enrichFromParsed($product, $sourceUrl, $sourcePreview, $options);
                        } else {
                            app(ProductSourceEnricher::class)->enrich($product, $sourceUrl, $options);
                        }
                        $stats['enriched_created']++;
                    }
                }

                $previewRows[] = [$decision['article'] ?? '-', $aiDecision, $productId, $sku, 'created'];
            } catch (\Throwable $e) {
                $stats['errors']++;
                $previewRows[] = [$decision['article'] ?? '-', $aiDecision, '-', '-', 'error: ' . mb_substr($e->getMessage(), 0, 80)];
            }
        }

        $this->table(['metric', 'count'], $this->mapCounts($stats));
        $this->table(['article', 'decision', 'product_id', 'sku', 'result'], array_slice($previewRows, 0, 50));

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function createProductFromAiDecision(array $decision, $now, array $sourcePreview = []): int
    {
        $brandName = trim((string) ($decision['brand'] ?? ''));
        $brandId = $this->brandByName[$this->brandKey($brandName)] ?? null;
        if ($brandId === null && $brandName !== '') {
            $brandId = (int) DB::table('brands')->insertGetId([
                'name' => $brandName,
                'slug' => $this->uniqueBrandSlug($brandName),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $categoryId = $this->categoryIdFromDecision($decision, true, $now);
        if ($categoryId <= 0 || ! DB::table('categories')->where('id', $categoryId)->exists()) {
            $categoryId = $this->fallbackCategoryId();
        }

        $article = trim((string) ($decision['article'] ?? ''));
        $name = $this->productNameFromDecision($decision, $sourcePreview);
        $sku = $this->nextKotlovSku();
        $short = trim($name . ($article !== '' ? ' (' . $article . ')' : ''));

        return (int) DB::table('products')->insertGetId([
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'supplier_id' => null,
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'h1' => $name,
            'sku' => $sku,
            'price' => (float) ($decision['retail_price_byn'] ?? 0),
            'price_old' => null,
            'currency' => 'BYN',
            'content' => null,
            'short_description' => $short,
            'images' => json_encode([], JSON_UNESCAPED_UNICODE),
            'specs' => json_encode([], JSON_UNESCAPED_UNICODE),
            'unit' => 'шт',
            'warranty' => null,
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => ($decision['stock_status'] ?? '') === 'in_stock',
            'availability_status' => ($decision['stock_status'] ?? '') === 'in_stock'
                ? \App\Models\Product::AVAILABILITY_IN_STOCK
                : \App\Models\Product::AVAILABILITY_CHECK,
            'stock_qty' => null,
            'is_featured' => false,
            'is_new' => true,
            'is_sale' => false,
            'sort_order' => 0,
            'meta_title' => $name . ' купить в %city%',
            'meta_keywords' => trim($brandName . ', ' . $name . ', ' . $article, ', '),
            'meta_description' => Str::limit($short . ' — купить с доставкой по Беларуси.', 250, ''),
            'rating' => 0,
            'reviews_count' => 0,
            'views_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function decisionToSupplierRow(array $decision, int $productId, array $sourcePreview = []): array
    {
        $stockStatus = (string) ($decision['stock_status'] ?? 'in_stock');
        return [
            'article' => (string) ($decision['article'] ?? ''),
            'norm_article' => $this->normArticle((string) ($decision['article'] ?? '')),
            'brand' => (string) ($decision['brand'] ?? ''),
            'resolved_brand' => (string) ($decision['brand'] ?? ''),
            'name' => $this->productNameFromDecision($decision, $sourcePreview),
            'category_text' => (string) ($decision['category_text'] ?? ''),
            'price' => isset($decision['wholesale_price_byn']) ? (float) $decision['wholesale_price_byn'] : null,
            'retail_price' => isset($decision['retail_price_byn']) ? (float) $decision['retail_price_byn'] : null,
            'stock_text' => (string) ($decision['stock_status'] ?? ''),
            'qty' => null,
            'stock' => [
                'status' => $stockStatus,
                'in_stock' => $stockStatus === 'in_stock',
                'delivery_days' => null,
            ],
            'matched_product_id' => $productId,
            'matched_sku' => $this->sku($productId),
            'confidence' => 'ai_' . ($decision['ai_confidence'] ?? ''),
            'sheet' => (string) ($decision['sheet'] ?? ''),
            'row_number' => (int) ($decision['row_number'] ?? 0),
            'source_url' => $this->decisionSourceUrl($decision) ?: self::SOURCE_URL,
            'raw_source' => [
                'varmega_url' => $decision['varmega_url'] ?? null,
                'teplodvor_url' => $decision['teplodvor_url'] ?? null,
                'rn_profi_url' => $decision['rn_profi_url'] ?? null,
                'ai_decision' => $decision['ai_decision'] ?? null,
                'ai_confidence' => $decision['ai_confidence'] ?? null,
                'ai_reason' => $decision['ai_reason'] ?? null,
            ],
        ];
    }

    private function productNameFromDecision(array $decision, array $sourcePreview = []): string
    {
        $brand = trim((string) ($decision['brand'] ?? ''));
        $article = trim((string) ($decision['article'] ?? ''));
        $sourceTitle = trim((string) ($sourcePreview['title'] ?? ''));
        if (mb_strlen($sourceTitle) >= 5) {
            return preg_replace('/\s+/u', ' ', $sourceTitle) ?: $sourceTitle;
        }

        $rawName = trim((string) ($decision['name'] ?? ''));

        if (mb_strlen($rawName) < 5 || ! preg_match('/[А-Яа-яA-Za-z]{3,}/u', $rawName)) {
            $rawName = trim(($decision['category_text'] ?? '') . ' ' . $article . ' ' . $rawName);
        }
        $name = trim($brand . ' ' . $rawName);
        return preg_replace('/\s+/u', ' ', $name) ?: trim($brand . ' ' . $article);
    }

    private function decisionSourceUrl(array $decision): string
    {
        foreach (['varmega_url', 'teplodvor_url', 'rn_profi_url'] as $field) {
            $url = trim((string) ($decision[$field] ?? ''));
            if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
                return $url;
            }
        }

        return '';
    }

    private function categoryIdFromDecision(array $decision, bool $createMissing = false, $now = null): int
    {
        $categoryId = (int) ($decision['teplodvor_category_id'] ?? 0);
        if ($categoryId > 0) {
            return $categoryId;
        }

        $varmegaUrl = trim((string) ($decision['varmega_url'] ?? ''));
        if ($varmegaUrl === '') {
            return 0;
        }

        $path = $this->varmegaCategoryPath($varmegaUrl);
        foreach (self::VARMEGA_CATEGORY_MAP as $prefix => $categorySlug) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return $this->categoryIdBySlug((string) $categorySlug, $createMissing, $now);
            }
        }

        return 0;
    }

    private function categoryIdBySlug(string $slug, bool $createMissing = false, $now = null): int
    {
        $slug = trim($slug);
        if ($slug === '') {
            return 0;
        }

        $categoryId = (int) (DB::table('categories')->where('slug', $slug)->value('id') ?? 0);
        if ($categoryId > 0 || ! $createMissing || ! isset(self::VARMEGA_CATEGORY_DEFINITIONS[$slug])) {
            return $categoryId;
        }

        $definition = self::VARMEGA_CATEGORY_DEFINITIONS[$slug];
        $parentId = 0;
        $parentSlug = (string) ($definition['parent_slug'] ?? '');
        if ($parentSlug !== '') {
            $parentId = $this->categoryIdBySlug($parentSlug, false, $now);
        }

        return (int) DB::table('categories')->insertGetId([
            'parent_id' => $parentId,
            'name' => (string) $definition['name'],
            'slug' => $slug,
            'h1' => (string) $definition['name'],
            'type' => (string) ($definition['type'] ?? ($parentId > 0 ? 'child' : 'main')),
            'sort_order' => (int) ($definition['sort_order'] ?? 0),
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function updateProductCategoryFromDecision(int $productId, array $decision, bool $apply, $now): bool
    {
        $categoryId = $this->categoryIdFromDecision($decision, $apply, $now);
        if ($categoryId <= 0 || ! DB::table('categories')->where('id', $categoryId)->exists()) {
            return false;
        }

        $currentCategoryId = (int) DB::table('products')->where('id', $productId)->value('category_id');
        if ($currentCategoryId === $categoryId) {
            return false;
        }

        if ($apply) {
            DB::table('products')->where('id', $productId)->update([
                'category_id' => $categoryId,
                'updated_at' => $now,
            ]);
        }

        return true;
    }

    private function fallbackCategoryId(): int
    {
        return (int) (DB::table('categories')->where('slug', 'raznoe')->value('id')
            ?? DB::table('categories')->orderBy('id')->value('id')
            ?? 1);
    }

    private function uniqueBrandSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'brand';
        $slug = $base;
        $i = 2;
        while (DB::table('brands')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'rn-profi-product';
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

        $next = max(0, (int) $max) + 1;
        do {
            $sku = sprintf('KOTLOV-%06d', $next++);
        } while (DB::table('products')->where('sku', $sku)->exists());

        return $sku;
    }

    private function resolveLocalPath(string $path): string
    {
        $path = trim($path);
        if ($path !== '' && ! str_starts_with($path, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Z]:\\\\/i', $path)) {
            return base_path($path);
        }

        return $path;
    }

    private function upsertSupplierProduct(array $row, int $supplierId, ?int $syncId, $now): void
    {
        $payload = [
            'supplier_sync_id' => $syncId,
            'product_id' => (int) $row['matched_product_id'],
            'product_sku' => $row['matched_sku'],
            'supplier_name' => trim(($row['resolved_brand'] ?: $row['brand']) . ' ' . $row['name']),
            'source_url' => $row['source_url'] ?? self::SOURCE_URL,
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
                'source' => $row['raw_source'] ?? [],
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

    private function looksLikeStockText(string $text): bool
    {
        $low = mb_strtolower($this->clean($text));

        return $low !== '' && (
            str_contains($low, 'в наличии')
            || str_contains($low, 'налич')
            || str_contains($low, 'ожидается')
            || str_contains($low, 'ожид')
            || str_contains($low, 'поставка')
            || str_contains($low, 'под заказ')
            || str_contains($low, 'заказ')
            || str_contains($low, 'нет')
            || str_contains($low, 'отсут')
            || str_contains($low, 'склад')
        );
    }

    private function stock(string $text, ?float $qty): array
    {
        $low = mb_strtolower($this->clean($text));
        if ($qty !== null && $qty > 0) {
            return ['status' => 'in_stock', 'in_stock' => true, 'delivery_days' => 0];
        }
        if (str_contains($low, 'ожидается') || str_contains($low, 'ожид') || str_contains($low, 'скоро')) {
            return ['status' => 'expected', 'in_stock' => false, 'delivery_days' => 3];
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

    private function varmegaOfficialSheetRows(array $rows): array
    {
        $stats = [];
        foreach ($rows as $row) {
            $sheet = $row['sheet'];
            $stats[$sheet] ??= ['matched' => 0, 'missing' => 0, 'rows' => 0];
            if (! empty($row['varmega_url'])) {
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
        if ($this->option('varmega-official')) {
            $headers[] = 'varmega';
            $headers[] = 'vm_cat';
        }
        if ($this->option('teplodvor')) {
            $headers[] = 'teplodvor';
            $headers[] = 'td_score';
            $headers[] = 'cat_id';
        }
        if ($this->option('ai-match')) {
            $headers[] = 'ai_decision';
            $headers[] = 'ai_conf';
            $headers[] = 'ai_target';
            $headers[] = 'ai_action';
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

            if ($this->option('varmega-official')) {
                $data[] = $row['varmega_url'] ? mb_substr((string) $row['varmega_url'], 0, 56) : '-';
                $data[] = $row['varmega_category_path'] ? mb_substr((string) $row['varmega_category_path'], 0, 32) : '-';
            }

            if ($this->option('teplodvor')) {
                $data[] = $row['teplodvor_url'] ? mb_substr((string) $row['teplodvor_url'], 0, 56) : '-';
                $data[] = $row['teplodvor_score'] !== null ? number_format((float) $row['teplodvor_score'], 2, '.', '') : '-';
                $data[] = $row['teplodvor_category_id'] ?? '-';
            }

            if ($this->option('ai-match')) {
                $data[] = $row['ai_decision'] ?? '-';
                $data[] = $row['ai_confidence'] ?? '-';
                $data[] = $row['ai_target_product_id'] ?? '-';
                $data[] = $row['ai_recommended_action'] ?? '-';
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

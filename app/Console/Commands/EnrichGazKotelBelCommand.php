<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Enrich ЖИТОМИР / GKB products with descriptions and per-model specs
 * scraped from gazkotelbel.com series pages.
 *
 * Strategy:
 *   - Each series page has ONE description block + a specs table where
 *     columns are individual models and rows are parameters.
 *   - We apply the series description to ALL products in the series and
 *     extract per-model specs from the matching column.
 *   - No images (gazkotelbel.com only has SVG placeholders).
 *
 *   php artisan supplier:enrich-gazkotelbel --dry-run
 *   php artisan supplier:enrich-gazkotelbel --apply
 *   php artisan supplier:enrich-gazkotelbel --apply --only-missing
 *   php artisan supplier:enrich-gazkotelbel --apply --series=zhytomyr-3
 */
class EnrichGazKotelBelCommand extends Command
{
    protected $signature = 'supplier:enrich-gazkotelbel
        {--dry-run : Preview only (default)}
        {--apply : Write changes to the database}
        {--only-missing : Skip products that already have a description}
        {--series= : Process only one series key (e.g. zhytomyr-3)}
        {--sleep=800 : Delay between page fetches, ms}';

    protected $description = 'Enrich ЖИТОМИР/GKB products with descriptions and specs from gazkotelbel.com.';

    private const SUPPLIER_CODE = 'gazkotelbel';

    /**
     * Series key → source page + article matching config.
     *
     * 'prefixes' matches supplier_article LIKE 'prefix%'
     * 'articles' matches exact supplier_article values
     */
    private const SERIES = [
        'zhytomyr-3' => [
            'url'      => 'https://gazkotelbel.com/products/zhytomyr-3/',
            'prefixes' => ['Ж3-КС-Г-', 'Ж3-КС-ГВ-'],
        ],
        'zhytomyr-10' => [
            'url'      => 'https://gazkotelbel.com/products/zhytomyr-10/',
            'prefixes' => ['Ж10-КС-Г-'],
        ],
        'zhytomyr-turbo' => [
            'url'      => 'https://gazkotelbel.com/products/zhytomyr-turbo/',
            'prefixes' => ['ТУРБО-КС-Г-'],
        ],
        'zhytomyr-9' => [
            'url'      => 'https://gazkotelbel.com/products/zhytomyr-9/',
            'prefixes' => ['КС-Г-'],
        ],
        'zhytomyr-m' => [
            'url'      => 'https://gazkotelbel.com/products/zhytomyr-m/',
            'prefixes' => ['АОГВ-', 'АДГВ-'],
        ],
        'zhytomyr-aotv' => [
            'url'      => 'https://gazkotelbel.com/products/zhytomyr-aotv-aktv/',
            'prefixes' => ['АОТВ-', 'АКТВ-'],
        ],
        'zhytomyr-doors' => [
            'url'      => 'https://gazkotelbel.com/products/zhytomyr/',
            'prefixes' => ['ЖИТОМИР-'],
        ],
        'zhytomyr-5' => [
            'url'      => 'https://gazkotelbel.com/products/zhytomyr-5/',
            'prefixes' => ['КНС-'],
        ],
        'vpg-20' => [
            'url'      => 'https://gazkotelbel.com/products/hsv-20/',
            'articles' => ['ВПГ-20'],
        ],
        'vpg-20m' => [
            'url'      => 'https://gazkotelbel.com/products/hsv-20m/',
            'articles' => ['ВПГ-20М'],
        ],
        'vpg-20tm' => [
            'url'      => 'https://gazkotelbel.com/products/hsv-20tm/',
            'articles' => ['ВПГ-20ТМ'],
        ],
        'vpg-20t' => [
            'url'      => 'https://gazkotelbel.com/products/vpg-20t/',
            'articles' => ['ВПГ-20Т'],
        ],
    ];

    /** Known specs key translations → our internal naming */
    private const SPEC_MAP = [
        'Номинальная мощность (кВт)'  => 'Мощность',
        'Мощность (кВт)'              => 'Мощность',
        'Отапливаемая площадь (м²)'   => 'Площадь отопления',
        'Объем помещения (м³)'        => 'Объём помещения',
        'КПД (%)'                     => 'КПД',
        'Средний расход газа (м³/ч)'  => 'Расход газа (ср.)',
        'Расход природного газа (м³/ч)' => 'Расход газа',
        'Масса нетто (кг)'            => 'Вес',
        'Масса (кг)'                  => 'Вес',
        'Объем воды в котле (л)'      => 'Объём воды',
        'Рабочее давление (Бар)'      => 'Рабочее давление',
        'Габариты (Г×Ш×В, мм)'       => 'Габариты',
        'Максимальная температура воды (°С)' => 'Макс. температура воды',
    ];

    private int   $supplierId = 0;
    private bool  $apply      = false;
    private array $stats      = [
        'series'   => 0,
        'products' => 0,
        'updated'  => 0,
        'skipped'  => 0,
        'errors'   => 0,
    ];

    // ── Entry point ──────────────────────────────────────────────────────────────

    public function handle(): int
    {
        $this->apply = (bool) $this->option('apply') && ! $this->option('dry-run');
        $this->line($this->apply
            ? '<fg=red;options=bold>APPLY — database will be updated.</>'
            : '<fg=yellow;options=bold>DRY RUN — database will not be changed.</>');

        $this->supplierId = (int) (DB::table('suppliers')
            ->where('code', self::SUPPLIER_CODE)->value('id') ?? 0);
        if ($this->supplierId === 0) {
            $this->error('Supplier «' . self::SUPPLIER_CODE . '» not found — run supplier:sync-gazkotelbel first.');
            return self::FAILURE;
        }

        $onlySeries  = $this->option('series');
        $onlyMissing = (bool) $this->option('only-missing');
        $sleepMs     = (int) ($this->option('sleep') ?: 800);

        foreach (self::SERIES as $key => $config) {
            if ($onlySeries && $onlySeries !== $key) {
                continue;
            }

            $this->newLine();
            $this->info("── Series: <fg=cyan>{$key}</>");
            $this->stats['series']++;

            // 1. Fetch page
            try {
                $html = $this->fetchPage($config['url']);
            } catch (\Throwable $e) {
                $this->error("  fetch failed: " . $e->getMessage());
                $this->stats['errors']++;
                continue;
            }

            // 2. Parse content
            $description = $this->parseDescription($html);
            $specsTable  = $this->parseSpecsTable($html);

            $preview = mb_substr(strip_tags($description), 0, 90);
            $this->line("  desc:  " . ($preview ?: '<none>'));
            $this->line("  specs: " . count($specsTable) . " columns → [" . implode(', ', array_keys($specsTable)) . "]");

            // 3. Find catalog products via supplier_products
            $products = $this->findProducts($config);
            $this->line("  found: " . count($products) . " product(s)");

            foreach ($products as $product) {
                $this->stats['products']++;

                if ($onlyMissing && ! empty($product->content)) {
                    $this->line("  skip (has desc): [{$product->supplier_article}]");
                    $this->stats['skipped']++;
                    continue;
                }

                $modelSpecs = $this->matchModelSpecs($product->supplier_article, $specsTable);

                $action = $this->apply ? '<fg=green>update</>' : 'would update';
                $this->line("  {$action}: [{$product->supplier_article}] {$product->name}" .
                    ($modelSpecs ? " (" . count($modelSpecs) . " specs)" : ""));

                if ($this->apply) {
                    try {
                        $this->updateProduct($product, $description, $modelSpecs);
                        $this->stats['updated']++;
                    } catch (\Throwable $e) {
                        $this->warn("  [error] {$product->supplier_article}: " . $e->getMessage());
                        $this->stats['errors']++;
                    }
                }
            }

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        $this->newLine();
        $this->table(
            ['metric', 'count'],
            array_map(fn ($k, $v) => [$k, $v], array_keys($this->stats), array_values($this->stats))
        );

        return $this->stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    // ── HTTP fetch ───────────────────────────────────────────────────────────────

    private function fetchPage(string $url): string
    {
        $ctx = stream_context_create([
            'http' => [
                'header'  => "User-Agent: Mozilla/5.0 (compatible; Kotlov-Bot/1.0)\r\nAccept-Language: ru-RU,ru;q=0.9\r\n",
                'timeout' => 20,
            ],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $html = @file_get_contents($url, false, $ctx);
        if ($html === false || $html === '') {
            throw new \RuntimeException("HTTP fetch returned empty for $url");
        }
        return $html;
    }

    // ── Description parsing ──────────────────────────────────────────────────────

    private function parseDescription(string $html): string
    {
        // Remove scripts/styles
        $clean = preg_replace(['/<script[^>]*>.*?<\/script>/si', '/<style[^>]*>.*?<\/style>/si'], '', $html) ?? '';

        // Try to find product description container
        $content = '';
        $selectors = [
            '/<div[^>]*class="[^"]*(?:woocommerce-product-details__short-description|entry-content|product-description|tab-content)[^"]*"[^>]*>(.*?)<\/div>/si',
            '/<div[^>]*class="[^"]*description[^"]*"[^>]*>(.*?)<\/div>/si',
            '/<article[^>]*>(.*?)<\/article>/si',
        ];

        foreach ($selectors as $pattern) {
            if (preg_match($pattern, $clean, $m)) {
                $content = $m[1];
                break;
            }
        }

        if ($content === '') {
            $content = $clean;
        }

        // Extract <p> paragraphs
        preg_match_all('/<p[^>]*>(.*?)<\/p>/si', $content, $matches);
        $paragraphs = [];
        foreach ($matches[1] ?? [] as $p) {
            $text = trim(strip_tags($p));
            $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
            if (mb_strlen($text) > 30) {
                $paragraphs[] = $text;
            }
        }

        // Take first 6 meaningful paragraphs
        $paragraphs = array_slice($paragraphs, 0, 6);

        return implode("\n\n", $paragraphs);
    }

    // ── Specs table parsing ──────────────────────────────────────────────────────

    /**
     * Returns [ 'colKey' => ['SpecName' => 'value', ...], ... ]
     * colKey is a short numeric string from the model header:
     *   "КС-Г 007 СН" → "007"
     *   "КНС-2"        → "2"
     *   "АОГВ-7СН"     → "7"
     */
    private function parseSpecsTable(string $html): array
    {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR);
        libxml_clear_errors();

        $tables = $doc->getElementsByTagName('table');
        if ($tables->length === 0) {
            return [];
        }

        // Find the specs table: prefer table with ≥3 columns AND numeric model headers
        $targetTable = null;
        foreach ($tables as $tbl) {
            $firstRow = $tbl->getElementsByTagName('tr')->item(0);
            if ($firstRow === null) {
                continue;
            }
            $cells = [];
            foreach ($firstRow->childNodes as $node) {
                if ($node->nodeType === XML_ELEMENT_NODE && in_array(strtolower($node->nodeName), ['th','td'])) {
                    $cells[] = trim($node->textContent);
                }
            }
            if (count($cells) < 3) {
                continue;
            }
            // Check that at least one header (skip col[0]) contains a digit
            $hasNumeric = false;
            foreach (array_slice($cells, 1) as $h) {
                if (preg_match('/\d/', $h)) {
                    $hasNumeric = true;
                    break;
                }
            }
            if ($hasNumeric) {
                $targetTable = $tbl;
                break;
            }
        }
        // Fallback: first table with ≥ 2 columns
        if ($targetTable === null) {
            foreach ($tables as $tbl) {
                $firstRow = $tbl->getElementsByTagName('tr')->item(0);
                if ($firstRow === null) continue;
                $cellCount = $firstRow->getElementsByTagName('th')->length
                           + $firstRow->getElementsByTagName('td')->length;
                if ($cellCount >= 2) { $targetTable = $tbl; break; }
            }
        }

        if ($targetTable === null) {
            return [];
        }

        // Read all rows
        $rows = [];
        foreach ($targetTable->getElementsByTagName('tr') as $tr) {
            $cells = [];
            /** @var \DOMElement $cell */
            foreach ($tr->childNodes as $node) {
                if ($node->nodeType !== XML_ELEMENT_NODE) {
                    continue;
                }
                $tag = strtolower($node->nodeName);
                if ($tag === 'th' || $tag === 'td') {
                    $cells[] = trim($node->textContent);
                }
            }
            if ($cells !== []) {
                $rows[] = $cells;
            }
        }

        if (count($rows) < 2) {
            return [];
        }

        // Header row: col 0 = param name, cols 1..N = model values
        $headers = $rows[0];

        // Extract numeric key from each header cell
        $colKeys = [];
        for ($i = 1; $i < count($headers); $i++) {
            $colKeys[$i] = $this->extractColKey($headers[$i]);
        }

        // Build specs per column key
        $result = [];
        foreach ($colKeys as $key) {
            $result[$key] = [];
        }

        foreach (array_slice($rows, 1) as $row) {
            $param = trim($row[0] ?? '');
            if ($param === '') {
                continue;
            }
            foreach ($colKeys as $idx => $key) {
                $val = trim($row[$idx] ?? '');
                if ($val !== '' && $val !== '—' && $val !== '-') {
                    $result[$key][$param] = $val;
                }
            }
        }

        return $result;
    }

    private function extractColKey(string $header): string
    {
        // Three-digit zero-padded: "КС-Г 007 СН" → "007"
        if (preg_match('/\b(\d{3})\b/', $header, $m)) {
            return $m[1];
        }
        // Short number at end: "КНС-2", "АОГВ-7СН" → "2", "7"
        if (preg_match('/[-–](\d+)/u', $header, $m)) {
            return $m[1];
        }
        return mb_substr($header, 0, 20);
    }

    // ── Product lookup ───────────────────────────────────────────────────────────

    private function findProducts(array $config): array
    {
        $q = DB::table('supplier_products as sp')
            ->join('products as p', 'p.id', '=', 'sp.product_id')
            ->where('sp.supplier_id', $this->supplierId)
            ->whereNotNull('sp.product_id')
            ->select('p.id', 'p.name', 'p.content', 'p.specs', 'sp.supplier_article');

        if (! empty($config['articles'])) {
            $q->whereIn('sp.supplier_article', $config['articles']);
        } elseif (! empty($config['prefixes'])) {
            $q->where(function ($w) use ($config) {
                foreach ($config['prefixes'] as $prefix) {
                    $w->orWhere('sp.supplier_article', 'like', $prefix . '%');
                }
            });
        }

        return $q->get()->toArray();
    }

    // ── Specs matching ───────────────────────────────────────────────────────────

    private function matchModelSpecs(string $article, array $specsTable): array
    {
        if (empty($specsTable)) {
            return [];
        }

        // Extract key from article: "Ж3-КС-Г-007СН" → "007", "КНС-2" → "2", "АОГВ-7СН" → "7"
        $key = null;
        if (preg_match('/[-–](\d{3})/u', $article, $m)) {
            $key = $m[1];
        } elseif (preg_match('/[-–](\d+)/u', $article, $m)) {
            $key = $m[1];
        }

        if ($key === null) {
            return [];
        }

        // Try exact key, then zero-padded 3-digit, then stripped leading zeros
        foreach (array_unique([$key, str_pad($key, 3, '0', STR_PAD_LEFT), ltrim($key, '0') ?: $key]) as $candidate) {
            if (isset($specsTable[$candidate])) {
                return $this->translateSpecs($specsTable[$candidate]);
            }
        }

        return [];
    }

    private function translateSpecs(array $raw): array
    {
        $result = [];
        foreach ($raw as $k => $v) {
            $translated = self::SPEC_MAP[$k] ?? $k;
            $result[$translated] = $v;
        }
        return $result;
    }

    // ── Database update ──────────────────────────────────────────────────────────

    private function updateProduct(object $product, string $description, array $modelSpecs): void
    {
        $existing = [];
        if (! empty($product->specs)) {
            $decoded = json_decode($product->specs, true);
            if (is_array($decoded)) {
                $existing = $decoded;
            }
        }

        // New specs take priority over existing
        $mergedSpecs = array_merge($existing, $modelSpecs);

        DB::table('products')->where('id', $product->id)->update([
            'content'           => $description,
            'short_description' => $this->makeShortDesc($description),
            'specs'             => $mergedSpecs !== [] ? json_encode($mergedSpecs, JSON_UNESCAPED_UNICODE) : $product->specs,
            'updated_at'        => now(),
        ]);
    }

    private function makeShortDesc(string $description): string
    {
        if ($description === '') {
            return '';
        }
        // Take text up to first double newline (first paragraph)
        $first = explode("\n\n", $description, 2)[0];
        // Limit to ~250 chars
        if (mb_strlen($first) > 250) {
            $first = mb_substr($first, 0, 247) . '…';
        }
        return trim($first);
    }
}

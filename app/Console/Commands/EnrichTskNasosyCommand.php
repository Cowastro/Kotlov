<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ScrapesAqualiderCard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Enrich ТСК Насосы products with photo / description / characteristics from
 * their EXACT aqualider.by card.
 *
 * The price workbook has a dedicated «aqualider» tab mapping each supplier
 * article (col A) to the product's aqualider.by card via a cell hyperlink
 * (col C). That gives a precise article → card URL link — no fuzzy brand/model
 * matching. We read that map straight from the XLSX (the CSV export drops cell
 * hyperlinks), then scrape each linked card and write the photo, description and
 * specs onto the matching product (joined through supplier_products.article).
 *
 *   php artisan supplier:enrich-tsk-nasosy --dry-run
 *   php artisan supplier:enrich-tsk-nasosy --in-stock-only --apply
 *
 * Pricing/stock stay owned by supplier:sync-tsk-nasosy (the price sheet); this
 * command only backfills content and never touches price.
 */
class EnrichTskNasosyCommand extends Command
{
    use ScrapesAqualiderCard;

    protected $signature = 'supplier:enrich-tsk-nasosy
        {--xlsx-file= : Local .xlsx path (skips Google Sheet download)}
        {--sheet-url= : Override the default Google Sheet URL}
        {--limit= : Process only the first N linked products}
        {--sleep=600 : Delay between card requests, ms}
        {--in-stock-only : Only products currently in stock}
        {--only-missing : Only products missing a photo or description}
        {--overwrite-images : Replace existing photos (default: keep)}
        {--apply : Write changes (default: preview)}
        {--dry-run : Preview only (default)}';

    protected $description = 'Enrich ТСК Насосы products (photo/desc/specs) from their exact aqualider.by card via the price-book «aqualider» tab.';

    private const SUPPLIER_CODE = 'tsk_nasosy';
    private const IMAGE_DIR = 'img/products/tsk-nasosy';
    private const DEFAULT_SHEET_URL = 'https://docs.google.com/spreadsheets/d/1NlqXqVky2cDDAELEEKKAO0e07mpZjpkhLOQN13YWiuU/edit';

    private bool $apply;
    private array $stats = ['linked' => 0, 'candidates' => 0, 'scraped' => 0, 'images' => 0,
                            'content' => 0, 'attrs' => 0, 'url_updated' => 0, 'fetch_failed' => 0, 'errors' => 0];

    public function handle(): int
    {
        $this->apply = (bool) $this->option('apply') && ! $this->option('dry-run');
        $this->line($this->apply ? '<fg=red;options=bold>APPLY</>' : '<fg=yellow;options=bold>DRY RUN</>');

        $sid = (int) (DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id') ?? 0);
        if ($sid === 0) {
            $this->error('Supplier «' . self::SUPPLIER_CODE . '» not found — run supplier:sync-tsk-nasosy first.');
            return self::FAILURE;
        }

        // ── article → aqualider card URL (from the «aqualider» tab hyperlinks) ────
        try {
            $xlsx = $this->resolveXlsxPath();
            $map  = $this->buildAqualiderMap($xlsx);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        $this->info(sprintf('aqualider map: %d article → card links', count($map)));
        if ($map === []) {
            return self::SUCCESS;
        }

        // ── Our supplier products, joined to the map by article ──────────────────
        $rows = DB::table('supplier_products as sp')
            ->join('products as p', 'p.id', '=', 'sp.product_id')
            ->where('sp.supplier_id', $sid)
            ->whereNotNull('sp.product_id')
            ->when((bool) $this->option('in-stock-only'), fn ($q) => $q->where('sp.in_stock', true))
            ->get(['sp.id as sp_id', 'sp.supplier_article', 'sp.source_url', 'sp.in_stock',
                   'p.id as product_id', 'p.category_id', 'p.name', 'p.images', 'p.content']);

        $candidates = [];
        foreach ($rows as $r) {
            $url = $map[$this->foldArticle((string) $r->supplier_article)] ?? null;
            if ($url === null) {
                continue;
            }
            $this->stats['linked']++;
            if ($this->option('only-missing') && ! $this->needsContent($r)) {
                continue;
            }
            $r->card_url = $url;
            $candidates[] = $r;
        }
        $this->stats['candidates'] = count($candidates);
        $this->info(sprintf('Linked products: %d  |  to process: %d', $this->stats['linked'], count($candidates)));

        $limit = $this->option('limit') ? (int) $this->option('limit') : count($candidates);
        $candidates = array_slice($candidates, 0, $limit);

        if (! $this->apply) {
            $this->previewTable($candidates);
            $this->newLine();
            $this->line('Run with <fg=green>--apply</> to write photos/descriptions/specs.');
            return self::SUCCESS;
        }

        foreach ($candidates as $r) {
            try {
                $this->enrich($r);
            } catch (\Throwable $e) {
                $this->stats['errors']++;
                $this->warn("[error] {$r->supplier_article}: " . $e->getMessage());
            }
            usleep((int) $this->option('sleep') * 1000);
        }

        $this->newLine();
        $this->table(['metric', 'count'], array_map(fn ($k, $v) => [$k, $v], array_keys($this->stats), array_values($this->stats)));
        return $this->stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    // ── Enrich one product from its exact card ───────────────────────────────────

    private function enrich(object $r): void
    {
        $html = $this->fetchCard($r->card_url);
        if ($html === null) {
            $this->stats['fetch_failed']++;
            $this->warn("[fetch] {$r->supplier_article}: {$r->card_url}");
            return;
        }
        $d = $this->parseCard($html, $r->card_url);
        $this->stats['scraped']++;
        $now = now();

        $pid = (int) $r->product_id;
        $catId = (int) $r->category_id;

        // Photos — full gallery, thumbnails/placeholders filtered out
        // (skip if already present unless --overwrite-images).
        $this->stats['images'] += $this->downloadCardImages(
            $pid, $d['images'], self::IMAGE_DIR, (bool) $this->option('overwrite-images')
        );

        // Description — only fill when empty (never clobber curated text).
        if ($d['desc'] !== '' && trim((string) $r->content) === '') {
            DB::table('products')->where('id', $pid)->update([
                'content' => '<p>' . e($d['desc']) . '</p>',
                'short_description' => mb_substr($d['desc'], 0, 250),
                'updated_at' => $now,
            ]);
            $this->stats['content']++;
        }

        // Characteristics → product_attribute_values for the product's category.
        $this->stats['attrs'] += $this->writeCardSpecs($pid, $catId, $d['specs']);

        // Pin the exact card URL on the supplier link (was the generic homepage).
        if ($r->source_url !== $r->card_url) {
            DB::table('supplier_products')->where('id', $r->sp_id)
                ->update(['source_url' => $r->card_url, 'updated_at' => $now]);
            $this->stats['url_updated']++;
        }

        $this->line(sprintf('<fg=cyan>%s</> #%d %s | фото:%d specs:%d',
            $r->supplier_article, $pid, mb_substr((string) $r->name, 0, 40),
            count($d['images']), count($d['specs'])));
    }

    private function needsContent(object $r): bool
    {
        $arr = json_decode((string) $r->images, true);
        $hasImage = is_array($arr) && array_filter($arr, fn ($x) => is_string($x) && trim($x) !== '' && $x !== '[]');
        $hasContent = trim((string) $r->content) !== '';
        return ! $hasImage || ! $hasContent;
    }

    private function previewTable(array $candidates): void
    {
        $this->table(
            ['article', 'product_id', 'in_stock', 'has_img', 'has_desc', 'card_url'],
            array_map(function ($r) {
                $arr = json_decode((string) $r->images, true);
                $hasImage = is_array($arr) && array_filter($arr, fn ($x) => is_string($x) && trim($x) !== '' && $x !== '[]');
                return [
                    mb_substr((string) $r->supplier_article, 0, 18), $r->product_id,
                    $r->in_stock ? 'да' : 'нет', $hasImage ? 'да' : '—',
                    trim((string) $r->content) !== '' ? 'да' : '—', mb_substr((string) $r->card_url, 0, 60),
                ];
            }, array_slice($candidates, 0, 20))
        );
    }

    // ── XLSX «aqualider» tab → article → card URL ────────────────────────────────

    private function resolveXlsxPath(): string
    {
        $file = $this->option('xlsx-file');
        if ($file !== null) {
            if (! file_exists($file)) {
                throw new \RuntimeException("File not found: {$file}");
            }
            return $file;
        }

        $url = $this->option('sheet-url') ?: self::DEFAULT_SHEET_URL;
        if (! preg_match('#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $url, $m)) {
            throw new \RuntimeException('Invalid Google Sheets URL.');
        }
        $export = "https://docs.google.com/spreadsheets/d/{$m[1]}/export?format=xlsx";
        $ctx = stream_context_create([
            'http' => ['method' => 'GET', 'timeout' => 180, 'follow_location' => 1, 'max_redirects' => 10,
                       'header' => "User-Agent: Mozilla/5.0 (compatible; KotlovBot/1.0)\r\nAccept: */*"],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $bin = @file_get_contents($export, false, $ctx);
        if ($bin === false || $bin === '' || str_starts_with(ltrim($bin), '<')) {
            throw new \RuntimeException('Failed to download the workbook (.xlsx).');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'tsk') . '.xlsx';
        file_put_contents($tmp, $bin);
        return $tmp;
    }

    /**
     * Read the «aqualider» tab from the XLSX and return article → card URL.
     * Hyperlinks live as worksheet relationships (the CSV export drops them);
     * the article sits in column A of the same row.
     *
     * @return array<string,string>
     */
    private function buildAqualiderMap(string $xlsxPath): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($xlsxPath) !== true) {
            throw new \RuntimeException('Cannot open workbook archive.');
        }

        // Locate the «aqualider» sheet file via workbook.xml + its rels.
        $wb  = (string) $zip->getFromName('xl/workbook.xml');
        $rel = (string) $zip->getFromName('xl/_rels/workbook.xml.rels');
        $relTarget = [];
        if (preg_match_all('/<Relationship Id="([^"]+)"[^>]*Target="([^"]+)"/', $rel, $rm)) {
            foreach ($rm[1] as $k => $id) {
                $relTarget[$id] = $rm[2][$k];
            }
        }
        $sheetFile = null;
        if (preg_match_all('/<sheet [^>]*name="([^"]+)"[^>]*r:id="([^"]+)"/', $wb, $sm)) {
            foreach ($sm[1] as $k => $name) {
                if (mb_stripos(html_entity_decode($name), 'aqualider') !== false) {
                    $sheetFile = 'xl/' . ltrim($relTarget[$sm[2][$k]] ?? '', '/');
                    break;
                }
            }
        }
        if ($sheetFile === null) {
            $zip->close();
            throw new \RuntimeException('Sheet «aqualider» not found in the workbook.');
        }

        $sheetXml = (string) $zip->getFromName($sheetFile);
        $relsName = preg_replace('#worksheets/#', 'worksheets/_rels/', $sheetFile) . '.rels';
        $sheetRels = (string) $zip->getFromName($relsName);

        // rId → external hyperlink target
        $hrefById = [];
        if (preg_match_all('/<Relationship Id="([^"]+)"[^>]*Target="([^"]+)"/', $sheetRels, $hm)) {
            foreach ($hm[1] as $k => $id) {
                $hrefById[$id] = html_entity_decode($hm[2][$k]);
            }
        }

        // row number → aqualider URL (any cell hyperlink in the row pointing there)
        $rowUrl = [];
        if (preg_match_all('/<hyperlink ([^>]*)\/?>/', $sheetXml, $hl)) {
            foreach ($hl[1] as $attrs) {
                if (preg_match('/r:id="([^"]+)"/', $attrs, $idm) && preg_match('/ref="[A-Z]+(\d+)"/', $attrs, $rfm)) {
                    $target = $hrefById[$idm[1]] ?? '';
                    if (stripos($target, 'aqualider') !== false) {
                        $rowUrl[(int) $rfm[1]] = $target;
                    }
                }
            }
        }
        if ($rowUrl === []) {
            $zip->close();
            return [];
        }

        $shared = $this->sharedStrings($zip);

        // Stream the sheet; capture column-A value for each linked row.
        $map = [];
        $reader = new \XMLReader();
        $reader->XML($sheetXml);
        $curRow = 0;
        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'row') {
                $curRow = (int) $reader->getAttribute('r');
            }
            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'c' && isset($rowUrl[$curRow])) {
                $ref = (string) $reader->getAttribute('r');
                if (preg_replace('/\d+/', '', $ref) !== 'A') {
                    continue;
                }
                $type = $reader->getAttribute('t');
                $xml  = $reader->readOuterXML();
                $val  = preg_match('/<v>(.*?)<\/v>/s', $xml, $vm) ? $vm[1] : '';
                if ($type === 's') {
                    $val = $shared[(int) $val] ?? '';
                }
                $article = $this->foldArticle((string) $val);
                if ($article !== '') {
                    $map[$article] = $rowUrl[$curRow];
                }
            }
        }
        $reader->close();
        $zip->close();
        return $map;
    }

    /** @return array<int,string> shared string table */
    private function sharedStrings(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }
        $out = [];
        $reader = new \XMLReader();
        $reader->XML($xml);
        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'si') {
                $node = new \SimpleXMLElement($reader->readOuterXML());
                $t = '';
                foreach ($node->xpath('.//*[local-name()="t"]') as $tn) {
                    $t .= (string) $tn;
                }
                $out[] = $t;
            }
        }
        $reader->close();
        return $out;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Fetch REAL specs for Rusklimat products that have none, by finding the product
 * page via Serper (НС-code → rusklimat.ru / b2b.rusklimat.com / trusted source)
 * and scraping its characteristics table. Saves specs (and short_description if
 * the page provides one). Does NOT touch content — that's the rewrite step.
 *
 *   php artisan supplier:enrich-specs-rusklimat --active-only --limit=20 --dry-run
 *   php artisan supplier:enrich-specs-rusklimat --active-only --limit=20 --apply
 */
class EnrichSpecsRusklimatCommand extends Command
{
    protected $signature = 'supplier:enrich-specs-rusklimat
        {--active-only      : Only active (non-archived) products}
        {--brand=           : Filter by brand name (partial match)}
        {--limit=20         : Max products per run}
        {--offset=0         : Skip first N products}
        {--sleep=500        : Delay between products in ms}
        {--apply            : Write changes (default is preview)}
        {--dry-run          : Preview only (default)}';

    protected $description = 'Find product page via Serper and scrape real specs/short for Rusklimat products.';

    private const SUPPLIER_CODE = 'rusklimat';

    /** Pages we trust for real specs. Order = preference. */
    private const SPEC_DOMAINS = [
        'rusklimat.ru', 'b2b.rusklimat.com', 'rusklimat.by',
        'satro-paladin.com', '7-kvt.ru', 'dc-electro.ru',
    ];

    public function handle(): int
    {
        $apply  = (bool) $this->option('apply') && ! $this->option('dry-run');
        $limit  = max(1, (int) $this->option('limit'));
        $offset = max(0, (int) $this->option('offset'));

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: specs/short will be written.</>'
            : '<fg=yellow;options=bold>DRY RUN: nothing will be written.</>');

        if (! env('SERPER_API_KEY')) {
            $this->error('SERPER_API_KEY not set.');
            return self::FAILURE;
        }

        $supplierId = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id');
        if (! $supplierId) {
            $this->error('Supplier "' . self::SUPPLIER_CODE . '" not found.');
            return self::FAILURE;
        }

        $query = DB::table('products as p')
            ->join('supplier_products as sp', 'p.id', '=', 'sp.product_id')
            ->leftJoin('brands as b', 'p.brand_id', '=', 'b.id')
            ->where('sp.supplier_id', $supplierId)
            ->when($this->option('active-only'), fn ($q) => $q->where('p.is_archived', false))
            ->when($this->option('brand'), fn ($q) => $q->where('b.name', 'like', '%' . $this->option('brand') . '%'))
            ->where(function ($q) {
                $q->whereNull('p.specs')->orWhere('p.specs', '')->orWhere('p.specs', '[]')
                  ->orWhere('p.specs', '{}')->orWhere('p.specs', 'null')
                  ->orWhereRaw('(JSON_VALID(p.specs) AND JSON_LENGTH(p.specs) = 0)');
            });

        $total = (clone $query)->distinct('p.id')->count('p.id');
        $products = $query->orderBy('p.id')->offset($offset)->limit($limit)
            ->get(['p.id', 'p.name', 'p.short_description', 'b.name as brand', 'sp.supplier_article']);

        $this->newLine();
        $this->info(sprintf('Products without specs: %d (processing %d, offset %d)', $total, $products->count(), $offset));
        if ($products->isEmpty()) {
            $this->info('Nothing to do.');
            return self::SUCCESS;
        }

        $stats = ['processed' => 0, 'page_found' => 0, 'specs_saved' => 0, 'short_saved' => 0, 'not_found' => 0, 'errors' => 0];

        foreach ($products as $p) {
            $stats['processed']++;
            $brand   = trim((string) ($p->brand ?? ''));
            $article = trim((string) ($p->supplier_article ?? ''));
            $name    = trim((string) $p->name);

            $this->newLine();
            $this->line(sprintf('<fg=cyan>id=%d</> %s', $p->id, mb_substr($name, 0, 56)));

            $url = $this->findPage($article, $brand, $name);
            if ($url === null) {
                $stats['not_found']++;
                $this->line('  <fg=yellow>page not found on trusted sources</>');
                usleep((int) $this->option('sleep') * 1000);
                continue;
            }
            $stats['page_found']++;
            $this->line('  page: ' . mb_substr($url, 0, 80));

            $html = $this->fetch($url);
            if ($html === null) {
                $stats['errors']++;
                $this->line('  <fg=red>could not fetch page</>');
                usleep((int) $this->option('sleep') * 1000);
                continue;
            }

            $specs = $this->parseSpecs($html);
            $short = $this->parseShort($html);

            $this->line('  scraped specs: ' . count($specs) . ($short ? ' | short: yes' : ''));
            if ($specs !== []) {
                $sample = array_slice($specs, 0, 4, true);
                foreach ($sample as $k => $v) {
                    $this->line('    · ' . mb_substr($k, 0, 30) . ': ' . mb_substr((string) $v, 0, 30));
                }
            }

            $updates = [];
            if ($specs !== []) {
                $updates['specs'] = json_encode($specs, JSON_UNESCAPED_UNICODE);
            }
            if ($short && trim((string) $p->short_description) === '') {
                $updates['short_description'] = $short;
            }

            if ($updates === []) {
                $this->line('  — nothing extracted');
            } elseif ($apply) {
                $updates['updated_at'] = now();
                DB::table('products')->where('id', $p->id)->update($updates);
                if (isset($updates['specs'])) { $stats['specs_saved']++; }
                if (isset($updates['short_description'])) { $stats['short_saved']++; }
                $this->line('  <fg=green>saved: ' . implode(', ', array_keys(array_diff_key($updates, ['updated_at' => 1]))) . '</>');
            } else {
                if (isset($updates['specs'])) { $stats['specs_saved']++; }
                if (isset($updates['short_description'])) { $stats['short_saved']++; }
                $this->line('  <fg=blue>[dry-run] would save: ' . implode(', ', array_keys($updates)) . '</>');
            }

            usleep((int) $this->option('sleep') * 1000);
        }

        $this->newLine();
        $this->table(['metric', 'count'], array_map(fn ($k, $v) => [$k, $v], array_keys($stats), array_values($stats)));
        if ($total > $offset + $limit) {
            $this->line(sprintf("\n<fg=yellow>%d more remain. Continue with --offset=%d</>", $total - ($offset + $limit), $offset + $limit));
        }

        return self::SUCCESS;
    }

    // ── Page discovery via Serper ────────────────────────────────────────────────

    private function findPage(string $article, string $brand, string $name): ?string
    {
        $queries = array_values(array_unique(array_filter([
            $article,
            $brand !== '' && $article !== '' ? "{$brand} {$article}" : '',
            $brand !== '' && $name !== '' ? "{$brand} {$name}" : '',
            $name,
        ])));

        foreach ($queries as $q) {
            foreach ($this->serperOrganic($q) as $link) {
                $host = mb_strtolower(parse_url($link, PHP_URL_HOST) ?: '');
                foreach (self::SPEC_DOMAINS as $d) {
                    if (str_contains($host, $d) && str_contains($link, '/product/') || (str_contains($host, $d) && str_contains($link, 'catalog'))) {
                        return $link;
                    }
                }
            }
        }
        return null;
    }

    /** @return string[] organic result links */
    private function serperOrganic(string $query): array
    {
        try {
            $r = Http::timeout(20)
                ->withHeaders(['X-API-KEY' => env('SERPER_API_KEY'), 'Content-Type' => 'application/json'])
                ->post('https://google.serper.dev/search', ['q' => $query, 'num' => 10, 'gl' => 'ru', 'hl' => 'ru']);
            if (! $r->successful()) {
                return [];
            }
            return array_values(array_filter(array_map(fn ($it) => $it['link'] ?? null, $r->json('organic') ?? [])));
        } catch (\Throwable) {
            return [];
        }
    }

    // ── Page fetch & parse ───────────────────────────────────────────────────────

    private function fetch(string $url): ?string
    {
        try {
            $r = Http::timeout(25)
                ->withHeaders([
                    'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36',
                    'Accept-Language' => 'ru-RU,ru;q=0.9',
                ])
                ->withOptions(['verify' => false])
                ->get($url);
            return $r->successful() ? $r->body() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Extract a [name => value] specs map using several common patterns. */
    private function parseSpecs(string $html): array
    {
        $specs = [];

        // 1. span.param-name / span.param-value (rusklimat.by)
        if (preg_match_all('/<span[^>]+class="[^"]*param-name[^"]*"[^>]*>\s*(.*?)\s*<\/span>.*?<span[^>]+class="[^"]*param-value[^"]*"[^>]*>\s*(.*?)\s*<\/span>/isu', $html, $m)) {
            $this->collect($specs, $m[1], $m[2]);
        }

        // 2. table rows: <tr> <td|th>name</td> <td>value</td> </tr>
        if (preg_match_all('/<tr[^>]*>\s*<t[dh][^>]*>(.*?)<\/t[dh]>\s*<t[dh][^>]*>(.*?)<\/t[dh]>\s*<\/tr>/isu', $html, $m)) {
            $this->collect($specs, $m[1], $m[2]);
        }

        // 3. dt/dd pairs
        if (preg_match_all('/<dt[^>]*>(.*?)<\/dt>\s*<dd[^>]*>(.*?)<\/dd>/isu', $html, $m)) {
            $this->collect($specs, $m[1], $m[2]);
        }

        // 4. JSON-LD additionalProperty (schema.org Product)
        if (preg_match_all('/<script[^>]+type="application\/ld\+json"[^>]*>(.*?)<\/script>/isu', $html, $blocks)) {
            foreach ($blocks[1] as $json) {
                $data = json_decode(trim($json), true);
                foreach ($this->findAdditionalProperties($data) as $prop) {
                    $k = trim((string) ($prop['name'] ?? ''));
                    $v = trim((string) ($prop['value'] ?? ''));
                    if ($k !== '' && $v !== '' && ! isset($specs[$k])) {
                        $specs[$k] = $v;
                    }
                }
            }
        }

        return $specs;
    }

    /** Non-spec keys that table/gallery headers leak in. */
    private const SPEC_KEY_DENY = ['фото', 'наименование', 'код товара', 'артикул', 'цена', 'кол-во', 'количество', 'бренд', 'производитель', 'описание'];

    private function collect(array &$specs, array $keys, array $vals): void
    {
        for ($i = 0, $n = count($keys); $i < $n; $i++) {
            $k = trim(preg_replace('/\s+/u', ' ', strip_tags($keys[$i])) ?? '');
            $v = trim(preg_replace('/\s+/u', ' ', strip_tags($vals[$i] ?? '')) ?? '');

            if ($k === '' || $v === '' || $v === '—') {
                continue;
            }
            if (mb_strlen($k) > 50 || mb_strlen($v) > 80) {
                continue; // headers / prose, not a spec pair
            }
            if (str_word_count(strip_tags($v), 0, 'абвгдеёжзийклмнопрстуфхцчшщъыьэюя') > 8) {
                continue; // value looks like a sentence, not a spec
            }
            $kl = mb_strtolower($k);
            foreach (self::SPEC_KEY_DENY as $deny) {
                if (str_starts_with($kl, $deny)) {
                    continue 2;
                }
            }
            if (! isset($specs[$k])) {
                $specs[$k] = $v;
            }
        }
    }

    private function findAdditionalProperties($data): array
    {
        if (! is_array($data)) {
            return [];
        }
        if (isset($data['additionalProperty']) && is_array($data['additionalProperty'])) {
            return $data['additionalProperty'];
        }
        $out = [];
        foreach ($data as $v) {
            if (is_array($v)) {
                $out = array_merge($out, $this->findAdditionalProperties($v));
            }
        }
        return $out;
    }

    private function parseShort(string $html): ?string
    {
        if (preg_match('/<meta[^>]+property="og:description"[^>]+content="([^"]+)"/i', $html, $m) ||
            preg_match('/<meta[^>]+content="([^"]+)"[^>]+property="og:description"/i', $html, $m)) {
            $t = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
            return mb_strlen($t) > 15 ? $t : null;
        }
        return null;
    }
}

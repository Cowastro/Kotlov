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
        {--id=*             : Process only selected product IDs}
        {--limit=20         : Max products per run}
        {--offset=0         : Skip first N products}
        {--sleep=500        : Delay between products in ms}
        {--show-keys        : Just print scraped key:value pairs (vocabulary gathering, no writes)}
        {--debug-source     : Print fetched HTML diagnostics for parser debugging}
        {--force            : Re-scrape products even when specs are already filled}
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
        $force  = (bool) $this->option('force');
        $limit  = max(1, (int) $this->option('limit'));
        $offset = max(0, (int) $this->option('offset'));
        $ids = collect($this->option('id'))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->values()
            ->all();

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
            ->when($ids !== [], fn ($q) => $q->whereIn('p.id', $ids))
            ->when(! $force, fn ($query) => $query->where(function ($q) {
                $q->whereNull('p.specs')->orWhere('p.specs', '')->orWhere('p.specs', '[]')
                  ->orWhere('p.specs', '{}')->orWhere('p.specs', 'null')
                  ->orWhereRaw('(JSON_VALID(p.specs) AND JSON_LENGTH(p.specs) = 0)');
            }));

        $total = (clone $query)->distinct('p.id')->count('p.id');
        $products = $query->orderBy('p.id')->offset($offset)->limit($limit)
            ->get(['p.id', 'p.name', 'p.short_description', 'b.name as brand', 'sp.supplier_article']);

        $this->newLine();
        $this->info(sprintf($force ? 'Products to re-scrape: %d (processing %d, offset %d)' : 'Products without specs: %d (processing %d, offset %d)', $total, $products->count(), $offset));
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

            if ($this->option('debug-source')) {
                $this->line('  html bytes: ' . strlen($html));
                foreach (['additionalProperty', 'param-name', 'param-value', 'characteristics', 'og:description'] as $marker) {
                    $this->line('  marker ' . $marker . ': ' . (str_contains($html, $marker) ? 'yes' : 'no'));
                }
            }

            $specs = $this->parseSpecs($html);
            $short = $this->parseShort($html);

            // Vocabulary mode: just dump every scraped key:value, no writes.
            if ($this->option('show-keys')) {
                $this->line('  cat=' . (DB::table('products')->where('id', $p->id)->value('category_id'))
                    . ' scraped ' . count($specs) . ':');
                foreach ($specs as $k => $v) {
                    $this->line('    · ' . $k . ' = ' . $v);
                }
                usleep((int) $this->option('sleep') * 1000);
                continue;
            }

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
        $collect = function (array $queries): array {
            $out = [];
            foreach ($queries as $q) {
                foreach ($this->serperOrganic($q) as $link) {
                    $host = mb_strtolower(parse_url($link, PHP_URL_HOST) ?: '');
                    foreach (self::SPEC_DOMAINS as $d) {
                        if (str_contains($host, $d)) {
                            $out[] = $link;
                            break;
                        }
                    }
                }
            }
            return $out;
        };

        $match = fn ($l) => $this->pageMatchesProduct($l, $brand, $name);

        // 1) Force the retail site rusklimat.ru — its /product/ pages expose specs.
        $primary = array_values(array_filter([
            $article !== '' ? "{$article} site:rusklimat.ru" : '',
            $name !== '' ? "{$name} site:rusklimat.ru" : '',
        ]));
        $candidates = array_values(array_filter($collect($primary), $match));
        $best = $this->bestProductPage($candidates);
        if ($best !== null) {
            return $best;
        }

        // 2) Fallback: plain queries across trusted sources.
        $fallback = array_values(array_unique(array_filter([
            $article,
            $brand !== '' && $article !== '' ? "{$brand} {$article}" : '',
            $brand !== '' && $name !== '' ? "{$brand} {$name}" : '',
            $name,
        ])));
        $candidates = array_merge($candidates, array_values(array_filter($collect($fallback), $match)));

        if ($candidates === []) {
            return null;
        }
        usort($candidates, fn ($a, $b) => $this->pageRank($a) <=> $this->pageRank($b));
        return $candidates[0];
    }

    /**
     * Guard against wrong-product pages: the URL must mention the brand and,
     * if the name has a model code (token with a digit), that code too.
     */
    private function pageMatchesProduct(string $url, string $brand, string $name): bool
    {
        $u = mb_strtolower($url);

        $brandOk = true;
        $bWord = preg_split('/\s+/', trim($this->translitLower($brand)))[0] ?? '';
        if ($bWord !== '' && mb_strlen($bWord) >= 3) {
            $brandOk = str_contains($u, $bWord);
        }

        $tokens = [];
        preg_match_all('/[a-z0-9]{2,}/', $this->translitLower($name), $mm);
        foreach ($mm[0] as $t) {
            if (mb_strlen($t) >= 3 && preg_match('/\d/', $t)) {
                $tokens[] = $t;
            }
        }
        $modelOk = $tokens === [];
        foreach ($tokens as $t) {
            if (str_contains($u, $t)) {
                $modelOk = true;
                break;
            }
        }

        return $brandOk && $modelOk;
    }

    private function translitLower(string $s): string
    {
        $map = [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i',
            'й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t',
            'у'=>'u','ф'=>'f','х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'shch','ъ'=>'','ы'=>'y','ь'=>'',
            'э'=>'e','ю'=>'yu','я'=>'ya',
        ];
        return strtr(mb_strtolower($s), $map);
    }

    /** Return the best rusklimat product page (rank ≤ 2) from candidates, or null. */
    private function bestProductPage(array $candidates): ?string
    {
        $candidates = array_values(array_filter($candidates, fn ($l) => $this->pageRank($l) <= 2));
        if ($candidates === []) {
            return null;
        }
        usort($candidates, fn ($a, $b) => $this->pageRank($a) <=> $this->pageRank($b));
        return $candidates[0];
    }

    private function pageRank(string $link): int
    {
        $host    = mb_strtolower(parse_url($link, PHP_URL_HOST) ?: '');
        $product = str_contains($link, '/product/');
        return match (true) {
            str_contains($host, 'rusklimat.ru') && $product => 0,
            str_contains($host, 'rusklimat.by') && $product => 1,
            $product                                        => 2,
            str_contains($host, 'rusklimat.ru')             => 3,
            default                                         => 5,
        };
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
        if (preg_match_all('/<tr[^>]*>(.*?)<\/tr>/isu', $html, $rows)) {
            foreach ($rows[1] as $rowHtml) {
                if (preg_match_all('/<t[dh][^>]*>(.*?)<\/t[dh]>/isu', $rowHtml, $cells) && count($cells[1]) >= 2) {
                    $this->addSpecFromTableCells($specs, $cells[1]);
                }
            }
        }

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
        foreach ($this->additionalPropertiesFromHtml($html) as $k => $v) {
            if ($k !== '' && $v !== '' && ! isset($specs[$k])) {
                $specs[$k] = $v;
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

    private function addSpecFromTableCells(array &$specs, array $cells): void
    {
        $texts = array_values(array_filter(array_map(
            fn ($cell): string => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $cell), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? ''),
            $cells
        ), fn (string $text): bool => $text !== ''));

        if (count($texts) < 2 || isset($specs[$texts[0]])) {
            return;
        }

        $value = $this->bestRusklimatSpecValue(array_slice($texts, 1));
        if ($value !== null) {
            $specs[$texts[0]] = $value;
        }
    }

    private function bestRusklimatSpecValue(array $candidates): ?string
    {
        $best = null;
        $bestScore = -1;

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '' || $candidate === 'â€”') {
                continue;
            }

            $unitOnly = $this->isRusklimatUnitOnlyValue($candidate);
            $score = 0;
            $score += preg_match('/\d/u', $candidate) ? 100 : 0;
            $score += $unitOnly ? -100 : 25;
            $score += mb_strlen($candidate) > 2 ? 5 : 0;

            if ($score > $bestScore) {
                $best = $candidate;
                $bestScore = $score;
            }
        }

        return $best !== null && ! $this->isRusklimatUnitOnlyValue($best) ? $best : null;
    }

    private function isRusklimatUnitOnlyValue(string $value): bool
    {
        $value = mb_strtolower(trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $value = str_replace(['°', '℃', '²', '³', 'кв.', 'кв '], ['', 'c', '2', '3', '', ''], $value);
        $value = preg_replace('/[.,;:(){}\[\]\/\\\\|]+/u', ' ', $value) ?? $value;
        $tokens = preg_split('/\s+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($tokens === []) {
            return true;
        }

        $units = ['bar', 'c', 'cm', 'g', 'kg', 'kw', 'kvt', 'l', 'm', 'm2', 'm3', 'mm', 'mpa', 'pa', 'v', 'w', 'бар', 'в', 'вт', 'г', 'дюйм', 'квт', 'кг', 'л', 'литр', 'м', 'м2', 'м3', 'мес', 'месяц', 'мин', 'мм', 'мпа', 'па', 'см', 'час', 'шт'];

        foreach ($tokens as $token) {
            if (! in_array($token, $units, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Rusklimat sometimes emits Product JSON-LD or Nuxt payload fragments that
     * are valid enough for regex extraction but not for json_decode as a whole.
     *
     * @return array<string, string>
     */
    private function additionalPropertiesFromHtml(string $html): array
    {
        $specs = [];

        if (! preg_match_all('/"additionalProperty"\s*:\s*\[(.*?)\]/isu', $html, $blocks)) {
            return [];
        }

        foreach ($blocks[1] as $block) {
            if (! preg_match_all('/"name"\s*:\s*"((?:\\\\.|[^"\\\\])*)"\s*,\s*"value"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/isu', $block, $pairs, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($pairs as $pair) {
                $key = $this->decodeJsonStringFragment($pair[1]);
                $value = $this->decodeJsonStringFragment($pair[2]);
                if ($key !== '' && $value !== '' && ! $this->isRusklimatUnitOnlyValue($value)) {
                    $specs[$key] = $value;
                }
            }
        }

        return $specs;
    }

    private function decodeJsonStringFragment(string $value): string
    {
        $decoded = json_decode('"' . $value . '"');
        if (is_string($decoded)) {
            return trim($decoded);
        }

        return trim(stripslashes($value));
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

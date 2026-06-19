<?php

namespace App\Console\Commands;

use App\Services\AiContentEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Universal teplodvor.by enrichment — photos + specs + AI for ANY product.
 *
 * Step 1 (once):  build slug index from teplodvor.by sitemaps:
 *   php artisan supplier:enrich-teplodvor --build-index
 *
 * Step 2 (regularly):  enrich products that are missing photos or specs:
 *   php artisan supplier:enrich-teplodvor --apply
 *   php artisan supplier:enrich-teplodvor --apply --only-ai
 *   php artisan supplier:enrich-teplodvor --apply --product=1234
 */
class EnrichTeplodvorCommand extends Command
{
    protected $signature = 'supplier:enrich-teplodvor
        {--build-index     : Re-crawl sitemaps and rebuild slug index}
        {--apply           : Write changes to DB (default: dry-run)}
        {--skip-ai         : Skip AI description generation}
        {--only-ai         : Only (re)generate AI texts, skip photos and specs}
        {--overwrite       : Replace images even if product already has photos}
        {--product=        : Process single product by ID}
        {--limit=          : Max products to enrich in this run}
        {--min-score=0.75  : Minimum token match score (0–1)}
        {--sleep=800       : Delay between HTTP requests (ms)}
        {--brand=          : Filter by brand name (e.g. "Kermi", "Grundfos")}
        {--preview         : In dry-run, fetch page and show specs (slow)}';


    protected $description = 'Enrich any product with photos, specs and AI from teplodvor.by';

    private const BASE       = 'https://www.teplodvor.by';
    private const INDEX_FILE = 'teplodvor_index.json';
    private const IMAGE_DIR  = 'img/products/teplodvor';

    private bool $apply;
    private int  $sleep;
    private array $stats = [
        'scanned'  => 0,
        'matched'  => 0,
        'enriched' => 0,
        'images'   => 0,
        'specs'    => 0,
        'ai_done'  => 0,
        'no_match' => 0,
        'errors'   => 0,
    ];

    // ── Entry point ───────────────────────────────────────────────────────────────

    public function handle(): int
    {
        $this->apply = (bool) $this->option('apply');
        $this->sleep = max(300, (int) $this->option('sleep'));

        $this->line($this->apply
            ? '<fg=red;options=bold>APPLY — database will be updated.</>'
            : '<fg=yellow;options=bold>DRY RUN — no changes will be written.</>');

        // ── Phase 1: index ──────────────────────────────────────────────────────
        $indexPath = storage_path(self::INDEX_FILE);

        if ($this->option('build-index') || ! file_exists($indexPath)) {
            $this->buildIndex($indexPath);
        }

        $index = json_decode((string) file_get_contents($indexPath), true) ?? [];
        $this->info(sprintf('Slug index: %d teplodvor.by product URLs', count($index)));

        if (empty($index)) {
            $this->error('Index is empty. Run with --build-index first.');
            return self::FAILURE;
        }

        // ── Phase 2: products ───────────────────────────────────────────────────
        $onlyAi   = (bool) $this->option('only-ai');
        $overwrite = (bool) $this->option('overwrite');
        $minScore  = (float) $this->option('min-score');
        $limit     = $this->option('limit') ? (int) $this->option('limit') : PHP_INT_MAX;

        $query = DB::table('products')
            ->where('is_archived', false)
            ->whereNotNull('slug')
            ->where('slug', '!=', '');

        if ($this->option('brand')) {
            $brandId = DB::table('brands')
                ->where('name', 'like', '%' . $this->option('brand') . '%')
                ->value('id');
            if (! $brandId) {
                $this->error('Brand not found: ' . $this->option('brand'));
                return self::FAILURE;
            }
            $query->where('brand_id', $brandId);
            $this->info('Brand filter: ' . $this->option('brand') . " (id={$brandId})");
        }

        if ($this->option('product')) {
            $query->where('id', (int) $this->option('product'));
        } elseif ($onlyAi) {
            $query->where(function ($q) {
                $q->whereNull('content')->orWhere('content', '');
            });
        } elseif (! $overwrite) {
            // Default: products missing photos OR specs
            $query->where(function ($q) {
                $q->whereNull('images')
                    ->orWhere('images', '')
                    ->orWhere('images', '[]')
                    ->orWhere(function ($q2) {
                        $q2->whereNull('specs')->orWhere('specs', '')->orWhere('specs', '{}');
                    });
            });
        }

        $products = $query->get(['id', 'name', 'slug', 'brand_id', 'images', 'specs', 'content']);
        $this->info(sprintf('Products to process: %d', count($products)));

        $brandNames = DB::table('brands')->pluck('name', 'id')->toArray();

        $processed = 0;
        foreach ($products as $product) {
            if ($this->stats['scanned'] >= $limit) {
                break;
            }
            $this->stats['scanned']++;

            $url = $this->findMatch((string) $product->slug, $index, $minScore);

            if ($url === null) {
                $this->line(sprintf('  [NO MATCH] %s', mb_substr($product->name, 0, 70)));
                $this->stats['no_match']++;
                continue;
            }

            $this->stats['matched']++;
            $this->line(sprintf('  [MATCH] %s', mb_substr($product->name, 0, 60)));
            $this->line('    → ' . $url);

            // In dry-run without --preview: just show the matched URL, no HTTP fetch
            if (! $this->apply && ! $this->option('preview')) {
                $this->stats['enriched']++;
                continue;
            }

            try {
                $brandName = (string) ($brandNames[$product->brand_id] ?? '');
                $hasImages = ! empty(json_decode((string) ($product->images ?? '[]'), true));
                $this->enrichProduct((int) $product->id, $url, $brandName, $hasImages, $onlyAi, $overwrite);
            } catch (\Throwable $e) {
                $this->stats['errors']++;
                $this->warn('    ERROR: ' . $e->getMessage());
            }

            usleep($this->sleep * 1000);
        }

        $this->newLine();
        $this->table(
            ['metric', 'count'],
            array_map(fn ($k, $v) => [$k, $v], array_keys($this->stats), array_values($this->stats))
        );

        return $this->stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    // ── Index building ────────────────────────────────────────────────────────────

    private function buildIndex(string $path): void
    {
        $this->info('Building teplodvor.by slug index from sitemaps…');
        $index = [];

        for ($n = 1; $n <= 6; $n++) {
            $sitemapUrl = self::BASE . "/map/sitemap/{$n}/";
            $this->line("  sitemap {$n}: {$sitemapUrl}");

            $xml = $this->fetch($sitemapUrl);
            if ($xml === null) {
                $this->warn("  sitemap {$n}: failed to fetch");
                continue;
            }

            // 3+ nested path segments under /shop/ = product page (not a category)
            preg_match_all(
                '|<loc>(https://www\.teplodvor\.by(/shop/[^/]+/[^/]+/[^/]+/[^<]*/?))</loc>|',
                $xml, $m, PREG_SET_ORDER
            );

            $added = 0;
            foreach ($m as $row) {
                $url  = rtrim($row[1], '/');
                $slug = basename(rtrim($row[2], '/'));
                if ($slug !== '' && ! isset($index[$slug])) {
                    $index[$slug] = $url;
                    $added++;
                }
            }
            $this->line("  sitemap {$n}: {$added} product URLs added");
            usleep(600_000);
        }

        file_put_contents($path, json_encode($index, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->info(sprintf('Index saved: %d URLs → %s', count($index), $path));
    }

    // ── Matching ──────────────────────────────────────────────────────────────────

    // Function words and generic category words that inflate match scores without adding specificity.
    private const SLUG_STOPWORDS = [
        // Russian prepositions / conjunctions
        'bez', 'dlya', 'so', 'na', 'po', 'iz', 'ot', 'ob', 'pri', 'ili', 'ne', 'do', 'ko',
        // Generic product category nouns (pump, station, valve, etc.)
        'nasos', 'nasosnaya', 'stantsiya', 'klapan', 'schetchik',
    ];

    private function findMatch(string $ourSlug, array $index, float $minScore): ?string
    {
        // Tokenise: include single-digit numbers; drop single non-digit letters and stopwords.
        $ourTokens = array_values(array_filter(
            explode('-', strtolower($ourSlug)),
            fn ($t) => (strlen($t) >= 2 || ctype_digit($t))
                && ! in_array($t, self::SLUG_STOPWORDS, true)
        ));

        if (count($ourTokens) < 3) {
            return null;
        }

        // Weighted scoring: token weight = character length.
        // Long tokens (model codes, brand names) matter more than short ones.
        $totalWeight = (int) array_sum(array_map('strlen', $ourTokens));
        if ($totalWeight === 0) {
            return null;
        }

        $bestUrl   = null;
        $bestScore = 0.0;

        // Collect multi-digit numeric tokens (e.g. "25", "300") — these must ALL match exactly.
        $requiredNumerics = array_filter($ourTokens, fn ($t) => strlen($t) >= 2 && ctype_digit($t));

        foreach ($index as $tSlug => $url) {
            $tTokens = explode('-', $tSlug);

            // Hard pre-filter: every multi-digit number must be an exact segment.
            // Prevents "ups-25-70" from matching "ups-25-55" because both share brand+25.
            $numericOk = true;
            foreach ($requiredNumerics as $num) {
                if (! in_array($num, $tTokens, true)) {
                    $numericOk = false;
                    break;
                }
            }
            if (! $numericOk) {
                continue;
            }

            $matchedWeight = 0;
            foreach ($ourTokens as $token) {
                // Numeric tokens: exact boundary match — "35" must not match inside "6035".
                // Alpha tokens: substring match handles transliteration variants ("012sn" ⊇ "012").
                $hit = ctype_digit($token)
                    ? in_array($token, $tTokens, true)
                    : str_contains($tSlug, $token);
                if ($hit) {
                    $matchedWeight += strlen($token);
                }
            }
            $score = $matchedWeight / $totalWeight;
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestUrl   = $url;
            }
        }

        return $bestScore >= $minScore ? $bestUrl : null;
    }

    // ── Enrichment ────────────────────────────────────────────────────────────────

    private function enrichProduct(
        int $pid, string $url, string $brandName, bool $hasImages,
        bool $onlyAi, bool $overwrite
    ): void {
        $html = $this->fetch($url);
        if ($html === null) {
            $this->warn('    Failed to fetch page');
            $this->stats['errors']++;
            return;
        }

        $card = $this->parsePage($html);

        if (! $this->apply) {
            $this->line(sprintf('    name on page: %s', mb_substr($card['name'], 0, 60)));
            $this->line(sprintf('    images: %d, specs: %d', count($card['images']), count($card['specs'])));
            foreach (array_slice($card['specs'], 0, 5, true) as $k => $v) {
                $this->line("    · {$k}: {$v}");
            }
            return;
        }

        $now = now();

        if (! $onlyAi) {
            $written = $this->downloadImages($pid, $card['images'], $hasImages, $overwrite);
            $this->stats['images'] += $written;

            if (! empty($card['specs'])) {
                $row      = DB::table('products')->where('id', $pid)->value('specs');
                $existing = is_string($row) ? (json_decode($row, true) ?? []) : [];
                $merged   = array_merge($card['specs'], $existing); // existing wins on conflict
                DB::table('products')->where('id', $pid)->update([
                    'specs' => json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
                $this->stats['specs']++;
                $this->line('    specs saved: ' . count($merged));
            }
        }

        if (! $this->option('skip-ai')) {
            $existing = (string) DB::table('products')->where('id', $pid)->value('content');
            if ($onlyAi || trim($existing) === '') {
                $this->generateAiContent($pid, $card, $brandName, $now);
            }
        }

        DB::table('products')->where('id', $pid)->update(['updated_at' => $now]);
        $this->stats['enriched']++;
    }

    // ── Page parsing ──────────────────────────────────────────────────────────────

    private function parsePage(string $html): array
    {
        $name = $this->cleanText(preg_match('/<h1[^>]*>([\s\S]*?)<\/h1>/u', $html, $m) ? $m[1] : '');

        // Full-size images: prefer /large/, fall back to /product/
        preg_match_all('/userfls\/shop\/large\/([\d\/]+[^"\']+\.(?:jpg|jpeg|png|webp))/iu', $html, $m);
        $images = [];
        foreach (array_unique($m[1] ?? []) as $path) {
            $images[] = self::BASE . '/userfls/shop/large/' . $path;
        }
        if (empty($images)) {
            preg_match_all('/userfls\/shop\/product\/([\d\/]+[^"\']+\.(?:jpg|jpeg|png|webp))/iu', $html, $m);
            foreach (array_unique($m[1] ?? []) as $path) {
                $images[] = self::BASE . '/userfls/shop/product/' . $path;
            }
        }

        // Specs: <td class="parametr"><span>name</span></td><td>value</td>
        $specs = [];
        preg_match_all(
            '/<td[^>]*class="parametr"[^>]*>\s*<span[^>]*>([\s\S]*?)<\/span>\s*<\/td>\s*<td[^>]*>([\s\S]*?)<\/td>/u',
            $html, $m, PREG_SET_ORDER
        );
        foreach ($m as $row) {
            $k = $this->cleanText($row[1]);
            $v = $this->cleanText($row[2]);
            if ($k !== '' && $v !== '' && $k !== $v) {
                $specs[$k] = $v;
            }
        }
        if (empty($specs)) {
            preg_match_all(
                '/<tr[^>]*>\s*<td[^>]*>([\s\S]*?)<\/td>\s*<td[^>]*>([\s\S]*?)<\/td>/u',
                $html, $m, PREG_SET_ORDER
            );
            foreach ($m as $row) {
                $k = $this->cleanText($row[1]);
                $v = $this->cleanText($row[2]);
                if ($k !== '' && $v !== '' && $k !== $v && mb_strlen($k) <= 80) {
                    $specs[$k] = $v;
                }
            }
        }

        // Description
        $desc = '';
        if (preg_match('/<(?:section|div)[^>]*id=["\']description["\'][^>]*>([\s\S]*?)<\/(?:section|div)>/u', $html, $m)) {
            $raw  = (string) preg_replace('/<(script|style)\b[\s\S]*?<\/\1>/iu', '', $m[1]);
            $desc = trim(strip_tags(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            $desc = (string) preg_replace('/\s{2,}/u', ' ', $desc);
        }

        return compact('name', 'images', 'specs', 'desc');
    }

    private function cleanText(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    // ── Images ────────────────────────────────────────────────────────────────────

    private function downloadImages(int $pid, array $urls, bool $hasImages, bool $overwrite): int
    {
        if ($hasImages && ! $overwrite) {
            $this->line('    skip images (already has photos)');
            return 0;
        }
        if (empty($urls)) {
            $this->line('    no images found on page');
            return 0;
        }

        $dir = public_path(self::IMAGE_DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $saved   = [];
        $hashes  = [];
        $written = 0;

        foreach (array_slice($urls, 0, 6) as $imgUrl) {
            $body = $this->fetch($imgUrl, true);
            if ($body === null || strlen($body) < 3000) {
                continue;
            }
            $size = @getimagesizefromstring($body);
            if (! $size || $size[0] < 200 || $size[1] < 200) {
                continue;
            }
            $hash = md5($body);
            if (isset($hashes[$hash])) {
                continue;
            }
            $hashes[$hash] = true;

            $ext  = match ($size['mime'] ?? '') {
                'image/png'  => 'png',
                'image/webp' => 'webp',
                default      => 'jpg',
            };
            $file = $pid . '_' . $written . '.' . $ext;
            file_put_contents("{$dir}/{$file}", $body);
            $saved[] = self::IMAGE_DIR . '/' . $file;
            $written++;
            usleep(200_000);
        }

        if (! empty($saved)) {
            DB::table('products')->where('id', $pid)->update([
                'images'     => json_encode(array_values($saved), JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
            $this->line("    saved {$written} image(s)");
        }

        return $written;
    }

    // ── AI content ────────────────────────────────────────────────────────────────

    private function generateAiContent(int $pid, array $card, string $brandName, $now): void
    {
        $enricher = app(AiContentEnricher::class);
        if (! $enricher->isAvailable()) {
            $this->warn('    AI enricher not available');
            return;
        }

        $product = DB::table('products')->where('id', $pid)->first(['name', 'specs']);
        if (! $product) {
            return;
        }

        $existingSpecs = json_decode((string) ($product->specs ?? '{}'), true) ?: [];
        $mergedSpecs   = array_merge($existingSpecs, $card['specs']);

        $aiContent = $enricher->enrich(
            (string) $product->name,
            $brandName,
            $card['desc'] ?: null,
            $mergedSpecs
        );

        if ($aiContent !== null && trim(strip_tags($aiContent)) !== '') {
            $short = $enricher->shortDescription(
                (string) $product->name,
                $brandName,
                $mergedSpecs
            ) ?: mb_substr(trim(strip_tags($aiContent)), 0, 240);

            DB::table('products')->where('id', $pid)->update([
                'content'           => strip_tags($aiContent, '<p><ul><li><strong>'),
                'short_description' => mb_substr(trim($short), 0, 240),
                'meta_description'  => mb_substr(trim($short), 0, 250),
                'updated_at'        => $now,
            ]);
            $this->stats['ai_done']++;
            $this->line('    AI content generated');
        }
    }

    // ── HTTP ──────────────────────────────────────────────────────────────────────

    private function fetch(string $url, bool $binary = false): ?string
    {
        $ctx = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'timeout'         => 30,
                'follow_location' => 1,
                'max_redirects'   => 5,
                'header'          => implode("\r\n", [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept: text/html,application/xhtml+xml,*/*;q=0.9',
                    'Accept-Language: ru-RU,ru;q=0.9',
                    'Referer: https://www.teplodvor.by/',
                ]),
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);

        $body = @file_get_contents($url, false, $ctx);
        if ($body === false || (! $binary && strlen($body) < 500)) {
            return null;
        }
        return $body;
    }
}

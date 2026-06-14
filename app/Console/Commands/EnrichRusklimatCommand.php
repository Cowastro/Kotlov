<?php

namespace App\Console\Commands;

use App\Services\AiContentEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Enriches products imported from Rusklimat with:
 *  - Specs (scraped param-name/param-value from rusklimat.by product pages)
 *  - Short description (og:description from rusklimat.by)
 *  - Main image (og:image, downloaded to public/img/products/rusklimat/)
 *  - Long description (AI-generated using AiContentEnricher)
 *
 * Strategy:
 *  1. Download product URLs from rusklimat.by sitemap (540 known pages)
 *  2. Build slug index and try to match each product
 *  3. Scrape matched page: specs, short_desc, image
 *  4. Use AI to write full description (if ANTHROPIC_API_KEY / AI_API_KEY configured)
 *
 * Usage:
 *   php artisan supplier:enrich-rusklimat --limit=50
 *   php artisan supplier:enrich-rusklimat --force --skip-images
 *   php artisan supplier:enrich-rusklimat --ai-only --limit=200
 */
class EnrichRusklimatCommand extends Command
{
    protected $signature = 'supplier:enrich-rusklimat
        {--limit=50          : Max products to process per run}
        {--offset=0          : Skip first N products (for batching)}
        {--brand=            : Filter by brand name (partial match, e.g. --brand=Ballu)}
        {--category=         : Filter by category id (e.g. --category=306)}
        {--force             : Overwrite existing content / specs / images}
        {--skip-images       : Do not download images}
        {--skip-content      : Do not generate AI description}
        {--skip-specs        : Do not update specs}
        {--ai-only           : Skip web scraping, use AI for description only}
        {--dry-run           : Preview only, no DB writes}';

    protected $description = 'Enrich Rusklimat products: scrape specs/images from rusklimat.by + AI description';

    private const SUPPLIER_CODE  = 'rusklimat';
    private const IMAGE_DIR      = 'img/products/rusklimat';
    private const SITEMAP_URL    = 'https://rusklimat.by/sitemap.xml';
    private const REQUEST_DELAY  = 1_500_000; // 1.5s between requests

    private AiContentEnricher $ai;
    private bool $dryRun;
    private array $sitemapIndex = [];   // slug => full URL
    private array $stats = [
        'processed' => 0,
        'scraped'   => 0,
        'ai_used'   => 0,
        'images'    => 0,
        'specs'     => 0,
        'skipped'   => 0,
        'errors'    => 0,
    ];

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $this->ai     = new AiContentEnricher();

        if ($this->dryRun) {
            $this->warn('[dry-run] No changes will be written.');
        }

        // ── Resolve supplier ─────────────────────────────────────────────────────
        $supplierId = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id');
        if (! $supplierId) {
            $this->error('Supplier "' . self::SUPPLIER_CODE . '" not found.');
            return self::FAILURE;
        }

        // ── Load sitemap index ────────────────────────────────────────────────────
        if (! $this->option('ai-only')) {
            $this->info('Loading rusklimat.by sitemap…');
            $this->buildSitemapIndex();
            $this->info(sprintf('Sitemap: %d product URLs loaded.', count($this->sitemapIndex)));
        }

        // ── Load products to enrich ───────────────────────────────────────────────
        $limit  = (int) $this->option('limit');
        $offset = (int) $this->option('offset');
        $force  = (bool) $this->option('force');

        $query = DB::table('products as p')
            ->join('supplier_products as sp', 'p.id', '=', 'sp.product_id')
            ->where('sp.supplier_id', $supplierId)
            ->where('p.is_archived', false)
            ->select('p.id', 'p.name', 'p.slug', 'p.content', 'p.specs',
                     'p.images', 'p.short_description', 'sp.supplier_article', 'sp.raw');

        if ($brandFilter = $this->option('brand')) {
            $query->join('brands as br', 'p.brand_id', '=', 'br.id')
                  ->where('br.name', 'like', '%' . $brandFilter . '%');
        }

        if ($categoryFilter = $this->option('category')) {
            $query->where('p.category_id', (int) $categoryFilter);
        }

        if (! $force) {
            $query->where(function ($q) {
                $q->whereNull('p.content')->orWhere('p.content', '')
                  ->orWhereNull('p.specs')->orWhere('p.specs', '[]')
                  ->orWhere('p.specs', '{}')->orWhere('p.specs', 'null')
                  ->orWhereNull('p.images')->orWhere('p.images', '[]');
            });
        }

        $total    = (clone $query)->count();
        $products = $query->orderBy('p.id')->offset($offset)->limit($limit)->get();

        $this->newLine();
        $this->info(sprintf(
            'Products to enrich: %d (processing %d, offset %d%s%s%s)',
            $total, $products->count(), $offset,
            $force ? ', --force' : '',
            $brandFilter ? ', brand=' . $brandFilter : '',
            $categoryFilter ? ', category=' . $categoryFilter : ''
        ));

        if ($products->isEmpty()) {
            $this->info('Nothing to do — all products already enriched.');
            return self::SUCCESS;
        }

        // ── Image directory ───────────────────────────────────────────────────────
        $imgDir = public_path(self::IMAGE_DIR);
        if (! $this->dryRun && ! is_dir($imgDir)) {
            mkdir($imgDir, 0755, true);
        }

        $this->newLine();

        // ── Process each product ─────────────────────────────────────────────────
        foreach ($products as $product) {
            $this->stats['processed']++;
            $rawData  = $product->raw ? json_decode($product->raw, true) : [];
            $brand    = $rawData['brand'] ?? '';
            $category = $rawData['category'] ?? '';
            $article  = $product->supplier_article ?? '';

            $this->line(sprintf(
                '[%d/%d] <fg=cyan>%s</> %s',
                $this->stats['processed'],
                $products->count(),
                $brand,
                mb_substr($product->name, 0, 60)
            ));

            $scraped = null;

            // ── Step 1: Scrape rusklimat.by ───────────────────────────────────────
            if (! $this->option('ai-only')) {
                $pageUrl = $this->findProductUrl($product->name, $article);
                if ($pageUrl) {
                    $html = $this->fetchPage($pageUrl);
                    if ($html) {
                        $scraped = $this->parseProductPage($html, $pageUrl);
                        $this->stats['scraped']++;
                        $this->line(sprintf(
                            '  <fg=green>✓ scraped</> specs:%d img:%s url:%s',
                            count($scraped['specs'] ?? []),
                            $scraped['image_url'] ? 'yes' : 'no',
                            basename($pageUrl)
                        ));
                    }
                    usleep(self::REQUEST_DELAY);
                } else {
                    $this->line('  <fg=yellow>✗ not found on rusklimat.by</>');
                }
            }

            $updates = [];

            // ── Step 2: Specs ─────────────────────────────────────────────────────
            if (! $this->option('skip-specs') && ! empty($scraped['specs'])) {
                $existing = json_decode($product->specs ?? '[]', true);
                if ($force || empty($existing)) {
                    $updates['specs'] = json_encode($scraped['specs'], JSON_UNESCAPED_UNICODE);
                    $this->stats['specs']++;
                }
            }

            // ── Step 3: Short description ─────────────────────────────────────────
            if (! empty($scraped['short_description'])) {
                $existing = trim($product->short_description ?? '');
                if ($force || $existing === '') {
                    $updates['short_description'] = $scraped['short_description'];
                }
            }

            // ── Step 4: Image ─────────────────────────────────────────────────────
            if (! $this->option('skip-images') && ! empty($scraped['image_url'])) {
                $existing = json_decode($product->images ?? '[]', true);
                if ($force || empty($existing)) {
                    $localPath = $this->downloadImage($scraped['image_url'], $product->slug, $imgDir);
                    if ($localPath) {
                        $updates['images'] = json_encode(
                            [self::IMAGE_DIR . '/' . basename($localPath)],
                            JSON_UNESCAPED_UNICODE
                        );
                        $this->stats['images']++;
                        $this->line('  <fg=green>✓ image: ' . basename($localPath) . '</>');
                    }
                }
            }

            // ── Step 5: AI description (only when real specs available) ──────────
            if (! $this->option('skip-content')) {
                $existing     = trim($product->content ?? '');
                $needsAi      = $force || $existing === '';
                $scrapadSpecs = $scraped['specs'] ?? [];
                $rawShort     = $scraped['short_description'] ?? null;

                if ($needsAi && $this->ai->isAvailable() && $this->dryRun) {
                    // Dry-run must NOT spend money on the AI API — just report intent.
                    $this->line('  <fg=blue>[dry-run] would generate AI description (' . $this->ai->providerName() . ')</>');
                } elseif ($needsAi && $this->ai->isAvailable()) {
                    $aiText = $this->ai->enrich($product->name, $brand, $rawShort, $scrapadSpecs);
                    if ($aiText) {
                        $updates['content'] = $aiText;
                        $this->stats['ai_used']++;
                        $this->line('  <fg=blue>✓ AI description (' . $this->ai->providerName() . ')</>');
                    }
                } elseif ($needsAi && ! $this->ai->isAvailable()) {
                    $this->line('  <fg=yellow>— AI not configured (set ANTHROPIC_API_KEY or AI_API_KEY)</>');
                }
            }

            // ── Write ─────────────────────────────────────────────────────────────
            if (empty($updates)) {
                $this->stats['skipped']++;
                $this->line('  — nothing to update');
            } elseif (! $this->dryRun) {
                $updates['updated_at'] = now();
                DB::table('products')->where('id', $product->id)->update($updates);
            } else {
                $this->line('  [dry-run] would update: ' . implode(', ', array_keys($updates)));
            }
        }

        // ── Summary ───────────────────────────────────────────────────────────────
        $this->newLine();
        $this->table(
            ['metric', 'count'],
            array_map(fn ($k, $v) => [$k, $v], array_keys($this->stats), array_values($this->stats))
        );

        if ($total > $limit + $offset) {
            $this->line(sprintf(
                "\n<fg=yellow>%d more products remain. Run with --offset=%d to continue.</>\n",
                $total - ($limit + $offset),
                $offset + $limit
            ));
        }

        return self::SUCCESS;
    }

    // ── Sitemap index ─────────────────────────────────────────────────────────────

    private function buildSitemapIndex(): void
    {
        $xml = $this->fetchPage(self::SITEMAP_URL);
        if (! $xml) {
            $this->warn('Could not load sitemap — will rely on URL construction only.');
            return;
        }

        preg_match_all('|<loc>(https://rusklimat\.by/product/[^<]+)</loc>|', $xml, $matches);
        foreach ($matches[1] as $url) {
            $slug = basename(rtrim($url, '/'));
            $this->sitemapIndex[$slug] = $url;
        }
    }

    /**
     * Find the product page URL on rusklimat.by.
     * Strategy:
     *  1. Exact sitemap slug match (fastest)
     *  2. Fuzzy sitemap match by key tokens
     *  3. rusklimat.by search by article code (finds products not in sitemap)
     *  4. Direct URL construction (HEAD request)
     */
    private function findProductUrl(string $productName, string $article): ?string
    {
        // Build candidate slugs from product name
        $candidates = $this->buildCandidateSlugs($productName, $article);

        // 1. Exact sitemap match
        foreach ($candidates as $slug) {
            if (isset($this->sitemapIndex[$slug])) {
                return $this->sitemapIndex[$slug];
            }
        }

        // 2. Fuzzy sitemap match by key tokens
        if (! empty($this->sitemapIndex)) {
            $tokens = $this->extractKeyTokens($productName);
            if (count($tokens) >= 2) {
                foreach ($this->sitemapIndex as $slug => $url) {
                    $matched = 0;
                    foreach ($tokens as $token) {
                        if (str_contains($slug, $token)) {
                            $matched++;
                        }
                    }
                    if ($matched >= max(2, (int) ceil(count($tokens) * 0.7))) {
                        return $url;
                    }
                }
            }
        }

        // 3. Search by article code on rusklimat.by
        if ($article !== '') {
            $found = $this->searchByArticle($article);
            if ($found !== null) {
                return $found;
            }
        }

        // 4. Try direct URL: https://rusklimat.by/product/{slug}
        foreach ($candidates as $slug) {
            $url  = 'https://rusklimat.by/product/' . $slug;
            $code = $this->headRequest($url);
            if ($code === 200) {
                return $url;
            }
            usleep(400_000);
        }

        return null;
    }

    /**
     * Search rusklimat.by by article code and return the first product URL found.
     * Uses their search page: https://rusklimat.by/search/?q=EWH-10-Q-bic-O
     */
    private function searchByArticle(string $article): ?string
    {
        $searchUrl = 'https://rusklimat.by/search/?q=' . urlencode($article);

        $html = $this->fetchPage($searchUrl);
        if ($html === null) {
            return null;
        }

        // Extract first product link from search results
        // rusklimat.by search results contain links like /product/some-slug
        if (preg_match('#href=["\'](?:https://rusklimat\.by)?(/product/[a-z0-9][a-z0-9\-]+)["\']#i', $html, $m)) {
            return 'https://rusklimat.by' . $m[1];
        }

        return null;
    }

    private function buildCandidateSlugs(string $name, string $article = ''): array
    {
        // Transliterate Russian → Latin then slug
        $translit = $this->transliterate($name);
        $slug1    = Str::slug($translit);

        // Also try without leading category words (котел, конвектор, etc.)
        $noPrefix = preg_replace(
            '/^(котел|конвектор|радиатор|насос|бойлер|водонагреватель|кондиционер|обогреватель|'
          . 'стабилизатор|теплый|теплые|клапан|расширительный|мембранный|группа|горелка)\s+/ui',
            '', $name
        );
        $slug2 = Str::slug($this->transliterate($noPrefix));

        // Article-based slug (e.g. EWH-10-Q-bic-O → ewh-10-q-bic-o)
        $slug3 = $article !== '' ? Str::slug(mb_strtolower($article)) : '';

        return array_unique(array_filter([$slug1, $slug2, $slug3]));
    }

    private function extractKeyTokens(string $name): array
    {
        // Transliterate and extract tokens > 3 chars (skip brand/model noise)
        $translit = Str::slug($this->transliterate($name));
        $tokens   = array_filter(explode('-', $translit), fn ($t) => mb_strlen($t) >= 3);

        // Keep max 5 tokens (brand + model are most discriminative)
        return array_slice(array_values($tokens), 0, 5);
    }

    // ── Scraper ───────────────────────────────────────────────────────────────────

    private function fetchPage(string $url): ?string
    {
        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                                       . 'AppleWebKit/537.36 Chrome/124.0 Safari/537.36',
                    'Accept-Language' => 'ru-RU,ru;q=0.9',
                    'Accept'          => 'text/html,application/xhtml+xml',
                ])
                ->withOptions(['verify' => false])
                ->get($url);

            return $response->successful() ? $response->body() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function headRequest(string $url): int
    {
        try {
            return Http::timeout(8)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->withOptions(['verify' => false, 'allow_redirects' => false])
                ->head($url)
                ->status();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function parseProductPage(string $html, string $url): array
    {
        $result = [
            'short_description' => null,
            'specs'             => [],
            'image_url'         => null,
            'source_url'        => $url,
        ];

        // ── og:image ──────────────────────────────────────────────────────────────
        if (preg_match('/<meta[^>]+property="og:image"[^>]+content="([^"]+)"/i', $html, $m) ||
            preg_match('/<meta[^>]+content="([^"]+)"[^>]+property="og:image"/i', $html, $m)) {
            $src = $m[1];
            if (filter_var($src, FILTER_VALIDATE_URL)) {
                $result['image_url'] = $src;
            }
        }

        // ── og:description → short_description ───────────────────────────────────
        if (preg_match('/<meta[^>]+property="og:description"[^>]+content="([^"]+)"/i', $html, $m) ||
            preg_match('/<meta[^>]+content="([^"]+)"[^>]+property="og:description"/i', $html, $m)) {
            $text = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
            if (mb_strlen($text) > 10) {
                $result['short_description'] = $text;
            }
        }

        // Fallback: short_desc_detailprod div
        if (! $result['short_description'] &&
            preg_match("/<div[^>]+class='short_desc_detailprod'[^>]*>(.*?)<\/div>/is", $html, $m)) {
            $text = trim(strip_tags($m[1]));
            if (mb_strlen($text) > 10) {
                $result['short_description'] = $text;
            }
        }

        // ── Specs: param-name / param-value pairs ─────────────────────────────────
        $specs = [];
        preg_match_all(
            '/<span[^>]+class="param-name"[^>]*>\s*(.*?)\s*<\/span>.*?'
          . '<span[^>]+class="param-value"[^>]*>\s*(.*?)\s*<\/span>/is',
            $html, $matches
        );
        for ($i = 0, $n = count($matches[1]); $i < $n; $i++) {
            $k = trim(strip_tags($matches[1][$i]));
            $v = trim(strip_tags($matches[2][$i]));
            if ($k !== '' && $v !== '' && $v !== '—') {
                $specs[$k] = $v;
            }
        }

        // Fallback: table rows with param-name / param-value classes
        if (empty($specs)) {
            preg_match_all(
                '/<td[^>]+class="[^"]*param-name[^"]*"[^>]*>(.*?)<\/td>\s*'
              . '<td[^>]+class="[^"]*param-value[^"]*"[^>]*>(.*?)<\/td>/is',
                $html, $matches
            );
            for ($i = 0, $n = count($matches[1]); $i < $n; $i++) {
                $k = trim(strip_tags($matches[1][$i]));
                $v = trim(strip_tags($matches[2][$i]));
                if ($k && $v && $v !== '—') {
                    $specs[$k] = $v;
                }
            }
        }

        $result['specs'] = $specs;
        return $result;
    }

    // ── Image downloader ─────────────────────────────────────────────────────────

    private function downloadImage(string $url, string $productSlug, string $dir): ?string
    {
        try {
            $response = Http::timeout(25)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->withOptions(['verify' => false])
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            $contentType = $response->header('Content-Type') ?? 'image/jpeg';
            $ext = match (true) {
                str_contains($contentType, 'png')  => 'png',
                str_contains($contentType, 'webp') => 'webp',
                default                            => 'jpg',
            };

            $filename = Str::slug($productSlug) . '.' . $ext;
            $path     = $dir . DIRECTORY_SEPARATOR . $filename;

            if (! $this->dryRun) {
                file_put_contents($path, $response->body());
            }

            return $path;
        } catch (\Throwable) {
            return null;
        }
    }

    // ── Russian → Latin transliteration ─────────────────────────────────────────

    private function transliterate(string $text): string
    {
        $map = [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo',
            'ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m',
            'н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u',
            'ф'=>'f','х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'shch',
            'ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
            'А'=>'A','Б'=>'B','В'=>'V','Г'=>'G','Д'=>'D','Е'=>'E','Ё'=>'Yo',
            'Ж'=>'Zh','З'=>'Z','И'=>'I','Й'=>'Y','К'=>'K','Л'=>'L','М'=>'M',
            'Н'=>'N','О'=>'O','П'=>'P','Р'=>'R','С'=>'S','Т'=>'T','У'=>'U',
            'Ф'=>'F','Х'=>'Kh','Ц'=>'Ts','Ч'=>'Ch','Ш'=>'Sh','Щ'=>'Shch',
            'Ъ'=>'','Ы'=>'Y','Ь'=>'','Э'=>'E','Ю'=>'Yu','Я'=>'Ya',
        ];

        return strtr($text, $map);
    }
}

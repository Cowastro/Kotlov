<?php

namespace App\Console\Commands;

use App\Services\AiContentEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Enrich products by scraping the manufacturer's official website.
 *
 * Unlike EnrichRusklimatCommand (which scrapes the supplier's site),
 * this command fetches descriptions, specs, and images from the brand's
 * own website — higher quality content, always available regardless of
 * whether the supplier lists the product on their site.
 *
 * Usage:
 *   php artisan supplier:enrich-manufacturer --brand="Royal Thermo" --limit=50
 *   php artisan supplier:enrich-manufacturer --brand=Electrolux --dry-run
 *   php artisan supplier:enrich-manufacturer --brand=Ballu --force --skip-images
 */
class EnrichFromManufacturerCommand extends Command
{
    protected $signature = 'supplier:enrich-manufacturer
        {--brand=            : Brand name to process (required)}
        {--supplier=         : Supplier code to filter products (default: all suppliers)}
        {--category=         : Filter by category id}
        {--limit=50          : Max products per run}
        {--offset=0          : Skip first N products}
        {--force             : Overwrite already-enriched products}
        {--skip-images       : Do not download images}
        {--skip-content      : Do not generate AI description}
        {--skip-specs        : Do not update specs}
        {--ai-only           : Only generate AI content, skip web scraping}
        {--dry-run           : Preview — no DB writes}';

    protected $description = 'Enrich products from manufacturer website (descriptions, specs, images)';

    private const REQUEST_DELAY = 1_500_000; // 1.5s between requests
    private const IMAGE_DIR     = 'img/products/manufacturer';

    private AiContentEnricher $ai;
    private bool $dryRun;

    private array $stats = [
        'processed' => 0,
        'scraped'   => 0,
        'ai_used'   => 0,
        'images'    => 0,
        'specs'     => 0,
        'skipped'   => 0,
        'errors'    => 0,
    ];

    /** @var string[] cached sitemap URLs for current brand */
    private array $sitemapUrls = [];

    public function handle(): int
    {
        $brandFilter = trim((string) $this->option('brand'));
        if ($brandFilter === '') {
            $this->error('--brand is required. Example: --brand="Royal Thermo"');
            return self::FAILURE;
        }

        $this->dryRun = (bool) $this->option('dry-run');
        $this->ai     = new AiContentEnricher();

        if ($this->dryRun) {
            $this->warn('[dry-run] No changes will be written.');
        }

        // ── Resolve brand ─────────────────────────────────────────────────────────
        $brand = DB::table('brands')
            ->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($brandFilter) . '%'])
            ->first(['id', 'name']);

        if (! $brand) {
            $this->error("Brand matching \"{$brandFilter}\" not found in DB.");
            return self::FAILURE;
        }

        $this->info("Brand: <fg=cyan>{$brand->name}</> (id={$brand->id})");

        // ── Load manufacturer source config ───────────────────────────────────────
        $sources    = config('brand_sources', []);
        $sourceConf = null;

        foreach ($sources as $key => $conf) {
            if (str_contains(mb_strtolower($brand->name), mb_strtolower($key))
                || str_contains(mb_strtolower($key), mb_strtolower($brandFilter))) {
                $sourceConf = $conf;
                break;
            }
        }

        $aiOnly = (bool) $this->option('ai-only') || $sourceConf === null;

        if ($sourceConf === null && ! $this->option('ai-only')) {
            $this->warn("No manufacturer source configured for \"{$brand->name}\" — falling back to AI-only.");
        } elseif ($sourceConf) {
            $this->info("Source: <fg=yellow>{$sourceConf['site']}</>");

            // Pre-load sitemap if configured
            if (! empty($sourceConf['sitemap_url'])) {
                $this->sitemapUrls = $this->loadSitemap($sourceConf['sitemap_url']);
                $this->info(sprintf('Sitemap: %d URLs loaded', count($this->sitemapUrls)));
            }
        }

        // ── Build product query ───────────────────────────────────────────────────
        $limit  = (int) $this->option('limit');
        $offset = (int) $this->option('offset');
        $force  = (bool) $this->option('force');

        $query = DB::table('products as p')
            ->where('p.brand_id', $brand->id)
            ->where('p.is_archived', false)
            ->select('p.id', 'p.name', 'p.slug', 'p.sku', 'p.content', 'p.specs',
                     'p.images', 'p.short_description');

        if ($supplierCode = $this->option('supplier')) {
            $query->join('supplier_products as sp', 'p.id', '=', 'sp.product_id')
                  ->join('suppliers as s', 'sp.supplier_id', '=', 's.id')
                  ->where('s.code', $supplierCode)
                  ->addSelect('sp.supplier_article');
        } else {
            $query->addSelect(DB::raw("NULL as supplier_article"));
        }

        if ($categoryId = $this->option('category')) {
            $query->where('p.category_id', (int) $categoryId);
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
            'Products to enrich: %d (processing %d, offset %d%s)',
            $total, $products->count(), $offset, $force ? ', --force' : ''
        ));

        if ($products->isEmpty()) {
            $this->info('Nothing to do.');
            return self::SUCCESS;
        }

        // ── Image dir ─────────────────────────────────────────────────────────────
        $imgDir = public_path(self::IMAGE_DIR . '/' . Str::slug($brand->name));
        if (! $this->dryRun && ! is_dir($imgDir)) {
            mkdir($imgDir, 0755, true);
        }

        $this->newLine();

        // ── Process products ──────────────────────────────────────────────────────
        foreach ($products as $product) {
            $this->stats['processed']++;
            $article = $product->supplier_article ?? $product->sku ?? '';

            $this->line(sprintf(
                '[%d/%d] <fg=cyan>%s</> %s',
                $this->stats['processed'],
                $products->count(),
                $brand->name,
                mb_substr($product->name, 0, 70)
            ));

            $scraped = null;

            // ── Step 1: Scrape manufacturer site ──────────────────────────────────
            if (! $aiOnly && $sourceConf !== null) {
                $pageUrl = $this->findProductUrl($product->name, $article, $sourceConf);

                if ($pageUrl) {
                    $this->line("  → <fg=green>{$pageUrl}</>");
                    $html    = $this->fetchPage($pageUrl);
                    $scraped = $html ? $this->parsePage($html, $pageUrl, $sourceConf) : null;

                    if ($scraped) {
                        $this->stats['scraped']++;
                        if (! empty($scraped['specs'])) {
                            $this->stats['specs']++;
                        }
                    }
                } else {
                    $this->line('  <fg=red>✗</> not found on ' . parse_url($sourceConf['site'], PHP_URL_HOST));
                }
            }

            // ── Step 2: Download image (skip if product already has photos) ──────
            $hasImages = ! empty($product->images) && $product->images !== '[]';
            if (! $this->option('skip-images')) {
                if ($hasImages && ! $force) {
                    $this->line('  <fg=yellow>↷</> image: skipped (already has photos; use --force to overwrite)');
                } elseif (empty($scraped['image_url'])) {
                    $this->line('  <fg=red>✗</> image: no URL found on manufacturer page');
                } else {
                    $this->line('  <fg=cyan>↓</> image: ' . $scraped['image_url']);
                    $localPath = $this->downloadImage($scraped['image_url'], $product->slug ?? $product->sku, $imgDir, $brand->name);
                    if ($localPath) {
                        $scraped['local_image'] = $localPath;
                        $this->stats['images']++;
                        $this->line('  <fg=green>✓</> saved: ' . $localPath);
                    } else {
                        $this->line('  <fg=red>✗</> image download failed');
                    }
                }
            }

            // ── Step 3: AI description ─────────────────────────────────────────────
            $aiText = null;
            if (! $this->option('skip-content') && (empty($product->content) || $force)) {
                $rawShort = $scraped['short_desc'] ?? '';
                $rawSpecs = $scraped['specs'] ?? [];
                $aiText   = $this->ai->enrich($product->name, $brand->name, $rawShort, $rawSpecs);

                if ($aiText) {
                    $this->stats['ai_used']++;
                    $this->line('  <fg=green>✓</> AI description');
                }
            }

            // ── Step 4: Persist ────────────────────────────────────────────────────
            $update = [];

            if ($aiText && (empty($product->content) || $force)) {
                $update['content'] = $aiText;
            }

            // Generate short description via AI — never use manufacturer's OG copy
            if (empty($product->short_description) || $force) {
                $shortDesc = $this->ai->shortDescription($product->name, $brand->name, $scraped['specs'] ?? []);
                if ($shortDesc) {
                    $update['short_description'] = $shortDesc;
                }
            }

            if (! $this->option('skip-specs') && ! empty($scraped['specs']) && (empty($product->specs) || $product->specs === '[]' || $force)) {
                $update['specs'] = json_encode($scraped['specs'], JSON_UNESCAPED_UNICODE);
            }

            if (! empty($scraped['local_image'])) {
                $existing = json_decode($product->images ?? '[]', true) ?: [];
                if (! in_array($scraped['local_image'], $existing, true)) {
                    array_unshift($existing, $scraped['local_image']);
                    $update['images'] = json_encode($existing, JSON_UNESCAPED_UNICODE);
                }
            }

            if ($update && ! $this->dryRun) {
                $update['updated_at'] = now();
                DB::table('products')->where('id', $product->id)->update($update);
            } elseif ($update) {
                $this->line('  [dry-run] would update: ' . implode(', ', array_keys($update)));
            } else {
                $this->line('  — nothing to update');
                $this->stats['skipped']++;
            }

            usleep(self::REQUEST_DELAY);
        }

        // ── Summary ───────────────────────────────────────────────────────────────
        $this->newLine();
        $this->table(
            ['Stat', 'Count'],
            collect($this->stats)->map(fn ($v, $k) => [$k, $v])->values()->toArray()
        );

        return self::SUCCESS;
    }

    // ── URL finding ───────────────────────────────────────────────────────────────

    private function findProductUrl(string $name, string $article, array $conf): ?string
    {
        // Sitemap-based matching (e.g. Electrolux)
        if (! empty($this->sitemapUrls)) {
            $url = $this->findBySitemap($name, $article, $conf['site']);
            if ($url) {
                return $url;
            }
        }

        if (empty($conf['search_url'])) {
            return null;
        }

        // 1. Search by article (most precise)
        if ($article !== '') {
            $url = $this->searchOnSite($article, $conf);
            if ($url) {
                return $url;
            }
        }

        // 2. Search by model (strip brand prefix from name)
        $model = $this->extractModelFromName($name);
        if ($model !== '' && $model !== $article) {
            $url = $this->searchOnSite($model, $conf);
            if ($url) {
                return $url;
            }
        }

        return null;
    }

    private function findBySitemap(string $name, string $article, string $site): ?string
    {
        $candidates = [];

        // Build slug variants from article and model name
        $model      = $this->extractModelFromName($name);
        $articleSlug = $this->toSlug($article);
        $modelSlug  = $this->toSlug($model);

        foreach (array_unique(array_filter([$articleSlug, $modelSlug])) as $slug) {
            if (mb_strlen($slug) < 4) {
                continue;
            }
            foreach ($this->sitemapUrls as $url) {
                $urlSlug = basename(rtrim($url, '/'));
                // Exact slug match or sitemap URL contains the slug
                if ($urlSlug === $slug || str_contains($urlSlug, $slug)) {
                    $candidates[] = $url;
                    break;
                }
            }
            if (! empty($candidates)) {
                break;
            }
        }

        return $candidates[0] ?? null;
    }

    private function loadSitemap(string $sitemapUrl): array
    {
        $xml = $this->fetchPage($sitemapUrl);
        if (! $xml) {
            return [];
        }

        preg_match_all('/<loc>(.*?)<\/loc>/s', $xml, $m);
        $urls = [];

        foreach ($m[1] as $url) {
            $url = trim($url);
            // Skip non-product URLs (category pages etc.)
            if (preg_match('/sitemap|category|catalog|xml$/i', $url)) {
                continue;
            }
            $urls[] = $url;
        }

        return $urls;
    }

    private function toSlug(string $s): string
    {
        // Lowercase, remove special chars, collapse dashes
        $s = mb_strtolower($s);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s);
        return trim($s, '-');
    }

    private function searchOnSite(string $query, array $conf): ?string
    {
        $searchUrl = sprintf($conf['search_url'], urlencode($query));
        usleep(500_000);

        $html = $this->fetchPage($searchUrl);
        if ($html === null) {
            return null;
        }

        $pattern = $conf['product_link_pattern'];

        if (preg_match($pattern, $html, $m)) {
            $path = $m[1];
            if (str_starts_with($path, 'http')) {
                return $path;
            }
            return rtrim($conf['site'], '/') . '/' . ltrim($path, '/');
        }

        return null;
    }

    private function extractModelFromName(string $name): string
    {
        // Remove common Russian prefix words and brand names
        $clean = preg_replace(
            '/^(водонагреватель|котел|радиатор|конвектор|насос|кондиционер|обогреватель|'
            . 'электрический|газовый|накопительный|настенный|напольный)\s+/ui',
            '', $name
        );

        // Extract the model code: usually the last WORD-DIGITS-DOTS pattern
        if (preg_match('/([A-Z][A-Z0-9\-\.\/]+(?:\s+[A-Z0-9\-\.\/]+){0,2})/u', $clean, $m)) {
            return trim($m[1]);
        }

        return trim($clean);
    }

    // ── Page parsing ──────────────────────────────────────────────────────────────

    private function parsePage(string $html, string $url, array $conf): array
    {
        $result = [
            'short_desc' => '',
            'specs'      => [],
            'image_url'  => null,
        ];

        // ── JSON-LD (schema.org Product) ──────────────────────────────────────────
        if (preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches)) {
            foreach ($matches[1] as $json) {
                $data = json_decode(trim($json), true);
                if (! $data) {
                    continue;
                }

                $items = isset($data[0]) ? $data : [$data];

                foreach ($items as $item) {
                    $type = $item['@type'] ?? '';

                    if (in_array($type, ['Product', 'product'], true)) {
                        if (! empty($item['description'])) {
                            $result['short_desc'] = strip_tags($item['description']);
                        }
                        // Image
                        if (! empty($item['image'])) {
                            $img = is_array($item['image']) ? ($item['image'][0] ?? null) : $item['image'];
                            if ($img) {
                                $result['image_url'] = is_array($img) ? ($img['url'] ?? null) : $img;
                            }
                        }
                        break 2;
                    }
                }
            }
        }

        // ── Product gallery image (Bitrix / common CMS patterns) ────────────────────
        if ($result['image_url'] === null) {
            $result['image_url'] = $this->findProductImage($html, $url);
        }

        // ── og:image as last resort (may be a generic brand banner) ──────────────
        if ($result['image_url'] === null) {
            if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\'](.*?)["\'][^>]*>/si', $html, $m)) {
                $ogImg = $m[1];
                if (! preg_match('/logo|icon|favicon|sprite/i', $ogImg)) {
                    $result['image_url'] = $ogImg;
                }
            }
        }

        // ── Specs: look for <table> or <dl> with characteristics ─────────────────
        $result['specs'] = $this->parseSpecs($html);

        return $result;
    }

    private function findProductImage(string $html, string $pageUrl): ?string
    {
        $host = parse_url($pageUrl, PHP_URL_SCHEME) . '://' . parse_url($pageUrl, PHP_URL_HOST);

        $makeAbsolute = function (string $url) use ($host): string {
            if (str_starts_with($url, 'http')) {
                return $url;
            }
            if (str_starts_with($url, '//')) {
                return 'https:' . $url;
            }
            return $host . '/' . ltrim($url, '/');
        };

        $skipPatterns = '/logo|icon|favicon|banner|sprite|pixel|1x1|arrow|star|noimage|nophoto/i';

        // 1. Bitrix: <a href="/upload/iblock/..." data-fancybox> — full-size gallery link
        if (preg_match_all('/<a[^>]+href=["\']([^"\']*\/upload\/(?:iblock|resize_cache)[^"\']*\.(?:jpg|jpeg|png|webp))["\'][^>]*>/i', $html, $m)) {
            foreach ($m[1] as $href) {
                if (! preg_match($skipPatterns, $href)) {
                    return $makeAbsolute($href);
                }
            }
        }

        // 2. <img data-src="...upload..."> (lazy-loaded Bitrix / other CMS)
        if (preg_match_all('/<img[^>]+data-src=["\']([^"\']+)["\'][^>]*>/i', $html, $imgs)) {
            foreach ($imgs[1] as $src) {
                if (str_starts_with($src, 'data:') || preg_match($skipPatterns, $src)) {
                    continue;
                }
                if (preg_match('/upload|products|catalog|goods|items|photo/i', $src)) {
                    return $makeAbsolute($src);
                }
            }
        }

        // 3. <img src="...upload...resize_cache...700_700..."> — Bitrix resized product photo
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $imgs)) {
            $best = null;
            foreach ($imgs[1] as $src) {
                if (str_starts_with($src, 'data:') || preg_match($skipPatterns, $src)) {
                    continue;
                }
                $sl = mb_strtolower($src);
                // Prefer resize_cache with large size (product photo)
                if (str_contains($sl, 'resize_cache') && preg_match('/[4-9]\d\d_[4-9]\d\d/', $src)) {
                    return $makeAbsolute($src);
                }
                if (! $best && preg_match('/upload|products|catalog|goods|items|photo/i', $src)) {
                    $best = $src;
                }
            }
            if ($best) {
                return $makeAbsolute($best);
            }
        }

        return null;
    }

    private function parseSpecs(string $html): array
    {
        $raw = [];

        // Try <table> rows: first column = name, second = value
        if (preg_match_all('/<tr[^>]*>\s*<t[dh][^>]*>(.*?)<\/t[dh]>\s*<t[dh][^>]*>(.*?)<\/t[dh]>/si', $html, $rows)) {
            foreach ($rows[1] as $i => $key) {
                $k = trim(strip_tags($key));
                $v = trim(strip_tags($rows[2][$i]));
                if ($k !== '' && $v !== '' && mb_strlen($k) < 100 && mb_strlen($v) < 200) {
                    $raw[$k] = $v;
                }
            }
        }

        // Try <dl><dt>/<dd> pattern
        if (empty($raw) && preg_match_all('/<dt[^>]*>(.*?)<\/dt>\s*<dd[^>]*>(.*?)<\/dd>/si', $html, $dl)) {
            foreach ($dl[1] as $i => $key) {
                $k = trim(strip_tags($key));
                $v = trim(strip_tags($dl[2][$i]));
                if ($k !== '' && $v !== '' && mb_strlen($k) < 100) {
                    $raw[$k] = $v;
                }
            }
        }

        // Convert to [{key, value, unit}] format (matches Filament Repeater + infolist)
        $specs = [];
        foreach ($raw as $k => $v) {
            $specs[] = ['key' => $k, 'value' => $v, 'unit' => ''];
        }

        return $specs;
    }

    // ── HTTP helpers ──────────────────────────────────────────────────────────────

    private function fetchPage(string $url): ?string
    {
        try {
            $response = Http::withHeaders([
                'User-Agent'      => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
                'Accept-Language' => 'ru-RU,ru;q=0.9',
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ])
            ->timeout(15)
            ->get($url);

            return $response->successful() ? $response->body() : null;
        } catch (\Throwable $e) {
            $this->stats['errors']++;
            return null;
        }
    }

    private function downloadImage(string $url, string $slug, string $dir, string $brandName): ?string
    {
        try {
            $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])->timeout(20)->get($url);
            if (! $response->successful()) {
                return null;
            }

            $ext      = $this->guessExtension($url, $response->header('Content-Type'));
            $filename = Str::slug($slug) . '.' . $ext;
            $fullPath = $dir . '/' . $filename;

            if (! $this->dryRun) {
                file_put_contents($fullPath, $response->body());
            }

            return self::IMAGE_DIR . '/' . Str::slug($brandName) . '/' . $filename;
        } catch (\Throwable) {
            return null;
        }
    }

    private function guessExtension(string $url, ?string $contentType): string
    {
        if ($contentType && str_contains($contentType, 'png')) {
            return 'png';
        }
        if ($contentType && str_contains($contentType, 'webp')) {
            return 'webp';
        }
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) ? $ext : 'jpg';
    }
}

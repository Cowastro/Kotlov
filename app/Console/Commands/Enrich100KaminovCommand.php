<?php

namespace App\Console\Commands;

use App\Services\AiContentEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Enrich Лигмет supplier products with photos, specs and AI content
 * scraped from 100kaminov.by.
 *
 *   php artisan supplier:enrich-100kaminov --dry-run
 *   php artisan supplier:enrich-100kaminov --apply
 *   php artisan supplier:enrich-100kaminov --apply --brand=Kratki --pages=5
 *   php artisan supplier:enrich-100kaminov --apply --overwrite-images --skip-ai
 */
class Enrich100KaminovCommand extends Command
{
    protected $signature = 'supplier:enrich-100kaminov
        {--brand=      : Limit to one brand (e.g. Kratki)}
        {--source-url= : Brand-specific category URL (e.g. /g768157-pechi-kaminy?csbss6=8334185); skips default CATEGORIES; comma-separated for multiple}
        {--pages=10    : Max pages to crawl per category}
        {--limit=      : Max products to enrich}
        {--sleep=800   : Delay between HTTP requests, ms}
        {--sitemap     : Collect product URLs from 100kaminov sitemap instead of listing pages}
        {--overwrite-images : Replace existing images}
        {--skip-ai     : Skip AI description/SEO generation}
        {--only-ai     : Only regenerate AI texts, skip images and specs}
        {--only-missing : Skip products that already have images}
        {--apply       : Write to DB (default: dry-run)}
        {--dry-run     : Preview only (default)}';

    protected $description = 'Enrich Лигмет brand products with photos/specs/AI from 100kaminov.by';

    private const BASE = 'https://100kaminov.by';
    private const IMAGE_DIR = 'img/products/ligmet';
    private const SUPPLIER_CODE = 'ligmet';

    /** Category pages to crawl. Value = our catalog category_id hint (unused for routing, just context). */
    private const CATEGORIES = [
        '/ps1026-top-pechej-kaminov?sort=position' => 61,   // Печи-камины
        '/ps1025-top-pechej-dlya?sort=position'    => 69,   // Банные печи
        '/ps1024-top-pechej-dlya?sort=position'    => 69,   // Банные печи (2)
        '/g6149558-kaminy'                          => 90,   // Камины / топки
        '/g6364208-reshetki-kaminnye-ventilyatsionnye' => 291, // Решётки
    ];

    /** Our Лигмет supplier brands — slugs as they appear in 100kaminov.by URLs. */
    private const BRAND_SLUGS = [
        'kratki'   => 'Kratki',
        'invicta'  => 'Invicta',
        'blist'    => 'Blist',
        'fireway'  => 'FireWay',
        'nordflam' => 'Nordflam',
        'panadero' => 'Panadero',
        'ferguss'  => 'Ferguss',
        'mbs'      => 'MBS',
        'ermak'    => 'Ермак',
        'кпд'      => 'КПД',
    ];

    /** Model-normalization stopwords (mirrors SyncLigmetCommand). */
    private const STOPWORDS = [
        'ПЕЧЬ','ПЕЧЬ-КАМИН','ПЕЧЬ-КАМИНЫ','КАМИН','КАМИННАЯ','КАМИННЫЙ','ТОПКА',
        'ПЕЧНОЙ','ДРОВЯНАЯ','ДРОВЯНОЙ','БАННАЯ','ОТОПИТЕЛЬНАЯ','ВАРОЧНАЯ',
        'СТАЛЬНАЯ','СТАЛЬНОЙ','ЧУГУННАЯ','ЧУГУННЫЙ',
        'СЕРАЯ','СЕРЫЙ','СЕРОЕ','СЕРЫЕ','ЧЁРНАЯ','ЧЁРНЫЙ','ЧЁРНОЕ','ЧЕРНАЯ','ЧЕРНЫЙ','ЧЕРНОЕ',
        'БЕЛАЯ','БЕЛЫЙ','БЕЛОЕ','БЕЖЕВАЯ','БЕЖЕВЫЙ','КРАСНАЯ','КРАСНЫЙ',
        'КОРИЧНЕВАЯ','КОРИЧНЕВЫЙ','ПАТИНА','АНТРАЦИТ','ГРАФИТ','КРЕМОВАЯ','КРЕМОВЫЙ',
        // English color/finish variants (100kaminov.by naming)
        'GREY','GRAY','BLACK','WHITE','SATIN','CERAMIC','ECODESIGN',
        // Invicta product-type prefixes (Лигмет column D starts with STOVE/FIREPLACE)
        'STOVE','FIREPLACE',
        // Nordflam eco-design suffix; Invicta finish variant
        'EKO','PATINE',
        // Russian qualifiers that 100kaminov appends: "с духовкой", "с камнем", "с крышкой"
        'С','ДУХОВКОЙ','КАМНЕМ','КРЫШКОЙ','ВОДЯНЫМ','ВЕНТИЛЯТОРОМ',
        // site-specific noise
        'КУПИТЬ','МИНСКЕ','ДОСТАВКОЙ','ЦЕНА','ОПИСАНИЕ','ХАРАКТЕРИСТИКИ',
        // MBS product-type prefixes ("Плита на дровах MBS ...")
        'ПЛИТА','НА','ДРОВАХ',
    ];

    private bool $apply;
    private int $sleep;
    private array $catalogIndex = [];   // [brand_lower][model_key] = ['id'=>,'name'=>,'has_images'=>]
    private array $stats = [
        'crawled' => 0, 'matched' => 0, 'enriched' => 0,
        'images'  => 0, 'specs'   => 0, 'ai_done'  => 0,
        'skipped' => 0, 'errors'  => 0,
    ];

    public function handle(): int
    {
        $this->apply  = (bool) $this->option('apply') && ! $this->option('dry-run');
        $this->sleep  = max(200, (int) $this->option('sleep'));

        $this->line($this->apply
            ? '<fg=red;options=bold>APPLY</>'
            : '<fg=yellow;options=bold>DRY RUN</>');

        $this->buildCatalogIndex();
        $this->info(sprintf('Catalog index: %d brands, %d products total',
            count($this->catalogIndex),
            array_sum(array_map('count', $this->catalogIndex))));

        // In dry-run, show sample of model keys so mismatches are diagnosable.
        if (! $this->apply) {
            $brandFilter = $this->option('brand') ? mb_strtolower((string) $this->option('brand')) : null;
            foreach ($this->catalogIndex as $bKey => $entries) {
                if ($brandFilter && $bKey !== $brandFilter) {
                    continue;
                }
                $keys = array_slice(array_keys($entries), 0, 30);
                $this->line(sprintf('  [%s] sample model keys: %s%s',
                    $bKey, implode(', ', $keys), count($entries) > 30 ? ' …' : ''));
            }
        }

        $brandFilter = $this->option('brand') ? mb_strtolower((string) $this->option('brand')) : null;
        $limit       = $this->option('limit') ? (int) $this->option('limit') : PHP_INT_MAX;
        $maxPages    = (int) $this->option('pages');
        $enriched    = 0;

        // Build category list: --source-url overrides defaults (no brand slug filter needed).
        $sourceUrlOpt = $this->option('source-url');
        if ($sourceUrlOpt) {
            $rawPaths = array_map('trim', explode(',', $sourceUrlOpt));
            $categories = [];
            foreach ($rawPaths as $p) {
                // Accept full URLs or just paths.
                $path = str_starts_with($p, 'http') ? parse_url($p, PHP_URL_PATH) . '?' . (parse_url($p, PHP_URL_QUERY) ?? '') : $p;
                $categories[rtrim($path, '?')] = null;
            }
            $brandSlugFilter = null; // URL already scoped to brand
        } else {
            $categories      = self::CATEGORIES;
            $brandSlugFilter = $brandFilter;
        }

        $seenProductUrls = [];

        if ((bool) $this->option('sitemap')) {
            $links = $this->collectSitemapProductLinks($brandFilter);
            $this->newLine();
            $this->info('Sitemap product links: ' . count($links));

            foreach ($links as $productUrl) {
                if ($enriched >= $limit) {
                    break;
                }
                try {
                    if ($this->processProduct($productUrl)) {
                        $enriched++;
                    }
                } catch (\Throwable $e) {
                    $this->stats['errors']++;
                    $this->warn("  error [{$productUrl}]: " . $e->getMessage());
                }
                usleep($this->sleep * 1000);
            }

            $this->newLine();
            $this->table(['metric', 'count'],
                array_map(fn ($k, $v) => [$k, $v], array_keys($this->stats), array_values($this->stats)));

            return $this->stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        foreach ($categories as $path => $catHint) {
            if ($enriched >= $limit) {
                break;
            }
            $this->newLine();
            $this->info("Category: {$path}");

            for ($page = 1; $page <= $maxPages; $page++) {
                $sep   = str_contains($path, '?') ? '&' : '?';
                $url   = self::BASE . $path . ($page > 1 ? "{$sep}page={$page}" : '');
                $links = $this->collectLinks($url, $brandSlugFilter);

                if ($links === []) {
                    break;
                }

                // Stop paginating when page brings no new URLs (last page repeats first).
                $newLinks = array_filter($links, fn ($l) => ! isset($seenProductUrls[$l]));
                if (empty($newLinks)) {
                    break;
                }
                foreach ($newLinks as $l) {
                    $seenProductUrls[$l] = true;
                }

                foreach ($newLinks as $productUrl) {
                    if ($enriched >= $limit) {
                        break 2;
                    }
                    try {
                        if ($this->processProduct($productUrl)) {
                            $enriched++;
                        }
                    } catch (\Throwable $e) {
                        $this->stats['errors']++;
                        $this->warn("  error [{$productUrl}]: " . $e->getMessage());
                    }
                    usleep($this->sleep * 1000);
                }
                usleep($this->sleep * 1000);
            }
        }

        $this->newLine();
        $this->table(['metric', 'count'],
            array_map(fn ($k, $v) => [$k, $v], array_keys($this->stats), array_values($this->stats)));

        return $this->stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    // ── Catalog index ─────────────────────────────────────────────────────────────

    private function collectSitemapProductLinks(?string $brandFilter): array
    {
        $root = $this->fetch(self::BASE . '/sitemap.xml');
        $mapUrls = [self::BASE . '/sitemap_products-0.xml'];
        if ($root !== null) {
            preg_match_all('#<loc>\s*(https?://[^<]+)\s*</loc>#i', $root, $maps);
            $mapUrls = array_values(array_unique(array_merge($mapUrls, $maps[1])));
        } else {
            $this->warn('Could not fetch sitemap index, trying product sitemap directly.');
        }

        $brandSlugs = $this->brandSlugsForFilter($brandFilter);
        $allBrandSlugs = array_map('mb_strtolower', array_keys(self::BRAND_SLUGS));
        $links = [];
        $seen = [];
        $mapCount = 0;
        $rawProductUrls = 0;
        $brandMatchedUrls = 0;

        foreach ($mapUrls as $mapUrl) {
            $mapUrl = html_entity_decode((string) $mapUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (! str_contains($mapUrl, 'sitemap_product')) {
                continue;
            }
            $mapCount++;

            $xml = $this->fetch($mapUrl);
            if ($xml === null) {
                $this->warn('Could not fetch sitemap: ' . $mapUrl);
                continue;
            }

            preg_match_all('#<loc>\s*(https?://[^<]+/p\d[^<]+\.html)\s*</loc>#i', $xml, $urls);
            $rawProductUrls += count($urls[1]);
            foreach ($urls[1] as $url) {
                $url = html_entity_decode((string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $lowerUrl = mb_strtolower($url);
                $needles = $brandSlugs !== [] ? $brandSlugs : $allBrandSlugs;

                if (! $this->urlContainsAny($lowerUrl, $needles)) {
                    continue;
                }
                $brandMatchedUrls++;
                if (isset($seen[$url])) {
                    continue;
                }

                $seen[$url] = true;
                $links[] = $url;
            }
        }

        sort($links);
        $this->line(sprintf(
            'Sitemap diagnostics: maps=%d raw_products=%d brand_matched=%d brand_filter=%s',
            $mapCount,
            $rawProductUrls,
            $brandMatchedUrls,
            $brandFilter ?: '-'
        ));

        return $links;
    }

    private function brandSlugsForFilter(?string $brandFilter): array
    {
        if ($brandFilter === null || $brandFilter === '') {
            return [];
        }

        $slugs = [];
        foreach (self::BRAND_SLUGS as $slug => $name) {
            if (mb_strtolower($name) === $brandFilter || mb_strtolower($slug) === $brandFilter) {
                $slugs[] = mb_strtolower($slug);
            }
        }

        return $slugs;
    }

    private function urlContainsAny(string $url, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($url, mb_strtolower((string) $needle))) {
                return true;
            }
        }

        return false;
    }

    private function buildCatalogIndex(): void
    {
        $brandFilter = $this->option('brand') ? mb_strtolower((string) $this->option('brand')) : null;

        // Load only Лигмет-supplied products (joined via supplier_products).
        $sid = (int) (DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id') ?? 0);
        if ($sid === 0) {
            $this->warn('Supplier "ligmet" not found in DB — will index all products of these brands.');
        }

        $brandsQ = DB::table('brands')->whereIn('name', array_values(self::BRAND_SLUGS));
        if ($brandFilter) {
            $brandsQ->whereRaw('LOWER(name) = ?', [$brandFilter]);
        }
        $brands = $brandsQ->pluck('id', 'name'); // name=>id

        foreach ($brands as $brandName => $brandId) {
            $key = mb_strtolower($brandName);
            $this->catalogIndex[$key] = [];

            $query = DB::table('products')
                ->where('brand_id', $brandId)
                ->where('is_archived', false);

            // If supplier exists, restrict to Лигмет-supplied products only.
            if ($sid > 0) {
                $pids = DB::table('supplier_products')
                    ->where('supplier_id', $sid)
                    ->whereNotNull('product_id')
                    ->pluck('product_id');
                $query->whereIn('id', $pids);
            }

            if ($this->option('only-missing')) {
                $query->where(function ($q) {
                    $q->whereNull('images')->orWhere('images', '')->orWhere('images', '[]');
                });
            }

            $query->get(['id', 'name', 'images'])->each(function ($p) use ($key, $brandName) {
                $model = $this->model((string) $p->name, $brandName);
                if ($model !== '') {
                    $hasImages = ! empty(json_decode((string) ($p->images ?? '[]'), true));
                    $this->catalogIndex[$key][$model] = [
                        'id'         => (int) $p->id,
                        'name'       => $p->name,
                        'has_images' => $hasImages,
                    ];
                }
            });
        }
    }

    // ── Category crawl ────────────────────────────────────────────────────────────

    /** Collect unique product URLs from a listing page, optionally filtered by brand. */
    private function collectLinks(string $url, ?string $brandFilter): array
    {
        $html = $this->fetch($url);
        if ($html === null) {
            return [];
        }

        preg_match_all('#href="(/p\d[^"]*\.html)"#', $html, $m);
        $seen  = [];
        $links = [];

        foreach ($m[1] as $href) {
            if (isset($seen[$href])) {
                continue;
            }
            $seen[$href] = true;

            // Quick brand filter from URL slug.
            if ($brandFilter !== null) {
                $matched = false;
                foreach (self::BRAND_SLUGS as $slug => $name) {
                    if (mb_strtolower($name) === $brandFilter && str_contains($href, $slug)) {
                        $matched = true;
                        break;
                    }
                }
                if (! $matched) {
                    continue;
                }
            } else {
                // Must belong to one of our brands.
                $anyBrand = false;
                foreach (array_keys(self::BRAND_SLUGS) as $slug) {
                    if (str_contains($href, $slug)) {
                        $anyBrand = true;
                        break;
                    }
                }
                if (! $anyBrand) {
                    continue;
                }
            }

            $links[] = self::BASE . $href;
        }

        $this->stats['crawled'] += count($links);
        return $links;
    }

    // ── Product processing ────────────────────────────────────────────────────────

    private function processProduct(string $url): bool
    {
        $html = $this->fetch($url);
        if ($html === null) {
            $this->stats['errors']++;
            return false;
        }

        $card = $this->parseCard($html, $url);
        if ($card['name'] === '') {
            return false;
        }

        // Detect brand from card title.
        $brandKey   = $this->detectBrand($card['name']);
        if ($brandKey === null) {
            return false;
        }
        $brandName  = self::BRAND_SLUGS[$brandKey];
        $modelKey   = $this->model($card['name'], $brandName);

        $entry = $this->catalogIndex[mb_strtolower($brandName)][$modelKey] ?? null;

        $this->line(sprintf('  [%s] %s → model:%s → %s',
            $brandName, mb_substr($card['name'], 0, 40), $modelKey,
            $entry ? "pid={$entry['id']}" : 'NO MATCH'));

        if ($entry === null) {
            $this->stats['skipped']++;
            return false;
        }

        $this->stats['matched']++;

        if (! $this->apply) {
            foreach (array_slice($card['specs'], 0, 4, true) as $k => $v) {
                $this->line("    · {$k}: {$v}");
            }
            $this->line('    · images: ' . count($card['images']));
            return true;
        }

        $pid     = $entry['id'];
        $now     = now();
        $changed = false;

        $onlyAi = (bool) $this->option('only-ai');

        // Images.
        if (! $onlyAi) {
            $imagesWritten = $this->downloadImages($pid, $card['images'], $entry['has_images']);
            if ($imagesWritten > 0) {
                $this->stats['images'] += $imagesWritten;
                $changed = true;
            }
        }

        // Specs — skip if product already has any values.
        if (! $onlyAi && $card['specs'] !== []) {
            $hasSpecs = DB::table('product_attribute_values')->where('product_id', $pid)->exists();
            if (! $hasSpecs) {
                $this->stats['specs'] += $this->writeSpecs($pid, $card['specs']);
            }
        }

        // AI enrichment: only when content is empty (or --only-ai forces regeneration).
        $existingContent = (string) DB::table('products')->where('id', $pid)->value('content');
        if (! $this->option('skip-ai') && ($onlyAi || trim($existingContent) === '')) {
            $this->generateAiContent($pid, $card, $brandName, $now);
        }

        if ($changed) {
            DB::table('products')->where('id', $pid)->update(['updated_at' => $now]);
        }

        // Store source URL in supplier_products.
        $sid = (int) (DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id') ?? 0);
        if ($sid > 0) {
            DB::table('supplier_products')
                ->where('supplier_id', $sid)
                ->where('product_id', $pid)
                ->update(['source_url' => $url, 'updated_at' => $now]);
        }

        $this->stats['enriched']++;
        return true;
    }

    // ── Parsing ───────────────────────────────────────────────────────────────────

    private function parseCard(string $html, string $url): array
    {
        // Name from og:title, strip "купить..." suffix.
        $name = '';
        if (preg_match('/"og:title" content="([^"]+)"/i', $html, $m)) {
            $name = preg_replace('/\s+купить\s+.*/iu', '', html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
            $name = trim($name, " \t\n\r\0\x0B.,");
        }

        // Images: collect all deal.by thumbnails, convert to w640 full-size.
        $images = [];
        if (preg_match('#"og:image" content="(https://images\.deal\.by/[^"]+)"#i', $html, $og)) {
            $images[] = preg_replace('#_w\d+_h\d+_#', '_w640_h640_', $og[1]);
        }
        preg_match_all('#(https://images\.deal\.by/\d+_w\d+_h\d+_[^\s"\']+\.jpg)#i', $html, $im);
        foreach (array_unique($im[1]) as $thumb) {
            $images[] = preg_replace('#_w\d+_h\d+_#', '_w640_h640_', $thumb);
        }
        $images = array_values(array_unique(array_filter($images)));

        // Specs: parse row-by-row so header cells (colspan) are naturally skipped.
        // Each <tr> with exactly 2 b-product-info__cell cells is a key→value pair.
        $specs = [];
        if (preg_match_all('#<tr[^>]*>(.*?)</tr>#s', $html, $trm)) {
            foreach ($trm[1] as $rowHtml) {
                preg_match_all('#<td[^>]*b-product-info__cell[^>]*>\s*([^<]+?)\s*</td>#i', $rowHtml, $cm);
                if (count($cm[1]) === 2) {
                    $key = trim(html_entity_decode($cm[1][0], ENT_QUOTES, 'UTF-8'));
                    $val = trim(html_entity_decode($cm[1][1], ENT_QUOTES, 'UTF-8'));
                    if ($key !== '' && $val !== '' && $key !== $val) {
                        $specs[$key] = $val;
                    }
                }
            }
        }

        // Description: b-user-content block.
        $desc = '';
        if (preg_match('#data-qaid="product_description"[^>]*>(.*?)</div>\s*</div>\s*</div>#s', $html, $dm)) {
            $raw = $dm[1];
            // Strip YouTube iframes, keep text + lists.
            $raw = preg_replace('#<iframe[^>]*>.*?</iframe>#s', '', $raw) ?? $raw;
            $raw = preg_replace('#<img[^>]*>#', '', $raw) ?? $raw;
            $desc = trim($raw);
        }

        return compact('name', 'images', 'specs', 'desc');
    }

    private function detectBrand(string $name): ?string
    {
        $upper = mb_strtoupper($name);
        foreach (self::BRAND_SLUGS as $slug => $canonical) {
            if (mb_stripos($upper, mb_strtoupper($canonical)) !== false) {
                return $slug;
            }
        }
        return null;
    }

    private function model(string $name, string $brand): string
    {
        $n = mb_strtoupper($name);
        if ($brand !== '') {
            $n = preg_replace('/' . preg_quote(mb_strtoupper($brand), '/') . '/u', '', $n) ?? $n;
        }
        // Keep only Cyrillic/Latin letters, digits, hyphen. Slash and dot become spaces
        // so Лигмет variants like "RUNA/BLACK" normalise to "RUNA BLACK" → "RUNA" after stopwords.
        $n = preg_replace('/[^А-ЯЁA-Z0-9\-]/u', ' ', $n) ?? $n;
        $toks = array_filter(
            preg_split('/\s+/u', trim($n)) ?: [],
            // Also strip Invicta-style product reference codes: P615644, P927475, etc.
            fn ($t) => $t !== '' && ! $this->isStopword($t, $brand) && ! preg_match('/^P\d{5,}$/', $t)
        );
        $key = implode(' ', $toks);
        if (mb_strtolower($brand) === 'blist') {
            $key = trim($key . ' ' . $this->blistColorSuffix($n));
        }

        return $key;
    }

    // ── Images ────────────────────────────────────────────────────────────────────

    private function isStopword(string $token, string $brand): bool
    {
        if (mb_strtolower($brand) === 'blist' && $this->isColorToken($token)) {
            return false;
        }

        return in_array($token, self::STOPWORDS, true);
    }

    private function isColorToken(string $token): bool
    {
        static $colors = [
            'БЕЖЕВАЯ', 'БЕЖЕВЫЙ', 'БЕЖЕВОЕ', 'БЕЖЕВЫЕ',
            'КРАСНАЯ', 'КРАСНЫЙ', 'КРАСНОЕ', 'КРАСНЫЕ',
            'ЧЕРНАЯ', 'ЧЕРНЫЙ', 'ЧЕРНОЕ', 'ЧЕРНЫЕ',
            'ЧЁРНАЯ', 'ЧЁРНЫЙ', 'ЧЁРНОЕ', 'ЧЁРНЫЕ',
            'СЕРАЯ', 'СЕРЫЙ', 'СЕРОЕ', 'СЕРЫЕ',
            'БЕЛАЯ', 'БЕЛЫЙ', 'БЕЛОЕ', 'БЕЛЫЕ',
            'Đ‘Đ•Đ–Đ•Đ’ĐĐŻ', 'Đ‘Đ•Đ–Đ•Đ’Đ«Đ™',
            'ĐšĐ ĐĐˇĐťĐĐŻ', 'ĐšĐ ĐĐˇĐťĐ«Đ™',
            'Đ§ĐĐ ĐťĐĐŻ', 'Đ§ĐĐ ĐťĐ«Đ™', 'Đ§Đ•Đ ĐťĐĐŻ', 'Đ§Đ•Đ ĐťĐ«Đ™',
            'ĐˇĐ•Đ ĐĐŻ', 'ĐˇĐ•Đ Đ«Đ™',
            'Đ‘Đ•Đ›ĐĐŻ', 'Đ‘Đ•Đ›Đ«Đ™',
        ];

        return in_array($token, $colors, true);
    }

    private function blistColorSuffix(string $upperName): string
    {
        $map = [
            'BLIST_BEIGE' => ['БЕЖ', 'Đ±ĐµĐ¶', 'Đ‘Đ•Đ–'],
            'BLIST_RED' => ['КРАСН', 'ĐşŃ€Đ°ŃĐ˝', 'ĐšĐ ĐĐˇĐť'],
            'BLIST_BLACK' => ['ЧЕРН', 'ЧЁРН', 'Đ§ĐµŃ€Đ˝', 'Đ§Đ•Đ Đť', 'Đ§ĐĐ Đť'],
            'BLIST_GREY' => ['СЕР', 'ĐˇĐµŃ€', 'ĐˇĐ•Đ '],
            'BLIST_WHITE' => ['БЕЛ', 'Đ±ĐµĐ»', 'Đ‘Đ•Đ›'],
        ];

        foreach ($map as $suffix => $needles) {
            foreach ($needles as $needle) {
                if (mb_stripos($upperName, $needle) !== false) {
                    return $suffix;
                }
            }
        }

        return '';
    }

    private function downloadImages(int $pid, array $urls, bool $hasImages): int
    {
        if ($hasImages && ! $this->option('overwrite-images')) {
            return 0;
        }

        $dir  = public_path(self::IMAGE_DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $saved   = [];
        $hashes  = [];
        $written = 0;

        foreach (array_slice($urls, 0, 8) as $url) {
            $body = $this->fetch($url, true);
            if ($body === null || strlen($body) < 5000) {
                continue;
            }
            $size = @getimagesizefromstring($body);
            if (! $size || $size[0] < 300 || $size[1] < 300) {
                continue;
            }
            $md5 = md5($body);
            if (isset($hashes[$md5])) {
                continue;
            }
            $hashes[$md5] = true;

            $ext  = match ($size['mime']) {
                'image/png' => 'png', 'image/webp' => 'webp', default => 'jpg',
            };
            $file = $pid . '_' . $written . '.' . $ext;
            file_put_contents($dir . '/' . $file, $body);
            $saved[] = self::IMAGE_DIR . '/' . $file;
            $written++;
            usleep(200_000);
        }

        if ($saved !== []) {
            $imagesArr = array_values($saved);
            DB::table('products')->where('id', $pid)->update([
                'images' => json_encode($imagesArr),
            ]);
        }

        return $written;
    }

    // ── Specs → product_attribute_values ─────────────────────────────────────────

    private function writeSpecs(int $pid, array $specs): int
    {
        $catId   = (int) DB::table('products')->where('id', $pid)->value('category_id');
        $written = 0;
        $now     = now();

        foreach ($specs as $key => $val) {
            // Find existing attribute for this category.
            $attrId = DB::table('attributes')
                ->where('category_id', $catId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($key)])
                ->value('id');

            if (! $attrId) {
                // attributes.category_id is NOT NULL (FK), no global fallback.
                // type ENUM is select|value|check — 'value' = plain text/numeric.
                $attrId = DB::table('attributes')->insertGetId([
                    'category_id' => $catId,
                    'name'        => $key,
                    'type'        => 'value',
                    'in_filter'   => false,
                    'in_product'  => true,
                    'in_brief'    => false,
                    'sort_order'  => 0,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }

            if (DB::table('product_attribute_values')->where('product_id', $pid)->where('attribute_id', $attrId)->exists()) {
                continue;
            }
            DB::table('product_attribute_values')->insert([
                'product_id' => $pid, 'attribute_id' => $attrId,
                'value' => (string) $val, 'updated_at' => $now, 'created_at' => $now,
            ]);
            $written++;
        }

        return $written;
    }

    // ── AI content ────────────────────────────────────────────────────────────────

    private function generateAiContent(int $pid, array $card, string $brand, $now): void
    {
        $enricher = app(AiContentEnricher::class);
        if (! $enricher->isAvailable()) {
            return;
        }

        $existing = DB::table('products')->where('id', $pid)->first(['short_description', 'meta_title', 'meta_description', 'name', 'category_id']);
        if (! $existing) {
            return;
        }

        $updates = [];
        $rawDesc = $card['desc'] ?? '';

        // Same approach as admin "Обновить из ссылки": raw supplier text is context only,
        // AI generates unique SEO HTML; raw text is never stored.
        $aiContent = $enricher->enrich((string) $existing->name, $brand, $rawDesc, $card['specs']);
        if ($aiContent !== null && trim(strip_tags($aiContent)) !== '') {
            $updates['content'] = strip_tags($aiContent, '<p><ul><li><strong>');

            $short = $enricher->shortDescription((string) $existing->name, $brand, $card['specs'])
                ?: mb_substr(trim(strip_tags($aiContent)), 0, 240);
            $updates['short_description'] = mb_substr(trim($short), 0, 240);
            $updates['meta_description']  = mb_substr(trim($short), 0, 250);
        }

        if ($updates !== []) {
            $updates['updated_at'] = $now;
            DB::table('products')->where('id', $pid)->update($updates);
            $this->stats['ai_done']++;
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

<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ScrapesAqualiderCard;
use App\Services\AiContentEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Enrich Лигмет brand products from additional Bitrix-based sources
 * (kaminbel.by, ochag.by, etc.) that aren't covered by teplodvor/100kaminov.
 *
 * Uses the generic ScrapesAqualiderCard trait (og:title/og:image/properties-group).
 *
 *   php artisan supplier:enrich-ligmet-extra --base-url=https://kaminbel.by --source-url=/product/pechi-kaminy/fireway/ --brand=FireWay --dry-run
 *   php artisan supplier:enrich-ligmet-extra --base-url=https://ochag.by --source-url=/kaminy/pechi-kaminy/pechi-ferguss/ --brand=Ferguss --apply
 */
class EnrichLigmetExtraCommand extends Command
{
    use ScrapesAqualiderCard;

    protected $signature = 'supplier:enrich-ligmet-extra
        {--base-url=        : Base domain of source site (e.g. https://kaminbel.by)}
        {--source-url=      : Listing page path(s), comma-separated}
        {--brand=           : Limit to one catalog brand}
        {--pages=15         : Max pages per listing URL}
        {--limit=           : Max products to process}
        {--sleep=800        : Delay between requests, ms}
        {--overwrite-images : Replace existing images}
        {--only-ai          : Regenerate AI texts only, skip images and specs}
        {--apply            : Write changes (default: dry-run)}
        {--dry-run          : Preview only (default)}';

    protected $description = 'Enrich Лигмет brand products from additional Bitrix sites (kaminbel.by, ochag.by, etc.)';

    private const SUPPLIER_CODE = 'ligmet';
    private const IMAGE_DIR     = 'img/products/ligmet';

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

    private const STOPWORDS = [
        'ПЕЧЬ','КАМИН','КАМИННАЯ','КАМИННЫЙ','ТОПКА','ПЕЧНОЙ',
        'ДРОВЯНАЯ','ДРОВЯНОЙ','БАННАЯ','ОТОПИТЕЛЬНАЯ','ВАРОЧНАЯ','ОТОПИТЕЛЬНО','ВАРОЧНО',
        'ДЛЯ','ОТОПЛЕНИЯ','ВАРКИ',
        'СТАЛЬНАЯ','СТАЛЬНОЙ','ЧУГУННАЯ','ЧУГУННЫЙ','ПЛИТА','НА','ДРОВАХ',
        'СЕРАЯ','СЕРЫЙ','ЧЁРНАЯ','ЧЁРНЫЙ','ЧЕРНАЯ','ЧЕРНЫЙ',
        'БЕЛАЯ','БЕЛЫЙ','БЕЖЕВАЯ','БЕЖЕВЫЙ','КРАСНАЯ','КРАСНЫЙ',
        'КОРИЧНЕВАЯ','КОРИЧНЕВЫЙ','ПАТИНА','АНТРАЦИТ','ГРАФИТ','КРЕМОВАЯ','КРЕМОВЫЙ',
        'GREY','GRAY','BLACK','WHITE','SATIN','CERAMIC','ECODESIGN',
        'STOVE','FIREPLACE','EKO','PATINE','PATINA',
        'С','ДУХОВКОЙ','КАМНЕМ','КРЫШКОЙ','ВОДЯНЫМ','ВЕНТИЛЯТОРОМ','КОНТУРОМ',
        'КОНФОРКИ','КОНФОРКА','КОНФОРКАМИ','КОНФОРОК',
        'КУПИТЬ','МИНСКЕ','ДОСТАВКОЙ','ЦЕНА',
        'УЦЕНКА','АКЦИЯ','РАСПРОДАЖА',
        'QUOT',
        'KRATKI','INVICTA','BLIST','FIREWAY','NORDFLAM','FERGUSS','MBS','PANADERO','ЕРМАК',
    ];

    private bool  $apply;
    private string $base;
    private array  $catalogIndex = [];
    private array  $stats = [
        'crawled' => 0, 'matched' => 0, 'enriched' => 0,
        'images'  => 0, 'specs'   => 0, 'ai_done'  => 0,
        'skipped' => 0, 'errors'  => 0,
    ];

    public function handle(): int
    {
        $this->apply = (bool) $this->option('apply') && ! $this->option('dry-run');
        $this->base  = rtrim((string) $this->option('base-url'), '/');

        if ($this->base === '') {
            $this->error('--base-url is required (e.g. https://kaminbel.by)');
            return self::FAILURE;
        }

        $sourceOpt = (string) $this->option('source-url');
        if ($sourceOpt === '') {
            $this->error('--source-url is required (e.g. /product/pechi-kaminy/fireway/)');
            return self::FAILURE;
        }

        $this->line($this->apply ? '<fg=red;options=bold>APPLY</>' : '<fg=yellow;options=bold>DRY RUN</>');
        $this->line("Source: {$this->base}");

        $this->buildCatalogIndex();
        $this->info(sprintf('Catalog: %d brands, %d products', count($this->catalogIndex),
            array_sum(array_map('count', $this->catalogIndex))));

        if (! $this->apply) {
            $bf = $this->option('brand') ? mb_strtolower((string) $this->option('brand')) : null;
            foreach ($this->catalogIndex as $bKey => $entries) {
                if ($bf && $bKey !== $bf) {
                    continue;
                }
                $keys = array_slice(array_keys($entries), 0, 25);
                $this->line("  [{$bKey}] keys: " . implode(', ', $keys));
            }
        }

        $paths    = array_map('trim', explode(',', $sourceOpt));
        $limit    = $this->option('limit') ? (int) $this->option('limit') : PHP_INT_MAX;
        $maxPages = (int) $this->option('pages');
        $enriched = 0;

        // Sitemap index — parsed once, reused for all source paths.
        $sitemapUrls = $this->fetchSitemapIndex();
        $this->line('Sitemaps found: ' . count($sitemapUrls));

        foreach ($paths as $path) {
            if ($enriched >= $limit) {
                break;
            }
            $path = str_starts_with($path, 'http') ? (parse_url($path, PHP_URL_PATH) ?? $path) : $path;
            $prefix = $this->base . rtrim($path, '/') . '/';
            $this->newLine();
            $this->info("Collecting: {$path}");

            // 1. Try sitemap (works even on JS-rendered sites).
            $links = $this->collectLinksFromSitemaps($sitemapUrls, $prefix);

            // 2. Fallback: paginated HTML scraping.
            if ($links === []) {
                $this->line('  sitemap: no links — falling back to HTML crawl');
                // Product URLs may be siblings of the listing page (same parent dir), not children.
                // Filter by parent path to include e.g. /cat/pech-brand-model/ from listing /cat/brand/
                $parentPath = $this->base . '/' . implode('/', array_slice(
                    array_filter(explode('/', trim($path, '/'))),
                    0, -1
                )) . '/';
                $seen = [];
                for ($page = 1; $page <= $maxPages; $page++) {
                    $pageUrl  = $this->listingUrl($path, $page);
                    $newLinks = array_filter(
                        $this->collectLinks($pageUrl),
                        fn ($l) => ! isset($seen[$l]) && str_starts_with($l, $parentPath) && $l !== $prefix
                    );
                    if ($newLinks === []) {
                        $this->line("  HTML page {$page}: no new links, stopping.");
                        break;
                    }
                    foreach ($newLinks as $l) {
                        $seen[$l] = true;
                        $links[]  = $l;
                    }
                    $this->line("  HTML page {$page}: " . count($newLinks) . ' links');
                }
            } else {
                $this->line('  sitemap: ' . count($links) . ' links');
            }

            foreach (array_slice($links, 0, $limit - $enriched) as $productUrl) {
                try {
                    if ($this->processProduct($productUrl)) {
                        $enriched++;
                    }
                } catch (\Throwable $e) {
                    $this->stats['errors']++;
                    $this->warn("  error [{$productUrl}]: " . $e->getMessage());
                }
                usleep((int) $this->option('sleep') * 1000);
            }
        }

        $this->newLine();
        $this->table(['metric', 'count'],
            array_map(fn ($k, $v) => [$k, $v], array_keys($this->stats), array_values($this->stats)));

        return $this->stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    // ── Catalog index ─────────────────────────────────────────────────────────────

    private function buildCatalogIndex(): void
    {
        $bf = $this->option('brand') ? mb_strtolower((string) $this->option('brand')) : null;

        $sid = (int) (DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id') ?? 0);

        $brandsQ = DB::table('brands')->whereIn('name', array_values(self::BRAND_SLUGS));
        if ($bf) {
            $brandsQ->whereRaw('LOWER(name) = ?', [$bf]);
        }

        foreach ($brandsQ->get(['id', 'name']) as $b) {
            $key   = mb_strtolower($b->name);
            $query = DB::table('products')->where('brand_id', $b->id)->where('is_archived', false);
            if ($sid > 0) {
                $pids = DB::table('supplier_products')->where('supplier_id', $sid)
                    ->whereNotNull('product_id')->pluck('product_id');
                $query->whereIn('id', $pids);
            }
            $this->catalogIndex[$key] = [];
            $query->get(['id', 'name', 'images', 'content', 'category_id'])->each(function ($p) use ($key, $b) {
                $modelKey = $this->modelKey((string) $p->name, $b->name);
                if ($modelKey !== '') {
                    $this->catalogIndex[$key][$modelKey] = [
                        'id'         => (int) $p->id,
                        'name'       => $p->name,
                        'category_id' => (int) $p->category_id,
                        'has_images' => ! empty(json_decode((string) ($p->images ?? '[]'), true)),
                        'content'    => (string) $p->content,
                    ];
                }
            });
        }
    }

    // ── Processing ────────────────────────────────────────────────────────────────

    private function processProduct(string $url): bool
    {
        $html = $this->fetchCard($url);
        if ($html === null) {
            $this->stats['errors']++;
            return false;
        }

        $card = $this->parseCard($html, $url);
        $name = $card['name'];

        // Fallback: og:title absent on some Bitrix themes — extract from <title> or <h1>.
        if ($name === '') {
            $name = $this->extractNameFallback($html);
        }
        if ($name === '') {
            return false;
        }

        $brandKey = $this->detectBrand($name);
        if ($brandKey === null) {
            return false;
        }
        $brandName = self::BRAND_SLUGS[$brandKey];
        $modelKey  = $this->modelKey($name, $brandName);
        $entry     = $this->catalogIndex[mb_strtolower($brandName)][$modelKey] ?? null;

        $this->line(sprintf('  [%s] %s → %s → %s',
            $brandName, mb_substr($name, 0, 40), $modelKey,
            $entry ? "pid={$entry['id']}" : 'NO MATCH'));

        if ($entry === null) {
            $this->stats['skipped']++;
            $this->stats['crawled']++;
            return false;
        }

        $this->stats['crawled']++;
        $this->stats['matched']++;

        if (! $this->apply) {
            return true;
        }

        $pid    = $entry['id'];
        $catId  = $entry['category_id'];
        $now    = now();
        $onlyAi = (bool) $this->option('only-ai');

        // Photos.
        if (! $onlyAi) {
            $this->stats['images'] += $this->downloadCardImages(
                $pid, $card['images'], self::IMAGE_DIR, (bool) $this->option('overwrite-images')
            );
        }

        // Specs — skip if product already has any.
        if (! $onlyAi && $card['specs'] !== []) {
            $hasSpecs = DB::table('product_attribute_values')->where('product_id', $pid)->exists();
            if (! $hasSpecs) {
                $this->stats['specs'] += $this->writeCardSpecs($pid, $catId, $card['specs']);
            }
        }

        // AI — only when content is empty (or --only-ai).
        if ($onlyAi || trim($entry['content']) === '') {
            $this->generateAiContent($pid, $name, $card['desc'], $card['specs'], $brandName, $now);
        }

        $this->stats['enriched']++;
        return true;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────────

    // ── Sitemap discovery ─────────────────────────────────────────────────────────

    /** Fetch sitemap index and return list of sub-sitemap URLs. Falls back to main sitemap. */
    private function fetchSitemapIndex(): array
    {
        $index = $this->fetchCard($this->base . '/sitemap.xml');
        if ($index === null) {
            return [];
        }
        preg_match_all('/<loc>([^<]+)<\/loc>/i', $index, $m);
        $locs = array_map('trim', $m[1] ?? []);
        // If these are sub-sitemaps (contain "sitemap"), return them; otherwise treat main sitemap as the only one.
        $subs = array_filter($locs, fn ($u) => str_contains($u, 'sitemap'));
        return $subs !== [] ? array_values($subs) : [$this->base . '/sitemap.xml'];
    }

    /** Search sub-sitemaps for URLs starting with $prefix. Stops early once first match found in a sitemap. */
    private function collectLinksFromSitemaps(array $sitemapUrls, string $prefix): array
    {
        $links = [];
        foreach ($sitemapUrls as $smUrl) {
            $xml = $this->fetchCard($smUrl);
            if ($xml === null) {
                continue;
            }
            preg_match_all('/<loc>([^<]+)<\/loc>/i', $xml, $m);
            foreach ($m[1] ?? [] as $loc) {
                $loc = trim($loc);
                if (str_starts_with($loc, $prefix) && $loc !== $prefix) {
                    $links[] = rtrim($loc, '/') . '/';
                }
            }
            if ($links !== []) {
                // Found the right sitemap — no need to scan others.
                break;
            }
        }
        return array_values(array_unique($links));
    }

    private function listingUrl(string $path, int $page): string
    {
        if ($page <= 1) {
            return $this->base . $path;
        }
        // Try Bitrix SEF page pattern: /path/page{n}/
        $clean = rtrim(parse_url($path, PHP_URL_PATH) ?? $path, '/');
        $q     = parse_url($path, PHP_URL_QUERY);
        return $this->base . $clean . '/page' . $page . '/' . ($q ? '?' . $q : '');
    }

    private function collectLinks(string $pageUrl): array
    {
        $html = $this->fetchCard($pageUrl);
        if ($html === null) {
            return [];
        }

        // Extract all hrefs — both absolute (https://domain/...) and relative (/path/...)
        preg_match_all('/href=["\']([^"\']+)["\']/', $html, $m);
        $links = [];
        foreach (array_unique($m[1] ?? []) as $href) {
            // Normalise to absolute URL
            if (str_starts_with($href, 'http')) {
                // Must belong to same domain
                if (! str_starts_with($href, $this->base)) {
                    continue;
                }
                $abs = $href;
            } elseif (str_starts_with($href, '/')) {
                $abs = $this->base . $href;
            } else {
                continue; // relative or anchor — skip
            }

            $path = parse_url($abs, PHP_URL_PATH) ?? '';

            // Skip pagination, basket, search, account, etc.
            if (preg_match('#/(page\d+|cart|basket|search|login|register|compare|wishlist|favorites|checkout|account)/?$#i', $path)) {
                continue;
            }

            // Product URL: 2+ path segments, last segment is a meaningful slug (≥5 chars, not purely numeric)
            $segs = array_values(array_filter(explode('/', trim($path, '/'))));
            if (count($segs) >= 2 && mb_strlen(end($segs)) >= 5 && ! is_numeric(end($segs))) {
                $links[] = rtrim($abs, '/') . '/';
            }
        }
        return array_values(array_unique($links));
    }

    private function extractNameFallback(string $html): string
    {
        $decode = fn (string $s): string => html_entity_decode(trim(strip_tags($s)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Try <h1> first — usually the clean product name on Bitrix/Aspro pages.
        if (preg_match('/<h1[^>]*>\s*(.*?)\s*<\/h1>/isu', $html, $m)) {
            $n = $decode($m[1]);
            if (mb_strlen($n) >= 4) {
                return $n;
            }
        }
        // Try <title> — strip "Купить " prefix and " | city, city" suffix.
        if (preg_match('/<title[^>]*>\s*(.*?)\s*<\/title>/isu', $html, $m)) {
            $n = $decode($m[1]);
            $n = preg_replace('/^купить\s+/iu', '', $n) ?? $n;
            $n = trim(preg_replace('/\s*[|—–]\s*.+$/u', '', $n) ?? $n);
            if (mb_strlen($n) >= 4) {
                return $n;
            }
        }
        return '';
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

    private function modelKey(string $name, string $brand): string
    {
        $n = mb_strtoupper($name);
        if ($brand !== '') {
            $n = preg_replace('/' . preg_quote(mb_strtoupper($brand), '/') . '/u', '', $n) ?? $n;
        }
        $n    = preg_replace('/[^А-ЯЁA-Z0-9]/u', ' ', $n) ?? $n;
        $toks = array_filter(
            preg_split('/\s+/u', trim($n)) ?: [],
            fn ($t) => $t !== ''
                && ! $this->isStopword($t, $brand)
                && ! preg_match('/^\d+$/', $t)
        );
        $key = implode(' ', $toks);
        if (mb_strtolower($brand) === 'blist') {
            $key = trim($key . ' ' . $this->blistColorSuffix($n));
        }

        return $key;
    }

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

    private function generateAiContent(int $pid, string $name, string $desc, array $specs, string $brand, $now): void
    {
        $enricher = app(AiContentEnricher::class);
        if (! $enricher->isAvailable()) {
            return;
        }

        $aiContent = $enricher->enrich($name, $brand, $desc, $specs);
        if ($aiContent === null || trim(strip_tags($aiContent)) === '') {
            return;
        }

        $short = $enricher->shortDescription($name, $brand, $specs)
            ?: mb_substr(trim(strip_tags($aiContent)), 0, 240);

        DB::table('products')->where('id', $pid)->update([
            'content'           => strip_tags($aiContent, '<p><ul><li><strong>'),
            'short_description' => mb_substr(trim($short), 0, 240),
            'meta_description'  => mb_substr(trim($short), 0, 250),
            'updated_at'        => $now,
        ]);
        $this->stats['ai_done']++;
    }
}

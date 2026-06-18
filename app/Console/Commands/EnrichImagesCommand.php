<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Find and download product photos from the open web for products that have
 * NO real photo yet — independent of rusklimat.by (whose sitemap covers few SKUs).
 *
 * The web image search effectively covers "manufacturer sites / equipment
 * catalogs / open product cards", so a single search-by-model query realises
 * the multi-source requirement. No AI is used.
 *
 * Provider is auto-detected from .env (set ONE of these):
 *   - Google Custom Search:  GOOGLE_CSE_KEY + GOOGLE_CSE_CX   (free 100/day)
 *   - Serper.dev:            SERPER_API_KEY
 *   - Bing Image Search:     BING_IMAGE_KEY
 *
 * Safety:
 *   - never touches products that already have a real photo
 *   - never touches archived products unless explicitly allowed
 *   - downloads locally (no hotlinks), validates type/size/dimensions
 *   - --dry-run previews queries + candidate URLs without writing anything
 *
 * Usage:
 *   php artisan supplier:enrich-images --active-only --limit=10 --dry-run
 *   php artisan supplier:enrich-images --active-only --limit=10
 *   php artisan supplier:enrich-images --brand=Ballu --active-only --limit=20
 */
class EnrichImagesCommand extends Command
{
    protected $signature = 'supplier:enrich-images
        {--active-only      : Only active (non-archived) products — recommended}
        {--include-archived : Also process archived products (default: skip them)}
        {--brand=           : Filter by brand name (partial match, e.g. --brand=Ballu)}
        {--supplier=          : Supplier code whose products to enrich (required)}
        {--limit=20         : Max products to process per run}
        {--offset=0         : Skip first N products (batching)}
        {--min-kb=30        : Minimum image size in KB}
        {--min-width=400    : Minimum image width in px}
        {--allow-untrusted  : Allow images from non-trusted domains (default: trusted only)}
        {--site=            : Restrict search to one domain (site:) and trust it — targeted backfill, e.g. --site=hommet-shop.ru}
        {--skip-known-failures : Skip products that recently failed image search (TTL 30 days)}
        {--sleep=600        : Delay between products in milliseconds}
        {--max-images=1     : Max images to download per product (deduplicated by domain)}
        {--dry-run          : Preview queries + candidate URLs, write nothing}';

    protected $description = 'Find & download photos for products with no real photo (web image search, no AI).';

    /** Re-try a previously failed product only after this many days. */
    private const FAILURE_TTL_DAYS = 30;

    private string $imageDir = 'img/products/rusklimat';

    /** Reject obvious non-product images by URL/filename. */
    private const BAD_URL_MARKERS = [
        'logo', 'placeholder', 'no-photo', 'nophoto', 'no_photo', 'noimage',
        'no-image', 'no_image', 'sprite', 'icon', 'favicon', 'default',
        'stub', 'banner', 'thumb', '/endeca/',
    ];

    /** Supplier-owned sources — exact product, highest trust. */
    private const PREFERRED_DOMAINS = ['rusklimat.ru', 'rusklimat.com', 'rkcdn.ru'];

    /** Reputable HVAC retailers/catalogs — accepted when no preferred source. */
    private const TRUSTED_DOMAINS = [
        // HVAC / climate
        'satro-paladin.com', '7-kvt.ru', 'dc-electro.ru', 'pro-komfort.com',
        'ksk.by', 'btsprom.by', 'ridan.ru',
        // Fireplaces / stoves (Лигмет brands)
        'kaminbel.by', 'ochag.by', '100kaminov.by', 'teplodvor.by',
        'kratki.com', 'kratki.pl', 'invicta-feu.com', 'blist.pl',
        'nordflam.com.pl', 'panadero.es', 'mbs-stoves.com',
        'ligmet.by', 'kamin.by', 'tsk.by', 'lazurit.by',
        // Ермак + BY bath stove retailers
        'ermak-pech.ru', 'ermak-termo.com', 'na-kostre.ru',
        'pechibani.by', '100kotlov.by', 'mir-para.by',
    ];

    /** Hard-rejected sources: marketplaces / fashion / auto / irrelevant.
     *  Dropped even with --allow-untrusted (НС codes collide with their ids). */
    private const DENY_DOMAINS = [
        'oskelly', 'lowes.', 'avtoall', 'wildberries', 'ozon.', 'aliexpress',
        'lamoda', 'market.yandex', 'megamarket', 'harley-davidson', 'usmall',
        'pinterest', 'drom.ru', 'avito', 'ebay.', 'youtube', 'vk.com',
    ];

    private bool $dryRun;
    private int  $minBytes;
    private int  $minWidth;

    private array $stats = [
        'processed' => 0, 'downloaded' => 0, 'would_download' => 0,
        'no_candidate' => 0, 'rejected' => 0, 'skipped_has_photo' => 0, 'errors' => 0,
    ];

    public function handle(): int
    {
        $this->dryRun   = (bool) $this->option('dry-run');
        $this->minBytes = max(1, (int) $this->option('min-kb')) * 1024;
        $this->minWidth = max(1, (int) $this->option('min-width'));
        $supplierOpt = trim((string) $this->option('supplier'));
        if ($supplierOpt === '') {
            $this->error('--supplier is required. Example: --supplier=ligmet');
            return self::FAILURE;
        }
        $this->imageDir = 'img/products/' . $supplierOpt;

        $provider = $this->detectProvider();

        $this->line($this->dryRun
            ? '<fg=yellow;options=bold>DRY RUN: nothing will be written.</>'
            : '<fg=red;options=bold>APPLY: images will be downloaded and saved.</>');

        if ($provider === null) {
            $this->warn('No image-search provider configured. Set ONE of:');
            $this->line('  GOOGLE_CSE_KEY + GOOGLE_CSE_CX   (Google Custom Search, free 100/day)');
            $this->line('  SERPER_API_KEY                   (serper.dev)');
            $this->line('  BING_IMAGE_KEY                   (Bing Image Search)');
            if (! $this->dryRun) {
                return self::FAILURE;
            }
            $this->warn('Continuing in dry-run to show the queries that WOULD be issued.');
        } else {
            $this->info('Image-search provider: ' . $provider);
        }

        $supplierCode = $supplierOpt;
        $supplierId   = DB::table('suppliers')->where('code', $supplierCode)->value('id');
        if (! $supplierId) {
            $this->error('Supplier "' . $supplierCode . '" not found.');
            return self::FAILURE;
        }

        $includeArchived = (bool) $this->option('include-archived');
        $activeOnly      = (bool) $this->option('active-only') || ! $includeArchived;
        $brandFilter     = $this->option('brand');
        $limit           = max(1, (int) $this->option('limit'));
        $offset          = max(0, (int) $this->option('offset'));
        $skipFailures    = (bool) $this->option('skip-known-failures');
        $site            = trim((string) $this->option('site'));
        $failuresTable   = Schema::hasTable('image_search_failures');

        if ($site !== '') {
            $this->info('Scoped to site:' . $site . ' (treated as preferred).');
        }

        if ($skipFailures && ! $failuresTable) {
            $this->warn('--skip-known-failures: table image_search_failures missing — run migrate. Ignoring flag.');
            $skipFailures = false;
        }

        // Candidates: linked to supplier, images empty (JSON-safe), active by default.
        $query = DB::table('products as p')
            ->join('supplier_products as sp', 'p.id', '=', 'sp.product_id')
            ->leftJoin('brands as b', 'p.brand_id', '=', 'b.id')
            ->where('sp.supplier_id', $supplierId)
            ->when($activeOnly && ! $includeArchived, fn ($q) => $q->where('p.is_archived', false))
            ->when($brandFilter, fn ($q) => $q->where('b.name', 'like', '%' . $brandFilter . '%'))
            ->when($skipFailures, fn ($q) => $q->whereNotExists(function ($sub) use ($supplierId) {
                $sub->from('image_search_failures as f')
                    ->whereColumn('f.product_id', 'p.id')
                    ->where('f.supplier_id', $supplierId)
                    ->where('f.searched_at', '>=', now()->subDays(self::FAILURE_TTL_DAYS));
            }))
            ->where(function ($q) {
                $q->whereNull('p.images')->orWhere('p.images', '')->orWhere('p.images', '[]')
                  ->orWhereRaw('(JSON_VALID(p.images) AND JSON_LENGTH(p.images) = 0)');
            });

        $total    = (clone $query)->distinct('p.id')->count('p.id');
        $products = $query->orderBy('p.id')->offset($offset)->limit($limit)
            ->get(['p.id', 'p.sku', 'p.name', 'p.slug', 'p.images', 'b.name as brand', 'sp.supplier_article']);

        $this->newLine();
        $this->info(sprintf('Products without photo: %d (processing %d, offset %d%s)',
            $total, $products->count(), $offset, $brandFilter ? ', brand=' . $brandFilter : ''));

        if ($products->isEmpty()) {
            $this->info('Nothing to do.');
            return self::SUCCESS;
        }

        $dir = public_path($this->imageDir);
        if (! $this->dryRun && ! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->newLine();

        foreach ($products as $i => $product) {
            $this->stats['processed']++;

            // Re-check on disk: never overwrite a real photo.
            if (! $this->imagesMissing($product->images)) {
                $this->stats['skipped_has_photo']++;
                continue;
            }

            $this->line(sprintf('[%d/%d] <fg=cyan>%s</> %s',
                $i + 1, $products->count(), $product->brand ?? '—', mb_substr($product->name, 0, 56)));

            $queries        = $this->buildQueries($product);
            $allowUntrusted = (bool) $this->option('allow-untrusted');
            $maxImages      = max(1, (int) $this->option('max-images'));
            $pool           = [];   // all accepted candidates, keyed by domain (one per domain)
            $poolUntrusted  = [];   // untrusted backlog — added only when allowed

            foreach ($queries as $q) {
                if ($provider === null) {
                    $this->line('  query:  ' . $q . '   <fg=gray>(no provider — set SERPER_API_KEY)</>');
                    continue;
                }

                $qFull      = $site !== '' ? $q . ' site:' . $site : $q;
                $candidates = $this->search($provider, $qFull);
                $this->line(sprintf('  query:  %s   <fg=gray>(%d candidates)</>', $qFull, count($candidates)));

                foreach ($candidates as $c) {
                    // Hard deny — skip before any download.
                    if ($this->isDenied($c['domain'] ?? '', $c['image_url'])) {
                        $this->stats['rejected']++;
                        $this->line(sprintf('    - %-24s <fg=yellow>rejected:deny-domain</>', mb_substr($c['domain'] ?: '?', 0, 24)));
                        continue;
                    }

                    $tier = $this->domainTier($c['domain'] ?? '', $c['image_url']);
                    // --site: treat the requested domain as preferred so it always wins.
                    if ($site !== '' && str_contains(mb_strtolower(($c['domain'] ?? '') . ' ' . $c['image_url']), mb_strtolower($site))) {
                        $tier = 'preferred';
                    }
                    // Trusted/preferred domains may use /thumbs/ in URL for full-size files — skip URL-marker check.
                    $check = $this->validateImage($c['image_url'], skipUrlMarkers: $tier !== 'untrusted');
                    $apiDim = ($c['width'] && $c['height']) ? sprintf('%dx%d', $c['width'], $c['height']) : '—';
                    $verdict = $check['ok']
                        ? '<fg=green>ACCEPTED</> [' . $tier . ']'
                        : '<fg=yellow>rejected:' . $check['reason'] . '</>';

                    $this->line(sprintf('    - %-24s api:%-9s %s', mb_substr($c['domain'] ?: '?', 0, 24), $apiDim, $verdict));
                    $this->line('      image_url:  ' . mb_substr($c['image_url'], 0, 86));
                    $this->line('      source_url: ' . mb_substr($c['source_url'] ?: '—', 0, 86));

                    if (! $check['ok']) {
                        $this->stats['rejected']++;
                        continue;
                    }

                    $entry  = $c + ['query' => $q, 'tier' => $tier, 'meta' => $check];
                    $domain = $c['domain'] ?? 'unknown';
                    if ($tier !== 'untrusted') {
                        // One image per domain — highest-tier wins (preferred > trusted).
                        if (! isset($pool[$domain])) {
                            $pool[$domain] = $entry;
                        } elseif ($tier === 'preferred' && $pool[$domain]['tier'] !== 'preferred') {
                            $pool[$domain] = $entry;
                        }
                    } elseif ($allowUntrusted && ! isset($poolUntrusted[$domain])) {
                        $poolUntrusted[$domain] = $entry;
                    }
                }

                usleep(max(0, (int) $this->option('sleep')) * 1000);

                // Stop querying early if we already have enough trusted/preferred images.
                $tierOrder = fn ($e) => match ($e['tier']) { 'preferred' => 0, 'trusted' => 1, default => 2 };
                $poolSorted = array_values($pool);
                usort($poolSorted, fn ($a, $b) => $tierOrder($a) <=> $tierOrder($b));
                if (count($poolSorted) >= $maxImages && ! $this->dryRun) {
                    break;
                }
            }

            // Merge untrusted as fallback and sort: preferred > trusted > untrusted.
            $tierOrder = fn ($e) => match ($e['tier']) { 'preferred' => 0, 'trusted' => 1, default => 2 };
            $allCandidates = array_values(array_merge($pool, array_diff_key($poolUntrusted, $pool)));
            usort($allCandidates, fn ($a, $b) => $tierOrder($a) <=> $tierOrder($b));
            $picks = array_slice($allCandidates, 0, $maxImages);

            $hasOnlyUntrusted = count($pool) === 0 && count($poolUntrusted) > 0 && ! $allowUntrusted;

            if (empty($picks)) {
                $this->stats['no_candidate']++;
                $reason = $hasOnlyUntrusted
                    ? 'NO TRUSTED IMAGE (only untrusted found — re-run with --allow-untrusted to accept)'
                    : 'NO USABLE IMAGE';
                $this->line('  <fg=yellow>result: ' . $reason . '</>');
                $this->logResult($product, '', '', '', 'no_image');
                if (! $this->dryRun && $failuresTable) {
                    DB::table('image_search_failures')->updateOrInsert(
                        ['product_id' => $product->id, 'supplier_id' => $supplierId],
                        ['searched_at' => now(), 'updated_at' => now()]
                    );
                }
                continue;
            }

            if ($this->dryRun) {
                $this->stats['would_download'] += count($picks);
                foreach ($picks as $idx => $picked) {
                    $this->line(sprintf('  <fg=green>result: WOULD DOWNLOAD #%d</> [%s] %s  (%dx%d, %dKB) @ %s',
                        $idx + 1, $picked['tier'],
                        mb_substr($picked['image_url'], 0, 60),
                        $picked['meta']['width'], $picked['meta']['height'],
                        (int) ($picked['meta']['bytes'] / 1024), $picked['domain'] ?: '?'));
                }
                $this->logResult($product, $picks[0]['query'], (string) $provider, $picks[0]['image_url'], 'would_download');
                continue;
            }

            try {
                $savedPaths = [];
                foreach ($picks as $picked) {
                    $saved        = $this->saveImage($picked['meta']['body'], $picked['image_url'], $product->slug, $dir);
                    $savedPaths[] = $this->imageDir . '/' . $saved;
                    $this->line('  <fg=green>result: SAVED ' . $saved . '</>');
                    $this->logResult($product, $picked['query'], (string) $provider, $picked['image_url'], 'saved:' . $saved);
                    if (Schema::hasTable('image_enrichment_logs')) {
                        DB::table('image_enrichment_logs')->insert([
                            'product_id'    => $product->id,
                            'supplier_id'   => $supplierId,
                            'image_path'    => $this->imageDir . '/' . $saved,
                            'image_url'     => $picked['image_url'],
                            'source_url'    => $picked['source_url'] ?? null,
                            'provider'      => (string) $provider,
                            'trusted_level' => $picked['tier'] ?? null,
                            'created_at'    => now(),
                            'updated_at'    => now(),
                        ]);
                    }
                }
                DB::table('products')->where('id', $product->id)->update([
                    'images'     => json_encode($savedPaths, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
                $this->stats['downloaded']++;
                if ($failuresTable) {
                    DB::table('image_search_failures')
                        ->where('product_id', $product->id)->where('supplier_id', $supplierId)->delete();
                }
            } catch (\Throwable $e) {
                $this->stats['errors']++;
                $this->line('  <fg=red>result: ERROR ' . $e->getMessage() . '</>');
                $this->logResult($product, $picks[0]['query'] ?? '', (string) $provider, $picks[0]['image_url'] ?? '', 'error:' . $e->getMessage());
            }
        }

        $this->newLine();
        $this->table(['metric', 'count'],
            array_map(fn ($k, $v) => [$k, $v], array_keys($this->stats), array_values($this->stats)));

        if ($total > $offset + $limit) {
            $this->line(sprintf("\n<fg=yellow>%d more remain. Continue with --offset=%d</>",
                $total - ($offset + $limit), $offset + $limit));
        }

        return self::SUCCESS;
    }

    // ── Search queries (priority order) ───────────────────────────────────────────

    private function buildQueries($product): array
    {
        $brand   = trim((string) ($product->brand ?? ''));
        $article = trim((string) ($product->supplier_article ?? ''));
        $name    = trim((string) $product->name);
        $model   = $this->extractModel($name, $brand);

        $queries = [];
        if ($article !== '')                 { $queries[] = $article; }
        if ($brand !== '' && $article !== '') { $queries[] = $brand . ' ' . $article; }
        if ($brand !== '' && $model !== '')   { $queries[] = $brand . ' ' . $model; }
        $queries[] = $name;

        return array_values(array_unique(array_filter($queries)));
    }

    private function extractModel(string $name, string $brand): string
    {
        $n = $name;
        if ($brand !== '') {
            $n = preg_replace('/' . preg_quote($brand, '/') . '/iu', '', $n) ?? $n;
        }
        // Drop leading category words; keep the discriminative model tail.
        $n = preg_replace('/^(водонагреватель|конвектор|радиатор|насос|котел|котёл|бойлер|'
            . 'обогреватель|кондиционер|сплит-система|тепловентилятор|стабилизатор|колонка)\s+/iu', '', trim($n)) ?? $n;
        return trim(preg_replace('/\s+/u', ' ', $n) ?? $n);
    }

    // ── Providers ─────────────────────────────────────────────────────────────────

    private function detectProvider(): ?string
    {
        if (env('GOOGLE_CSE_KEY') && env('GOOGLE_CSE_CX')) { return 'google_cse'; }
        if (env('SERPER_API_KEY'))                          { return 'serper'; }
        if (env('BING_IMAGE_KEY'))                          { return 'bing'; }
        return null;
    }

    /**
     * @return array<int,array{image_url:string,source_url:string,domain:string,width:int,height:int}>
     */
    private function search(string $provider, string $query): array
    {
        try {
            return match ($provider) {
                'google_cse' => $this->searchGoogleCse($query),
                'serper'     => $this->searchSerper($query),
                'bing'       => $this->searchBing($query),
                default      => [],
            };
        } catch (\Throwable $e) {
            $this->line('  <fg=red>search error: ' . $e->getMessage() . '</>');
            return [];
        }
    }

    private function searchGoogleCse(string $query): array
    {
        $r = Http::timeout(20)->get('https://www.googleapis.com/customsearch/v1', [
            'key' => env('GOOGLE_CSE_KEY'), 'cx' => env('GOOGLE_CSE_CX'),
            'searchType' => 'image', 'num' => 6, 'imgSize' => 'large', 'q' => $query,
        ]);
        if (! $r->successful()) {
            return [];
        }
        return $this->normalizeCandidates(array_map(fn ($it) => [
            'image_url'  => $it['link'] ?? '',
            'source_url' => $it['image']['contextLink'] ?? '',
            'domain'     => $it['displayLink'] ?? '',
            'width'      => (int) ($it['image']['width'] ?? 0),
            'height'     => (int) ($it['image']['height'] ?? 0),
        ], $r->json('items') ?? []));
    }

    private function searchSerper(string $query): array
    {
        $r = Http::timeout(20)
            ->withHeaders(['X-API-KEY' => env('SERPER_API_KEY'), 'Content-Type' => 'application/json'])
            ->post('https://google.serper.dev/images', ['q' => $query, 'num' => 6]);
        if (! $r->successful()) {
            $this->line('  <fg=red>serper http-' . $r->status() . '</>');
            return [];
        }
        return $this->normalizeCandidates(array_map(fn ($it) => [
            'image_url'  => $it['imageUrl'] ?? '',
            'source_url' => $it['link'] ?? '',
            'domain'     => $it['domain'] ?? (parse_url($it['link'] ?? '', PHP_URL_HOST) ?: ''),
            'width'      => (int) ($it['imageWidth'] ?? 0),
            'height'     => (int) ($it['imageHeight'] ?? 0),
        ], $r->json('images') ?? []));
    }

    private function searchBing(string $query): array
    {
        $r = Http::timeout(20)
            ->withHeaders(['Ocp-Apim-Subscription-Key' => env('BING_IMAGE_KEY')])
            ->get('https://api.bing.microsoft.com/v7.0/images/search', ['q' => $query, 'count' => 6]);
        if (! $r->successful()) {
            return [];
        }
        return $this->normalizeCandidates(array_map(fn ($it) => [
            'image_url'  => $it['contentUrl'] ?? '',
            'source_url' => $it['hostPageUrl'] ?? '',
            'domain'     => parse_url($it['hostPageUrl'] ?? '', PHP_URL_HOST) ?: '',
            'width'      => (int) ($it['width'] ?? 0),
            'height'     => (int) ($it['height'] ?? 0),
        ], $r->json('value') ?? []));
    }

    /** Drop entries without an image URL and re-index. */
    private function normalizeCandidates(array $items): array
    {
        return array_values(array_filter($items, fn ($c) => ! empty($c['image_url'])));
    }

    /** Hard-deny irrelevant marketplaces/fashion/auto sources. */
    private function isDenied(string $domain, string $imageUrl): bool
    {
        $hay = mb_strtolower($domain . ' ' . $imageUrl);
        foreach (self::DENY_DOMAINS as $d) {
            if (str_contains($hay, $d)) {
                return true;
            }
        }
        return false;
    }

    /** Classify a candidate by trust: preferred (supplier) > trusted > untrusted. */
    private function domainTier(string $domain, string $imageUrl): string
    {
        $hay = mb_strtolower($domain . ' ' . $imageUrl);
        foreach (self::PREFERRED_DOMAINS as $d) {
            if (str_contains($hay, $d)) {
                return 'preferred';
            }
        }
        foreach (self::TRUSTED_DOMAINS as $d) {
            if (str_contains($hay, $d)) {
                return 'trusted';
            }
        }
        return 'untrusted';
    }

    // ── Validation & download ─────────────────────────────────────────────────────

    /**
     * @return array{ok:bool,reason:string,width:int,height:int,bytes:int,body:string}
     */
    private function validateImage(string $url, bool $skipUrlMarkers = false): array
    {
        $fail = fn ($reason) => ['ok' => false, 'reason' => $reason, 'width' => 0, 'height' => 0, 'bytes' => 0, 'body' => ''];

        if (! $skipUrlMarkers) {
            $lower = mb_strtolower($url);
            foreach (self::BAD_URL_MARKERS as $marker) {
                if (str_contains($lower, $marker)) {
                    return $fail('bad-marker:' . $marker);
                }
            }
        }

        try {
            $resp = Http::timeout(25)->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->withOptions(['verify' => false])->get($url);
        } catch (\Throwable $e) {
            return $fail('fetch-error');
        }

        if (! $resp->successful()) {
            return $fail('http-' . $resp->status());
        }

        $type = strtolower($resp->header('Content-Type') ?? '');
        if (! str_starts_with($type, 'image/')) {
            return $fail('not-image:' . ($type ?: 'unknown'));
        }

        $body  = $resp->body();
        $bytes = strlen($body);
        if ($bytes < $this->minBytes) {
            return $fail(sprintf('too-small:%dKB', (int) ($bytes / 1024)));
        }

        $info = @getimagesizefromstring($body);
        if ($info === false) {
            return $fail('undecodable');
        }
        [$w, $h] = $info;

        if ($w < $this->minWidth) {
            return $fail(sprintf('width-%d<%d', $w, $this->minWidth));
        }
        // Logos/banners/icons: extreme aspect ratio.
        if ($h > 0 && ($w / $h > 3 || $h / $w > 3)) {
            return $fail(sprintf('aspect-%dx%d', $w, $h));
        }

        return ['ok' => true, 'reason' => 'ok', 'width' => $w, 'height' => $h, 'bytes' => $bytes, 'body' => $body];
    }

    private function saveImage(string $body, string $url, ?string $slug, string $dir): string
    {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $ext = 'jpg';
        }
        $base     = Str::slug($slug ?: ('product-' . substr(md5($url), 0, 8))) ?: 'product';
        $filename = $base . '.' . $ext;
        $target   = $dir . DIRECTORY_SEPARATOR . $filename;

        if (file_put_contents($target, $body) === false) {
            throw new \RuntimeException('write failed: ' . $filename);
        }

        return $filename;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────────

    private function imagesMissing(?string $raw): bool
    {
        if ($raw === null || trim($raw) === '') {
            return true;
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return trim($raw) === '[]';
        }
        foreach ($decoded as $entry) {
            if (! is_string($entry)) {
                continue;
            }
            $e = trim($entry);
            if ($e !== '' && $e !== '[]' && $e !== 'null' && $e !== '""') {
                return false;
            }
        }
        return true;
    }

    private function logResult($product, string $query, string $source, string $url, string $result): void
    {
        Log::channel('stack')->info('enrich-images', [
            'product_id' => $product->id,
            'sku'        => $product->sku,
            'article'    => $product->supplier_article ?? null,
            'query'      => $query,
            'source'     => $source,
            'url'        => $url,
            'result'     => $result,
        ]);
    }
}

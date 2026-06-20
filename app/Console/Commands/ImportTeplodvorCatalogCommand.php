<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Full catalog sync from teplodvor.by for a given brand.
 *
 * Dry-run (report only):
 *   php artisan supplier:import-catalog-teplodvor --brand="Ariston"
 *
 * Apply (archive old + create new):
 *   php artisan supplier:import-catalog-teplodvor --brand="Ariston" --apply
 *
 * Apply with AI descriptions:
 *   php artisan supplier:import-catalog-teplodvor --brand="Ariston" --apply --with-ai
 */
class ImportTeplodvorCatalogCommand extends Command
{
    protected $signature = 'supplier:import-catalog-teplodvor
        {--brand=           : Brand name in our DB (required)}
        {--slug-filter=     : Slug substring to filter teplodvor URLs (defaults to brand name)}
        {--apply            : Apply changes (archive old, create new)}
        {--with-ai          : Generate AI short_description + content for new products}
        {--sleep=800        : Delay between HTTP requests (ms)}
        {--limit-new=       : Max new products to create}
        {--skip-archive     : Do not archive unmatched existing products}
        {--skip-new         : Do not create new products}
        {--min-score=0.70   : Minimum match score to consider existing product matched}
        {--fix-empty        : Re-scrape specs/images for brand products that are missing them}';

    protected $description = 'Archive obsolete products and import new catalog from teplodvor.by for a brand';

    private const INDEX_FILE = 'teplodvor_index.json';
    private const IMAGE_DIR  = 'img/products/teplodvor';

    // teplodvor URL path → our category_id
    private const CATEGORY_MAP = [
        'kotly/gazovye'                                              => 53,
        'kotly/tverdotoplivnye'                                      => 54,
        'kotly/elektricheskie'                                       => 55,
        'kotly/kondensatsionnye'                                     => 53,
        'vodonagrevateli/elektricheskie'                             => 98,
        'vodonagrevateli/elekricheskie'                              => 98,
        'vodonagrevateli/protochnye'                                 => 98,
        'vodonagrevateli/nakopitelnye'                               => 98,
        'vodonagrevateli/gazovye-kolonki'                            => 298,
        'vodonagrevateli/gazovye_kolonki'                            => 298,
        'vodonagrevateli/gazovye kolonki'                            => 298,
        'vodonagrevateli/gazovye'                                    => 298,
        'komplektuyuschie-otopleniya/dymokhody'                      => 57,
        'komplektuyuschie-otopleniya/avtomatika-i-termoregulyatory'  => 58,
        'komplektuyuschie-otopleniya/nasosy'                         => 60,
        'komplektuyuschie-otopleniya'                                => 195,
    ];

    private const SLUG_STOPWORDS = [
        'bez', 'dlya', 'so', 'na', 'po', 'iz', 'ot', 'ob', 'pri', 'ili', 'ne', 'do',
        'kotel', 'nasos', 'gazovyy', 'gazovaya', 'elektricheskiy', 'elektricheskaya',
        'nastennyi', 'nastennaya', 'nastennoe', 'napolnyi',
        'dvuhkonturnyi', 'odnokonturnyi', 'kondensat', 'kondensatsionnyy',
        'tverdotoplivnyy', 'tverdotoplivnyy',
        'vodogrevatel', 'vodonagrevateli', 'nakopitelnyy',
    ];

    private const SLUG_NORM = ['eco' => 'eko'];

    public function handle(): int
    {
        $brandName  = (string) $this->option('brand');
        $apply      = (bool)   $this->option('apply');
        $withAi     = (bool)   $this->option('with-ai');
        $sleep      = max(300, (int) $this->option('sleep'));
        $minScore   = (float)  $this->option('min-score');
        $limitNew   = $this->option('limit-new') ? (int) $this->option('limit-new') : PHP_INT_MAX;
        $skipArchive = (bool)  $this->option('skip-archive');
        $skipNew    = (bool)   $this->option('skip-new');
        $fixEmpty   = (bool)   $this->option('fix-empty');

        if (! $brandName) {
            $this->error('--brand is required');
            return self::FAILURE;
        }

        $brand = DB::table('brands')
            ->where('name', $brandName)
            ->orWhere('name', 'like', $brandName . '%')
            ->first();

        if (! $brand) {
            $this->error("Brand not found: {$brandName}");
            return self::FAILURE;
        }

        $slugFilter = strtolower((string) ($this->option('slug-filter') ?: Str::slug($brand->name)));
        $this->info("Brand: {$brand->name} (id={$brand->id}), teplodvor slug filter: \"{$slugFilter}\"");

        if ($fixEmpty) {
            return $this->runFixEmpty($brand, $slugFilter, $apply, $withAi, $sleep);
        }

        $this->line($apply ? '<fg=red;options=bold>APPLY MODE</>' : '<fg=yellow>DRY-RUN MODE</>');

        // ── Load index ────────────────────────────────────────────────────────────
        $indexPath = storage_path(self::INDEX_FILE);
        if (! file_exists($indexPath)) {
            $this->error('Index not found. Run: php artisan supplier:enrich-teplodvor --build-index');
            return self::FAILURE;
        }
        $fullIndex = json_decode((string) file_get_contents($indexPath), true) ?? [];

        // Filter teplodvor URLs for this brand — only actual product pages:
        // 1. slug must contain the brand filter substring
        // 2. URL must have exactly 3 path segments after /shop/ (depth = product, not category)
        // 3. Last path segment must have >= 5 hyphen-delimited tokens (product slugs are long;
        //    category filter pages like "ariston-30-litrov" have only 3 tokens)
        $brandIndex = array_filter($fullIndex, function ($url, $slug) use ($slugFilter) {
            if (! str_contains($slug, $slugFilter)) {
                return false;
            }
            // Count path depth after /shop/
            $path = parse_url($url, PHP_URL_PATH) ?? '';
            $segments = array_values(array_filter(explode('/', trim($path, '/'))));
            // Must be: shop / cat / subcat / product-slug  (4 segments total)
            if (count($segments) !== 4 || $segments[0] !== 'shop') {
                return false;
            }
            // Last segment (product slug) must be long enough to be a real product
            $lastSlug  = $segments[3];
            $tokenCount = count(explode('-', $lastSlug));
            return $tokenCount >= 5;
        }, ARRAY_FILTER_USE_BOTH);
        $this->info(sprintf('Teplodvor catalog: %d product URLs for "%s"', count($brandIndex), $slugFilter));

        // ── Load existing DB products ─────────────────────────────────────────────
        $dbProducts = DB::table('products')
            ->where('brand_id', $brand->id)
            ->where('is_archived', false)
            ->get(['id', 'name', 'slug', 'price', 'category_id'])
            ->keyBy('id');

        $this->info(sprintf('Existing active products in DB: %d', $dbProducts->count()));

        // ── Match DB products to teplodvor URLs ───────────────────────────────────
        // For each DB product find the best matching teplodvor slug
        $matched    = [];   // db_id => teplodvor_slug
        $unmatched  = [];   // db_ids with no teplodvor match → archive candidates
        $tepMatched = [];   // teplodvor slugs already matched to a DB product

        foreach ($dbProducts as $dbId => $dbProd) {
            $bestScore = 0.0;
            $bestSlug  = null;
            $dbTokens  = $this->tokenize((string) $dbProd->slug, []);
            $brandTokens = array_values(array_filter(
                explode('-', strtolower(Str::slug((string) $brand->name))),
                fn ($t) => strlen($t) >= 2
            ));

            foreach ($brandIndex as $tepSlug => $tepUrl) {
                $score = $this->score($dbTokens, $tepSlug, $brandTokens);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestSlug  = $tepSlug;
                }
            }

            if ($bestScore >= $minScore && $bestSlug) {
                $matched[$dbId] = $bestSlug;
                $tepMatched[$bestSlug] = $dbId;
            } else {
                $unmatched[] = $dbProd;
            }
        }

        // New teplodvor products (no matching DB product)
        $newTepProducts = array_filter(
            $brandIndex,
            fn ($url, $slug) => ! isset($tepMatched[$slug]),
            ARRAY_FILTER_USE_BOTH
        );

        // ── Report ────────────────────────────────────────────────────────────────
        $this->newLine();
        $this->info(sprintf('Matched existing: %d | To archive: %d | New from teplodvor: %d',
            count($matched), count($unmatched), count($newTepProducts)));

        if (! empty($unmatched)) {
            $this->newLine();
            $this->warn('--- TO ARCHIVE (no teplodvor match) ---');
            foreach ($unmatched as $p) {
                $this->line(sprintf('  [%d] %s', $p->id, mb_substr($p->name, 0, 70)));
            }
        }

        $this->newLine();
        $this->line(sprintf('--- NEW FROM TEPLODVOR (%d) ---', count($newTepProducts)));
        foreach (array_slice($newTepProducts, 0, 20) as $slug => $url) {
            $this->line('  ' . $url);
        }
        if (count($newTepProducts) > 20) {
            $this->line('  ... and ' . (count($newTepProducts) - 20) . ' more');
        }

        if (! $apply) {
            $this->newLine();
            $this->line('Re-run with --apply to archive ' . count($unmatched) . ' products and import ' . count($newTepProducts) . ' new ones.');
            return self::SUCCESS;
        }

        // ── APPLY: archive unmatched ──────────────────────────────────────────────
        if (! $skipArchive && ! empty($unmatched)) {
            $this->newLine();
            $this->warn('Archiving ' . count($unmatched) . ' obsolete products...');
            foreach ($unmatched as $p) {
                // Free the slug so a newly imported product can claim the clean URL
                DB::table('products')->where('id', $p->id)->update([
                    'is_archived'         => true,
                    'availability_status' => 'check',
                    'slug'                => $p->slug . '-archived-' . $p->id,
                    'updated_at'          => now(),
                ]);
            }
            $this->info('  Archived: ' . count($unmatched));
        }

        // ── APPLY: import new products ────────────────────────────────────────────
        if (! $skipNew && ! empty($newTepProducts)) {
            $this->newLine();
            $this->info('Importing ' . min(count($newTepProducts), $limitNew) . ' new products...');
            $ai      = $withAi ? new \App\Services\AiContentEnricher : null;
            $created = 0;

            foreach ($newTepProducts as $tepSlug => $tepUrl) {
                if ($created >= $limitNew) {
                    break;
                }

                $this->line('  → ' . $tepUrl);
                $card = $this->scrapePage($tepUrl);

                if (! $card || ! $card['name']) {
                    $this->line('    <fg=red>scrape failed</>');
                    usleep($sleep * 1000);
                    continue;
                }

                $categoryId = $this->detectCategory($tepUrl);
                $productSlug = $this->makeSlug($card['name'], (int) $brand->id);

                // Skip if slug already exists
                if (DB::table('products')->where('slug', $productSlug)->exists()) {
                    $this->line('    <fg=yellow>slug exists, skip</>');
                    usleep($sleep * 1000);
                    continue;
                }

                // Generate AI content if requested
                $shortDesc = null;
                $content   = null;
                if ($ai && $ai->isAvailable() && ! empty($card['specs'])) {
                    $seo = $ai->generateSeo($card['name'], $brand->name, $card['category_hint'] ?? '', $card['specs']);
                    if ($seo) {
                        $shortDesc = $seo['short'] ?? null;
                        $content   = $seo['content'] ?? null;
                    }
                }

                // Insert product
                $newId = DB::table('products')->insertGetId([
                    'name'                => $card['name'],
                    'slug'                => $productSlug,
                    'sku'                 => 'PS-000.' . (DB::table('products')->max('id') + 1),
                    'price'               => $card['price'] ?? 0,
                    'brand_id'            => $brand->id,
                    'category_id'         => $categoryId,
                    'images'              => '[]',
                    'specs'               => $card['specs'] ? json_encode($card['specs'], JSON_UNESCAPED_UNICODE) : null,
                    'service_info'        => !empty($card['service_info']) ? json_encode($card['service_info'], JSON_UNESCAPED_UNICODE) : null,
                    'short_description'   => $shortDesc,
                    'content'             => $content,
                    'availability_status' => $card['price'] > 0 ? 'in_stock' : 'check',
                    'is_active'           => true,
                    'is_archived'         => false,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);

                // Update SKU with real ID
                DB::table('products')->where('id', $newId)->update(['sku' => sprintf('PS-000.%d', $newId)]);

                // Download images (all, up to 6)
                if (! empty($card['images'])) {
                    $this->downloadImage($newId, $card['images']);
                }

                $this->line(sprintf('    <fg=green>CREATED</> id=%d  %s  %.2f BYN',
                    $newId, mb_substr($card['name'], 0, 50), $card['price'] ?? 0));
                $created++;
                usleep($sleep * 1000);
            }

            $this->info("Created: {$created} new products");
        }

        return self::SUCCESS;
    }

    // ── Fix-empty mode ────────────────────────────────────────────────────────────

    private function runFixEmpty(object $brand, string $slugFilter, bool $apply, bool $withAi, int $sleep): int
    {
        $this->line($apply ? '<fg=red;options=bold>APPLY MODE — fix empty specs/images</>' : '<fg=yellow>DRY-RUN MODE</>');

        $indexPath = storage_path(self::INDEX_FILE);
        if (! file_exists($indexPath)) {
            $this->error('Index not found. Run supplier:enrich-teplodvor --build-index');
            return self::FAILURE;
        }
        $fullIndex = json_decode((string) file_get_contents($indexPath), true) ?? [];

        // All teplodvor URLs for this brand (product pages only)
        $brandUrls = array_filter($fullIndex, function ($url, $slug) use ($slugFilter) {
            if (! str_contains($slug, $slugFilter)) return false;
            $path = parse_url($url, PHP_URL_PATH) ?? '';
            $segs = array_values(array_filter(explode('/', trim($path, '/'))));
            return count($segs) === 4 && $segs[0] === 'shop' && count(explode('-', $segs[3])) >= 5;
        }, ARRAY_FILTER_USE_BOTH);

        // DB products missing specs, images, or service_info
        $dbProducts = DB::table('products')
            ->where('brand_id', $brand->id)
            ->where('is_archived', false)
            ->where(function ($q) {
                $q->whereNull('specs')->orWhere('specs', '')->orWhere('specs', '[]')
                  ->orWhereNull('images')->orWhere('images', '')->orWhere('images', '[]')
                  ->orWhereNull('service_info')->orWhere('service_info', '')->orWhere('service_info', '[]')->orWhere('service_info', '{}');
            })
            ->get(['id', 'name', 'slug', 'specs', 'images', 'service_info', 'short_description', 'content'])
            ->keyBy('slug');

        $this->info(sprintf('%d teplodvor URLs, %d products missing specs/images', count($brandUrls), count($dbProducts)));

        $ai = $withAi ? new \App\Services\AiContentEnricher() : null;
        $fixed   = 0;
        $seen    = [];  // avoid processing same product twice

        foreach ($brandUrls as $tSlug => $url) {
            // Find our product whose slug starts with or equals the teplodvor slug
            $matched = null;
            foreach ($dbProducts as $ourSlug => $product) {
                // Our slug is teplodvor slug OR teplodvor slug + "-{id}" suffix
                if ($ourSlug === $tSlug || str_starts_with($ourSlug, $tSlug)) {
                    $matched = $product;
                    break;
                }
            }

            if (! $matched) continue;
            if (isset($seen[$matched->id])) continue;
            $seen[$matched->id] = true;

            $hasSpecs   = ! empty($matched->specs) && $matched->specs !== '[]';
            $hasImages  = ! empty($matched->images) && $matched->images !== '[]';
            $hasService = ! empty($matched->service_info) && $matched->service_info !== '[]' && $matched->service_info !== '{}';
            if ($hasSpecs && $hasImages && $hasService) continue;

            $fixed++;
            $this->line(sprintf('  [id=%d] %s', $matched->id, mb_substr($matched->name, 0, 60)));
            $this->line(sprintf('    → %s', $url));

            if (! $apply) continue;

            $card = $this->scrapePage($url);
            if (! $card || (empty($card['specs']) && empty($card['images']) && empty($card['service_info']))) {
                $this->line('    <fg=yellow>SKIP</> — nothing scraped');
                usleep($sleep * 1000);
                continue;
            }

            $update = ['updated_at' => now()];

            if (! $hasSpecs && ! empty($card['specs'])) {
                $update['specs'] = json_encode($card['specs'], JSON_UNESCAPED_UNICODE);
                $this->line(sprintf('    specs: %d rows', count($card['specs'])));
            }
            if (! $hasImages && ! empty($card['images'])) {
                $this->downloadImage($matched->id, $card['images']);
                $this->line(sprintf('    images: downloaded %d', count($card['images'])));
            }
            if (! $hasService && ! empty($card['service_info'])) {
                $update['service_info'] = json_encode($card['service_info'], JSON_UNESCAPED_UNICODE);
                $this->line(sprintf('    service_info: %d fields', count($card['service_info'])));
            }

            // AI content if missing
            if ($withAi && $ai && $ai->isAvailable()
                && (empty($matched->short_description) || empty($matched->content))
                && ! empty($card['specs'])
            ) {
                $seo = $ai->generateSeo($matched->name, $brand->name, $card['category_hint'] ?? '', $card['specs']);
                if ($seo) {
                    if (empty($matched->short_description)) $update['short_description'] = $seo['short'] ?? null;
                    if (empty($matched->content))           $update['content'] = $seo['content'] ?? null;
                    $this->line('    ai: generated');
                }
            }

            if (count($update) > 1) {
                DB::table('products')->where('id', $matched->id)->update($update);
            }

            $fixed++;
            usleep($sleep * 1000);
        }

        $this->info($apply ? "Fixed: {$fixed}" : "Would fix: {$fixed} (re-run with --apply)");
        return self::SUCCESS;
    }

    // ── Scraping ──────────────────────────────────────────────────────────────────

    private function scrapePage(string $url): ?array
    {
        try {
            $html = Http::timeout(20)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; kotlov.by/1.0)'])
                ->get($url)
                ->body();
        } catch (\Throwable) {
            return null;
        }

        // Name
        preg_match('/<h1[^>]*>(.*?)<\/h1>/si', $html, $m);
        $name = trim(strip_tags($m[1] ?? ''));

        // Price
        $price = 0.0;
        if (preg_match('/itemprop=["\']price["\'][^>]+content=["\']([0-9.,]+)["\']/', $html, $m)) {
            $price = (float) str_replace(',', '.', $m[1]);
        } elseif (preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $jms)) {
            foreach ($jms[1] as $json) {
                $d = json_decode($json, true);
                if ($d && isset($d['offers']['price'])) {
                    $price = (float) $d['offers']['price'];
                    break;
                }
            }
        }

        // Specs & service info from ALL tables
        $specs       = [];
        $serviceInfo = [];
        preg_match_all('/<table[^>]*>([\s\S]*?)<\/table>/si', $html, $tables);
        foreach ($tables[1] as $tblHtml) {
            preg_match_all('/<tr[^>]*>\s*<td[^>]*>(.*?)<\/td>\s*<td[^>]*>(.*?)<\/td>/si', $tblHtml, $rows);
            foreach ($rows[1] as $i => $k) {
                $key = trim(strip_tags(html_entity_decode($k)));
                $val = trim(strip_tags(html_entity_decode($rows[2][$i])));
                if (! $key || ! $val) {
                    continue;
                }
                if (preg_match('/^(производитель(?!\p{L})|импортер(?!\p{L})|импортёр(?!\p{L})|сервисный\s+центр|страна\s+происхождения)/ui', $key)) {
                    $serviceInfo[$key] = $val;
                } else {
                    $specs[] = ['key' => $key, 'value' => $val, 'unit' => ''];
                }
            }
        }

        // Pass 3: catch full-address service info in free-text / single-cell format
        // e.g. "Производитель: Аристоне Термо, Виале Аристиде Мерлони 45, Фабриано 60044, Италия"
        $stripped = (string) preg_replace('/<(script|style|noscript)\b[\s\S]*?<\/\1>/iu', '', $html);
        $stripped = html_entity_decode(strip_tags($stripped), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        foreach (preg_split('/[\r\n]+/', $stripped) ?: [] as $line) {
            $line = trim((string) preg_replace('/\s+/', ' ', $line));
            if (preg_match(
                '/^(производитель\b[^:]{0,30}|импортер[ъ]?\b(?:\s+в\s+(?:рб|беларуси|белоруссии))?[^:]{0,20}|импортёр\b(?:\s+в\s+(?:рб|беларуси|белоруссии))?[^:]{0,20}|сервисный\s+центр[^:]{0,20}|страна\s+происхождения[^:]{0,20})\s*:\s*(.{20,})/ui',
                $line, $parts
            )) {
                $label = trim($parts[1]);
                $value = trim($parts[2]);
                if ($label && $value && (! isset($serviceInfo[$label]) || strlen($value) > strlen($serviceInfo[$label]))) {
                    $serviceInfo[$label] = $value;
                }
            }
        }

        // Images (large)
        preg_match_all('/userfls\/shop\/large\/[^\'"]+\.(jpg|jpeg|png)/i', $html, $imgs);
        $images = array_unique(array_map(
            fn ($p) => 'https://www.teplodvor.by/' . $p,
            $imgs[0]
        ));

        // Category hint from breadcrumb
        preg_match_all('/<a[^>]+href=["\'][^"\']+["\'][^>]*>(.*?)<\/a>/si', $html, $bc);
        $categoryHint = '';
        foreach ($bc[1] as $label) {
            $l = trim(strip_tags($label));
            if (strlen($l) > 3 && ! in_array($l, ['Главная', 'teplodvor.by', ''])) {
                $categoryHint = $l;
            }
        }

        return [
            'name'          => $name,
            'price'         => $price,
            'specs'         => $specs,
            'service_info'  => $serviceInfo,
            'images'        => array_values($images),
            'category_hint' => $categoryHint,
        ];
    }

    private function downloadImage(int $pid, array $imgUrls): void
    {
        try {
            $dir = public_path(self::IMAGE_DIR);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $saved   = [];
            $hashes  = [];
            $written = 0;

            foreach (array_slice($imgUrls, 0, 6) as $imgUrl) {
                $body = Http::timeout(15)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                    ->get($imgUrl)
                    ->body();

                if (strlen($body) < 3000) {
                    continue;
                }
                $size = @getimagesizefromstring($body);
                if (! $size || $size[0] < 150 || $size[1] < 150) {
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
                $file = "{$pid}_{$written}.{$ext}";
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
            }
        } catch (\Throwable) {
        }
    }

    // ── Category detection ────────────────────────────────────────────────────────

    private function detectCategory(string $url): int
    {
        // Extract /shop/cat1/cat2/ from URL
        if (preg_match('#/shop/([^/]+)/([^/]+)/#', $url, $m)) {
            $key = $m[1] . '/' . $m[2];
            if (isset(self::CATEGORY_MAP[$key])) {
                return self::CATEGORY_MAP[$key];
            }
        }
        if (preg_match('#/shop/([^/]+)/#', $url, $m)) {
            if (isset(self::CATEGORY_MAP[$m[1]])) {
                return self::CATEGORY_MAP[$m[1]];
            }
        }
        return 195; // fallback: Комплектующие для отопления
    }

    // ── Matching ──────────────────────────────────────────────────────────────────

    private function tokenize(string $slug, array $brandTokens): array
    {
        return array_values(array_map(
            fn ($t) => self::SLUG_NORM[$t] ?? $t,
            array_filter(
                explode('-', strtolower($slug)),
                fn ($t) => (strlen($t) >= 2 || ctype_digit($t))
                    && ! in_array($t, self::SLUG_STOPWORDS, true)
                    && ! array_filter($brandTokens, fn ($bt) => levenshtein($t, $bt) <= 1)
                    && ! (strlen($t) >= 8 && preg_match('/[a-z]/', $t) && preg_match('/\d/', $t))
            )
        ));
    }

    private function score(array $ourTokens, string $tepSlug, array $brandTokens): float
    {
        if (empty($ourTokens)) {
            return 0.0;
        }
        $normTep = str_replace(array_keys(self::SLUG_NORM), array_values(self::SLUG_NORM), $tepSlug);

        // Brand must appear in teplodvor slug
        if (! empty($brandTokens)) {
            $hasBrand = false;
            foreach ($brandTokens as $bt) {
                if (strlen($bt) >= 3 && str_contains($tepSlug, $bt)) {
                    $hasBrand = true;
                    break;
                }
            }
            if (! $hasBrand) {
                return 0.0;
            }
        }

        $numerics   = array_filter($ourTokens, 'ctype_digit');
        $numConcat  = count($numerics) >= 2 ? implode('', array_values($numerics)) : null;
        $numConcatUsed = false;

        foreach ($numerics as $num) {
            if (preg_match('/(?:^|-)' . preg_quote($num, '/') . '(?:-|$)/', $normTep)) {
                continue;
            }
            if ($numConcat && preg_match('/(?:^|-)' . preg_quote($numConcat, '/') . '(?:-|$)/', $normTep)) {
                $numConcatUsed = true;
                continue;
            }
            return 0.0;
        }

        $total   = array_sum(array_map('strlen', $ourTokens));
        $matched = 0;
        foreach ($ourTokens as $t) {
            $hit = (ctype_digit($t) && $numConcatUsed)
                || (bool) preg_match('/(?:^|-)' . preg_quote($t, '/') . '(?:-|$)/', $normTep);
            if ($hit) {
                $matched += strlen($t);
            }
        }

        return $total > 0 ? $matched / $total : 0.0;
    }

    // ── Slug generation ───────────────────────────────────────────────────────────

    private function makeSlug(string $name, int $brandId): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $n    = 1;
        // Only check active (non-archived) products — archived slugs are renamed to slug-archived-{id}
        while (DB::table('products')->where('slug', $slug)->where('is_archived', false)->exists()) {
            $slug = $base . '-' . $n++;
        }
        return $slug;
    }
}

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
        {--min-score=0.70   : Minimum match score to consider existing product matched}';

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
            $ids = array_column($unmatched, 'id');
            DB::table('products')->whereIn('id', $ids)->update([
                'is_archived'         => true,
                'availability_status' => 'check',
                'updated_at'          => now(),
            ]);
            $this->info('  Archived: ' . count($ids));
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

                // Download image
                if (! empty($card['images'])) {
                    $this->downloadImage($newId, $card['images'][0]);
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

        // Specs (td/td table)
        $specs = [];
        preg_match('/<table>(.*?)<\/table>/si', $html, $tbl);
        if ($tbl) {
            preg_match_all('/<tr[^>]*>\s*<td[^>]*>(.*?)<\/td>\s*<td[^>]*>(.*?)<\/td>/si', $tbl[1], $rows);
            foreach ($rows[1] as $i => $k) {
                $key = trim(strip_tags(html_entity_decode($k)));
                $val = trim(strip_tags(html_entity_decode($rows[2][$i])));
                if ($key && $val && $key !== 'Производитель') {
                    $specs[] = ['key' => $key, 'value' => $val, 'unit' => ''];
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
            'images'        => array_values($images),
            'category_hint' => $categoryHint,
        ];
    }

    private function downloadImage(int $pid, string $imgUrl): void
    {
        try {
            $dir = public_path(self::IMAGE_DIR);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $body = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->get($imgUrl)
                ->body();

            if (strlen($body) < 3000) {
                return;
            }
            $size = @getimagesizefromstring($body);
            if (! $size || $size[0] < 150 || $size[1] < 150) {
                return;
            }
            $ext  = match ($size['mime'] ?? '') {
                'image/png'  => 'png',
                'image/webp' => 'webp',
                default      => 'jpg',
            };
            $file = "{$pid}_0.{$ext}";
            file_put_contents("{$dir}/{$file}", $body);
            DB::table('products')->where('id', $pid)->update([
                'images'     => json_encode([self::IMAGE_DIR . '/' . $file], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
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
        while (DB::table('products')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $n++;
        }
        return $slug;
    }
}

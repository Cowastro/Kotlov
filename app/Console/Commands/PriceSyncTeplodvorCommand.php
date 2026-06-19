<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Sync prices from teplodvor.by for products that have no supplier linked.
 *
 * Dry-run (show only):
 *   php artisan supplier:sync-prices-teplodvor --brand="BAXI"
 *
 * Apply prices:
 *   php artisan supplier:sync-prices-teplodvor --brand="BAXI" --apply
 *
 * All unlinked products at once:
 *   php artisan supplier:sync-prices-teplodvor --apply
 */
class PriceSyncTeplodvorCommand extends Command
{
    protected $signature = 'supplier:sync-prices-teplodvor
        {--brand=          : Filter by brand name}
        {--apply           : Write prices to DB (default: dry-run)}
        {--min-score=0.75  : Minimum match score (0–1)}
        {--sleep=600       : Delay between HTTP requests (ms)}
        {--limit=          : Max products to process}
        {--with-specs      : Only update products that already have specs saved (safer — avoids false matches)}
        {--ratio=1.0       : Price multiplier applied to teplodvor price (e.g. 0.95 for 5% below market)}';

    protected $description = 'Pull prices from teplodvor.by for products without a supplier';

    private const INDEX_FILE = 'teplodvor_index.json';

    private const SLUG_STOPWORDS = [
        'bez', 'dlya', 'so', 'na', 'po', 'iz', 'ot', 'ob', 'pri', 'ili', 'ne', 'do', 'ko',
        'nasos', 'nasosnaya', 'stantsiya', 'klapan', 'schetchik',
        'kotel', 'pech', 'otopitelnaya', 'otopitelnyj',
        'tverdotoplivnyj', 'tverdotoplivnyy',
        'elektricheskij', 'elektricheskiy', 'elektricheskaya',
        'gazovyj', 'gazovaya', 'gazovyy',
        'antratsit', 'antracit', 'antrocit',
        'belyj', 'chernyj', 'seryj', 'serebristyj', 'metallik',
        'terrakota', 'bronza', 'vitra',
        'layt', 'lajt',
        'chd', 'sk', 'tv', 'tz', 'sd', 'zg', 'nv', 'ds', 'dch',
    ];

    private const SLUG_NORM = ['eco' => 'eko'];

    public function handle(): int
    {
        $apply    = (bool) $this->option('apply');
        $minScore = (float) $this->option('min-score');
        $sleep    = max(200, (int) $this->option('sleep'));
        $limit    = $this->option('limit') ? (int) $this->option('limit') : PHP_INT_MAX;
        $ratio    = max(0.1, min(10.0, (float) $this->option('ratio')));

        $this->line($apply
            ? '<fg=red;options=bold>APPLY — prices will be updated.</>'
            : '<fg=yellow;options=bold>DRY RUN — no changes will be written.</>');
        if ($ratio !== 1.0) {
            $this->line(sprintf('<fg=cyan>Price ratio: ×%.2f (teplodvor × ratio = final price)</>', $ratio));
        }

        // ── Load slug index ───────────────────────────────────────────────────────
        $indexPath = storage_path(self::INDEX_FILE);
        if (! file_exists($indexPath)) {
            $this->error('Slug index not found. Run: php artisan supplier:enrich-teplodvor --build-index');
            return self::FAILURE;
        }
        $index = json_decode((string) file_get_contents($indexPath), true) ?? [];
        $this->info(sprintf('Slug index: %d teplodvor.by product URLs', count($index)));

        // ── Products without any supplier link ────────────────────────────────────
        $query = DB::table('products')
            ->where('is_archived', false)
            ->whereNotNull('slug')->where('slug', '!=', '')
            ->whereNotIn('id', function ($sub) {
                $sub->select('product_id')->from('supplier_products');
            });

        if ($this->option('with-specs')) {
            $query->whereNotNull('specs')->where('specs', '!=', '')->where('specs', '!=', '{}');
        }

        $brandSlugTokens = [];
        if ($this->option('brand')) {
            $brandName = $this->option('brand');
            $brandId   = DB::table('brands')->where('name', $brandName)->value('id')
                ?? DB::table('brands')->where('name', 'like', $brandName . '%')->value('id')
                ?? DB::table('brands')->where('name', 'like', '%' . $brandName . '%')->value('id');
            if (! $brandId) {
                $this->error("Brand not found: {$brandName}");
                return self::FAILURE;
            }
            $brandName = DB::table('brands')->where('id', $brandId)->value('name');
            $query->where('brand_id', $brandId);
            $this->info("Brand filter: {$brandName} (id={$brandId})");
            $brandSlugTokens = array_values(array_filter(
                explode('-', strtolower(Str::slug((string) $brandName))),
                fn ($t) => strlen($t) >= 2
            ));
        }

        $products = $query->get(['id', 'name', 'slug', 'price']);
        $this->info(sprintf('Products to process: %d (no supplier)', $products->count()));

        // ── Process ───────────────────────────────────────────────────────────────
        $stats = ['scanned' => 0, 'matched' => 0, 'price_found' => 0, 'updated' => 0, 'no_match' => 0, 'no_price' => 0];
        $rows  = [];

        foreach ($products as $product) {
            if ($stats['scanned'] >= $limit) {
                break;
            }
            $stats['scanned']++;

            $url = $this->findMatch((string) $product->slug, $index, $minScore, $brandSlugTokens);

            if (! $url) {
                $this->line(sprintf('  [NO MATCH] %s', mb_substr($product->name, 0, 70)));
                $stats['no_match']++;
                continue;
            }

            $stats['matched']++;
            $this->line(sprintf('  [MATCH] %s', mb_substr($product->name, 0, 60)));
            $this->line('    → ' . $url);

            $price = $this->scrapePrice($url);

            if ($price === null || $price <= 0) {
                $this->line('    price: <fg=red>not found</>');
                $stats['no_price']++;
                usleep($sleep * 1000);
                continue;
            }

            $stats['price_found']++;
            $oldPrice   = $product->price;
            $finalPrice = round($price * $ratio, 2);
            $this->line(sprintf(
                '    teplodvor: %.2f BYN → <fg=green>final: %.2f BYN</> (was: %s)',
                $price,
                $finalPrice,
                $oldPrice > 0 ? number_format((float) $oldPrice, 2) . ' BYN' : 'not set'
            ));

            $rows[] = [
                mb_substr($product->name, 0, 55),
                $oldPrice > 0 ? number_format((float) $oldPrice, 2) : '—',
                number_format($finalPrice, 2),
                $url,
            ];

            if ($apply) {
                DB::table('products')
                    ->where('id', $product->id)
                    ->update(['price' => $finalPrice, 'updated_at' => now()]);
                $stats['updated']++;
            }

            usleep($sleep * 1000);
        }

        // ── Summary ───────────────────────────────────────────────────────────────
        if (! empty($rows)) {
            $this->newLine();
            $this->table(['Product', 'Old price', 'New price (BYN)', 'Source URL'], $rows);
        }

        $this->newLine();
        $this->table(
            ['metric', 'count'],
            array_map(fn ($k, $v) => [$k, $v], array_keys($stats), array_values($stats))
        );

        if (! $apply && $stats['price_found'] > 0) {
            $this->info(sprintf(
                'Re-run with --apply to update %d prices%s.',
                $stats['price_found'],
                $ratio !== 1.0 ? sprintf(' (×%.2f ratio applied)', $ratio) : ''
            ));
        }

        return self::SUCCESS;
    }

    // ── Price scraping ────────────────────────────────────────────────────────────

    private function scrapePrice(string $url): ?float
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; kotlov.by/1.0)'])
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            $html = $response->body();

            // 1. JSON-LD (most reliable)
            if (preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $m)) {
                foreach ($m[1] as $json) {
                    $data = json_decode($json, true);
                    if (! $data) {
                        continue;
                    }
                    $items = isset($data['@graph']) ? $data['@graph'] : [$data];
                    foreach ($items as $item) {
                        foreach (['offers', 'Offers'] as $key) {
                            if (! isset($item[$key])) {
                                continue;
                            }
                            $offers = $item[$key];
                            $p = isset($offers['price'])
                                ? (float) $offers['price']
                                : (isset($offers[0]['price']) ? (float) $offers[0]['price'] : 0);
                            if ($p > 0) {
                                return $p;
                            }
                        }
                    }
                }
            }

            // 2. itemprop="price" content="..."
            if (preg_match('/itemprop=["\']price["\'][^>]+content=["\']([0-9.,]+)["\']/', $html, $m)) {
                $p = (float) str_replace(',', '.', $m[1]);
                if ($p > 0) {
                    return $p;
                }
            }

            // 3. meta product:price:amount
            if (preg_match('/<meta[^>]+property=["\']product:price:amount["\'][^>]+content=["\']([0-9.,]+)["\']/', $html, $m)) {
                $p = (float) str_replace(',', '.', $m[1]);
                if ($p > 0) {
                    return $p;
                }
            }

            // 4. data-price attribute
            if (preg_match('/data-price=["\']([0-9.,]+)["\']/', $html, $m)) {
                $p = (float) str_replace(',', '.', $m[1]);
                if ($p > 0) {
                    return $p;
                }
            }

            // 5. Common CSS price class patterns (teplodvor.by specific)
            $cssPatterns = [
                '/<[^>]+class=["\'][^"\']*b-product-buy__price[^"\']*["\'][^>]*>\s*([0-9\s]+[.,]?\d*)/i',
                '/<[^>]+class=["\'][^"\']*product-price[^"\']*["\'][^>]*>\s*([0-9\s]+[.,]?\d*)/i',
                '/<[^>]+class=["\'][^"\']*price-value[^"\']*["\'][^>]*>\s*([0-9\s]+[.,]?\d*)/i',
            ];
            foreach ($cssPatterns as $pattern) {
                if (preg_match($pattern, $html, $m)) {
                    $p = (float) str_replace([' ', ',', "\xc2\xa0"], ['', '.', ''], $m[1]);
                    if ($p > 0) {
                        return $p;
                    }
                }
            }

        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    // ── Matching (mirrors EnrichTeplodvorCommand::findMatch) ──────────────────────

    private function findMatch(string $ourSlug, array $index, float $minScore, array $brandTokens = []): ?string
    {
        $ourTokens = array_values(array_map(
            fn ($t) => self::SLUG_NORM[$t] ?? $t,
            array_filter(
                explode('-', strtolower($ourSlug)),
                fn ($t) => (strlen($t) >= 2 || ctype_digit($t))
                    && ! in_array($t, self::SLUG_STOPWORDS, true)
                    && ! array_filter($brandTokens, fn ($bt) => levenshtein($t, $bt) <= 1)
            )
        ));

        if (count($ourTokens) < 2) {
            return null;
        }

        $requiredNumerics = array_values(array_filter($ourTokens, 'ctype_digit'));
        $totalWeight      = array_sum(array_map('strlen', $ourTokens));

        if ($totalWeight === 0) {
            return null;
        }

        $bestScore = 0.0;
        $bestUrl   = null;

        foreach ($index as $tepSlug => $url) {
            foreach ($requiredNumerics as $num) {
                if (! str_contains($tepSlug, $num)) {
                    continue 2;
                }
            }

            $matchedWeight = 0;
            foreach ($ourTokens as $t) {
                if (str_contains($tepSlug, $t)) {
                    $matchedWeight += strlen($t);
                }
            }

            $score = $matchedWeight / $totalWeight;
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestUrl   = $url;
            }
        }

        return ($bestScore >= $minScore) ? $bestUrl : null;
    }
}

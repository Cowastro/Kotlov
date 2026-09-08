<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Site-wide broken-image audit. Mirrors Product::imageUrl()'s own path
 * resolution exactly (see app/Models/Product.php) so "broken" here means
 * the same thing a real page visit would show — not just an HTTP status,
 * because /proxy-image/{path} always returns 200 with a placeholder on a
 * missing legacy file (silent fallback, see routes/web.php) rather than
 * 404ing. So each image path is checked against the actual underlying
 * file/URL its resolution scheme implies:
 *   - http(s)://...            -> real HTTP HEAD check (external hotlink)
 *   - img/... or /img/...      -> public_path() file_exists
 *   - products/...             -> Storage::disk('public')->exists()  (new-style upload)
 *   - product/...              -> public_path('images/'.path) file_exists (legacy rsync)
 *   - path with >=2 slashes    -> public_path('images/product/'.path) file_exists
 *   - bare filename (SKU/id-derived proxy path) -> public_path('images/product/{dir1}/{dir2}/'.path)
 *
 * Writes the full broken list to public/exports/broken-images-{date}.ndjson
 * (avoids GH Actions log secret-masking corruption on large numeric output)
 * — download over plain HTTPS, then delete via --delete-export.
 *
 *   php artisan debug:find-broken-images                       # count only, fast, no export
 *   php artisan debug:find-broken-images --export               # + write ndjson
 *   php artisan debug:find-broken-images --limit=500             # cap products scanned (testing)
 *   php artisan debug:find-broken-images --delete-export=FILE    # remove a written export
 */
class FindBrokenProductImagesCommand extends Command
{
    protected $signature = 'debug:find-broken-images
        {--limit=0 : Cap number of products scanned (0 = all active)}
        {--export : Write full broken list to public/exports/*.ndjson}
        {--delete-export= : Delete a previously written export file (filename only)}
        {--clear-broken : Strip confirmed-broken paths out of products.images (a product falls back to the placeholder image once none remain) — preview only unless --apply is also given}
        {--apply : Write the --clear-broken changes to the database}';

    protected $description = 'Site-wide check of every active product image against its actual resolved file/URL (not just HTTP status)';

    public function handle(): int
    {
        if ($del = $this->option('delete-export')) {
            $path = public_path('exports/' . basename($del));
            if (file_exists($path)) {
                unlink($path);
                $this->info("deleted: {$path}");
            } else {
                $this->warn("not found: {$path}");
            }
            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');
        $export = (bool) $this->option('export');

        $query = DB::table('products')
            ->where('is_archived', false)
            ->whereNotNull('images')
            ->where('images', '!=', '')
            ->where('images', '!=', '[]')
            ->orderBy('id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $products = $query->get(['id', 'sku', 'name', 'images']);
        $this->info(sprintf('Scanning %d active products with images...', $products->count()));

        $totalImages = 0;
        $brokenCount = 0;
        $productsWithBroken = 0;
        $brokenRows = [];

        // batch http(s) checks concurrently for speed
        $httpTargets = []; // path => [productId, index]

        $resolved = []; // productId => [ [path, scheme, checkTarget], ... ]
        $imagesByProduct = []; // productId => decoded images array, for --clear-broken

        foreach ($products as $p) {
            $images = json_decode((string) $p->images, true);
            if (!is_array($images) || empty($images)) {
                continue;
            }
            $imagesByProduct[$p->id] = $images;
            foreach ($images as $idx => $path) {
                if (!is_string($path) || $path === '') {
                    continue;
                }
                $totalImages++;

                if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                    $httpTargets[$path][] = [$p->id, $p->sku, $p->name, $idx];
                    continue;
                }

                if (str_starts_with($path, 'img/') || str_starts_with($path, '/img/')) {
                    $fp = public_path(ltrim($path, '/'));
                    if (!file_exists($fp)) {
                        $this->recordBroken($brokenRows, $p, $idx, $path, 'img-asset', $fp);
                    }
                    continue;
                }

                if (str_starts_with($path, 'products/')) {
                    if (!Storage::disk('public')->exists($path)) {
                        $this->recordBroken($brokenRows, $p, $idx, $path, 'storage-upload', 'storage/public/' . $path);
                    }
                    continue;
                }

                if (str_starts_with($path, 'product/')) {
                    $fp = public_path('images/' . $path);
                    if (!file_exists($fp)) {
                        $this->recordBroken($brokenRows, $p, $idx, $path, 'legacy-proxy', $fp);
                    }
                    continue;
                }

                if (substr_count($path, '/') >= 2) {
                    $fp = public_path('images/product/' . $path);
                    if (!file_exists($fp)) {
                        $this->recordBroken($brokenRows, $p, $idx, $path, 'legacy-proxy-2slash', $fp);
                    }
                    continue;
                }

                // bare filename -> derive dir1/dir2 from SKU then id, same as imageUrl()
                $dir = $this->deriveProxyDir($p->sku, $p->id);
                $fp = public_path('images/product/' . $dir . '/' . $path);
                if (!file_exists($fp)) {
                    $this->recordBroken($brokenRows, $p, $idx, $path, 'legacy-proxy-bare', $fp);
                }
            }
        }

        // now the http(s) targets, concurrently
        if (!empty($httpTargets)) {
            $urls = array_keys($httpTargets);
            $this->info(sprintf('Checking %d external image URLs...', count($urls)));
            $chunks = array_chunk($urls, 20, true);
            foreach ($chunks as $chunk) {
                $responses = Http::pool(fn ($pool) => array_map(
                    fn ($u) => $pool->timeout(8)->head($u),
                    $chunk
                ));
                foreach ($chunk as $i => $url) {
                    $resp = $responses[$i] ?? null;
                    $ok = $resp && !($resp instanceof \Throwable) && method_exists($resp, 'successful') && $resp->successful();
                    if (!$ok) {
                        foreach ($httpTargets[$url] as [$pid, $sku, $name, $idx]) {
                            $brokenRows[] = [
                                'product_id' => $pid, 'sku' => $sku, 'name' => $name,
                                'index' => $idx, 'path' => $url, 'scheme' => 'external-url',
                                'check_target' => $url,
                            ];
                        }
                    }
                }
            }
        }

        $brokenCount = count($brokenRows);
        $productsWithBroken = count(array_unique(array_column($brokenRows, 'product_id')));

        $this->info(sprintf(
            'total_images_checked=%d broken=%d products_affected=%d',
            $totalImages,
            $brokenCount,
            $productsWithBroken
        ));

        // scheme breakdown
        $byScheme = [];
        foreach ($brokenRows as $r) {
            $byScheme[$r['scheme']] = ($byScheme[$r['scheme']] ?? 0) + 1;
        }
        foreach ($byScheme as $scheme => $cnt) {
            $this->line("  {$scheme}: {$cnt}");
        }

        if ($this->option('clear-broken') && $brokenCount > 0) {
            $this->clearBroken($brokenRows, $imagesByProduct, (bool) $this->option('apply'));
        }

        if ($export && $brokenCount > 0) {
            $dir = public_path('exports');
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $fname = 'broken-images-' . now()->format('Y-m-d_His') . '.ndjson';
            $fpath = $dir . '/' . $fname;
            $fh = fopen($fpath, 'w');
            foreach ($brokenRows as $r) {
                fwrite($fh, json_encode($r, JSON_UNESCAPED_UNICODE) . "\n");
            }
            fclose($fh);
            $this->info("exported: public/exports/{$fname} ({$brokenCount} rows)");
        }

        return self::SUCCESS;
    }

    /**
     * Remove confirmed-broken paths from products.images so a visitor never
     * sees a broken <img> — Product::imageUrl() already falls back to the
     * placeholder cleanly once the array is empty or the requested index is
     * missing. This never deletes information for entries a later automated
     * or manual fix could still recover (nothing on disk is touched, and the
     * source path is only dropped from THIS product's own array — a re-run
     * of the relevant supplier sync just repopulates it normally).
     */
    private function clearBroken(array $brokenRows, array $imagesByProduct, bool $apply): void
    {
        $byProduct = [];
        foreach ($brokenRows as $r) {
            $byProduct[$r['product_id']][] = $r['index'];
        }

        $this->newLine();
        $this->info(sprintf(
            'clear-broken: %d products have at least one broken image path.',
            count($byProduct)
        ));

        $willBeEmptied = 0;
        $willBePartial = 0;
        $updated = 0;

        foreach ($byProduct as $productId => $brokenIndexes) {
            $images = $imagesByProduct[$productId] ?? null;
            if (!is_array($images)) {
                continue;
            }

            $cleaned = array_values(array_diff_key($images, array_flip($brokenIndexes)));

            if ($cleaned === []) {
                $willBeEmptied++;
            } else {
                $willBePartial++;
            }

            if ($apply) {
                DB::table('products')->where('id', $productId)->update([
                    'images' => json_encode($cleaned, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
                $updated++;
            }
        }

        if (!$apply) {
            $this->line("  would fall back to placeholder (all images were broken): {$willBeEmptied}");
            $this->line("  would keep remaining valid images, just drop the broken one(s): {$willBePartial}");
            $this->line('  (dry run — pass --apply to write)');
            return;
        }

        $this->info("clear-broken: updated {$updated} products ({$willBeEmptied} now show the placeholder, {$willBePartial} kept their other valid images).");
    }

    private function recordBroken(array &$rows, object $p, int $idx, string $path, string $scheme, string $target): void
    {
        $rows[] = [
            'product_id' => $p->id, 'sku' => $p->sku, 'name' => $p->name,
            'index' => $idx, 'path' => $path, 'scheme' => $scheme,
            'check_target' => $target,
        ];
    }

    private function deriveProxyDir(?string $sku, int $id): string
    {
        $skuParts = explode('.', $sku ?? '');
        $firstRaw = explode('-', $skuParts[0] ?? '')[1] ?? null;
        $secondRaw = $skuParts[1] ?? null;

        if ($firstRaw !== null && $secondRaw !== null && is_numeric($firstRaw) && is_numeric($secondRaw)) {
            $n1 = (int) $firstRaw;
            $dir1 = sprintf('00%d', $n1);
            $dir2 = sprintf('%s%03d', str_pad((string) $n1, 3, '0', STR_PAD_LEFT), (int) $secondRaw);
            return $dir1 . '/' . $dir2;
        }

        $n1 = (int) floor($id / 1000);
        $dir1 = sprintf('00%d', $n1);
        $dir2 = str_pad((string) $id, 6, '0', STR_PAD_LEFT);
        return $dir1 . '/' . $dir2;
    }
}

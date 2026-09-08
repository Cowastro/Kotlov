<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Cache-busting follow-up to the supplier:sync-belkomin-tis-boilers badge
 * fix (--purge-badge-images): the badge purge+redownload reused the same
 * public path whenever a product's real first photo also happens to be
 * .webp (same extension the badge always had), so the URL
 * img/products/belkomin-tis/{article}-1.webp is identical before and
 * after the fix — only the bytes on disk changed. Any browser or
 * intermediate cache that already fetched that URL (Cache-Control:
 * max-age=2592000, 30 days) keeps serving the old "4% credit" badge
 * bytes until that cache naturally expires, even though the server now
 * returns the correct file.
 *
 * Renames every affected file to a cache-busted name (adds a content
 * hash) and updates products.images to match, forcing every client to
 * request a fresh URL regardless of what they had cached.
 *
 *   php artisan debug:fix-belkomin-cache-collision --apply
 */
class FixBelkominBadgeCacheCommand extends Command
{
    protected $signature = 'debug:fix-belkomin-cache-collision {--apply : Write changes (default: dry-run)}';

    protected $description = 'Rename belkomin-tis first-slot images that reused the badge\'s old .webp filename, to bust stale 30-day caches';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $dir = public_path('img/products/belkomin-tis');

        $products = DB::table('products as p')
            ->join('supplier_products as sp', 'p.id', '=', 'sp.product_id')
            ->join('suppliers as s', 'sp.supplier_id', '=', 's.id')
            ->where('s.code', 'belkomin')
            ->whereNotNull('p.images')
            ->select('p.id', 'p.images')
            ->get();

        $this->info("Checking {$products->count()} belkomin products...");

        $affected = 0;
        $renamed = 0;

        foreach ($products as $p) {
            $images = json_decode((string) $p->images, true);
            if (! is_array($images) || $images === []) {
                continue;
            }

            $first = $images[0] ?? null;
            if (! is_string($first) || ! preg_match('#^img/products/belkomin-tis/(.+)-1\.webp$#', $first, $m)) {
                continue; // extension already differs from the badge's .webp -> URL already changed, safe
            }

            $affected++;
            $article = $m[1];
            $srcPath = public_path($first);
            if (! file_exists($srcPath)) {
                $this->warn("  #{$p->id}: {$first} — file missing, skipping");
                continue;
            }

            $hash = substr(md5_file($srcPath), 0, 8);
            $newFilename = "{$article}-1-{$hash}.webp";
            $newRelative = 'img/products/belkomin-tis/' . $newFilename;
            $newPath = $dir . DIRECTORY_SEPARATOR . $newFilename;

            $this->line("  #{$p->id}: {$first} -> {$newRelative}");

            if ($apply) {
                copy($srcPath, $newPath);
                $images[0] = $newRelative;
                DB::table('products')->where('id', $p->id)->update([
                    'images' => json_encode($images, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            }
            $renamed++;
        }

        $this->newLine();
        $this->info(($apply ? 'Renamed' : 'Would rename') . " {$renamed} of {$affected} URL-collision files.");
        if (! $apply) {
            $this->line('(dry run — pass --apply to write)');
        }

        return self::SUCCESS;
    }
}

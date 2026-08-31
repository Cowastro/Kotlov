<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Data-repair: products.images JSON contains paths ending in ".ebp" instead
 * of ".webp" for 178 active products (confirmed via debug:find-ebp-images).
 * The real file on disk is genuinely ".webp" — verified directly:
 *   .../686faeefd6989.ebp   → 200, 1210 bytes, no content-type (proxy-image's
 *                              broken-file placeholder fallback)
 *   .../686faeefd6989.webp  → 200, 95554 bytes, content-type: image/jpeg (real photo)
 * So this is a stored-path typo, not a missing/misnamed file — safe to fix
 * by rewriting the JSON string in place, no re-download needed.
 *
 * Root cause not tracked down (searched the obvious image-download call
 * sites — ProductSourceEnricher, SyncEcokamin*, Repair* — none matched the
 * bare-hash filename pattern these rows have; likely an older/one-off
 * import). This command only repairs existing data; if the same pattern
 * reappears after a future import, the introducing code still needs finding.
 *
 *   php artisan catalog:fix-ebp-images            # dry run
 *   php artisan catalog:fix-ebp-images --apply
 */
class FixEbpImagePathsCommand extends Command
{
    protected $signature = 'catalog:fix-ebp-images {--apply}';

    protected $description = 'Rewrite products.images ".ebp" -> ".webp" (stored-path typo, real file is .webp)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->line($apply ? '<fg=red;options=bold>APPLY</>' : '<fg=yellow>DRY-RUN</>');

        $rows = DB::table('products')
            ->where('images', 'like', '%.ebp"%')
            ->get(['id', 'images']);

        $this->info(sprintf('%d products (including archived) have a .ebp image path', $rows->count()));

        $fixed = 0;
        foreach ($rows as $row) {
            $images = json_decode((string) $row->images, true);
            if (! is_array($images)) {
                continue;
            }

            $changed = false;
            $newImages = array_map(function ($path) use (&$changed) {
                if (is_string($path) && str_ends_with($path, '.ebp')) {
                    $changed = true;
                    return substr($path, 0, -4) . '.webp';
                }
                return $path;
            }, $images);

            if (! $changed) {
                continue;
            }

            $fixed++;
            if ($apply) {
                DB::table('products')->where('id', $row->id)->update([
                    'images' => json_encode(array_values($newImages), JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->info(sprintf('%s: %d products', $apply ? 'FIXED' : 'WOULD FIX', $fixed));

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Diagnostic: find products whose `images` JSON contains a path ending in
 * ".ebp" instead of ".webp" (the leading "w" got lost somewhere in an
 * import/upload pipeline) — such files serve a tiny broken placeholder
 * instead of the real image.
 *
 *   php artisan debug:find-ebp-images
 *   php artisan debug:find-ebp-images --limit=200
 */
class DebugFindEbpImagesCommand extends Command
{
    protected $signature = 'debug:find-ebp-images {--limit=50}';

    protected $description = 'Find products with a ".ebp" (truncated ".webp") path in images JSON';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $total = DB::table('products')
            ->where('is_archived', false)
            ->where('images', 'like', '%.ebp"%')
            ->count();

        $this->info("Total active products with a .ebp image path: {$total}");

        $rows = DB::table('products')
            ->where('is_archived', false)
            ->where('images', 'like', '%.ebp"%')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'name', 'images']);

        foreach ($rows as $r) {
            $images = json_decode((string) $r->images, true) ?: [];
            $ebpCount = count(array_filter($images, fn ($p) => str_ends_with((string) $p, '.ebp')));
            $totalCount = count($images);
            $this->line(sprintf('  id=%d ebp=%d/%d | %s', $r->id, $ebpCount, $totalCount, mb_substr($r->name, 0, 60)));
        }

        return self::SUCCESS;
    }
}

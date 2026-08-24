<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Merge a duplicate brand row into the canonical one: reassign all products
 * from --from to --into, then deactivate the --from brand row (is_active=false).
 * Follows the same pattern as the 2026_07_19_170000_merge_hotta_brand_into_kotlov_ge
 * migration: deactivate rather than hard-delete, to keep a historical trail and
 * avoid surprises in anything still keyed on the old brand id.
 *
 * Use case: MySQL's default case-insensitive collation on brands.name lets
 * duplicate rows differing only by case ("TESY" vs "Tesy") coexist; exact-name
 * lookups elsewhere (e.g. supplier:enrich-teplodvor --brand=) then resolve
 * non-deterministically to whichever row the DB returns first, silently
 * hiding the other one's products from brand-scoped tooling.
 *
 *   php artisan brand:merge --from=114 --into=288 --apply
 */
class MergeBrandsCommand extends Command
{
    protected $signature = 'brand:merge {--from= : Brand id to merge away} {--into= : Brand id to keep} {--apply : Write changes (default: dry-run)}';

    protected $description = 'Merge a duplicate brand row into another, reassigning its products';

    public function handle(): int
    {
        $fromId = (int) $this->option('from');
        $intoId = (int) $this->option('into');
        $apply  = (bool) $this->option('apply');

        if (! $fromId || ! $intoId || $fromId === $intoId) {
            $this->error('--from and --into must be distinct, non-zero brand ids.');
            return self::FAILURE;
        }

        $from = DB::table('brands')->where('id', $fromId)->first();
        $into = DB::table('brands')->where('id', $intoId)->first();

        if (! $from || ! $into) {
            $this->error('One of the brand ids does not exist.');
            return self::FAILURE;
        }

        $count = DB::table('products')->where('brand_id', $fromId)->count();

        $this->line(sprintf(
            'Merge: "%s" (id=%d, %d products) → "%s" (id=%d)',
            $from->name, $fromId, $count, $into->name, $intoId
        ));

        if (! $apply) {
            $this->warn('DRY RUN — pass --apply to execute.');
            return self::SUCCESS;
        }

        $now = now();

        DB::transaction(function () use ($fromId, $intoId, $now) {
            DB::table('products')->where('brand_id', $fromId)->update(['brand_id' => $intoId, 'updated_at' => $now]);
            DB::table('brands')->where('id', $fromId)->update(['is_active' => false, 'updated_at' => $now]);
        });

        $this->info(sprintf('Done: %d products moved to brand id=%d, brand id=%d deactivated (is_active=false).', $count, $intoId, $fromId));

        return self::SUCCESS;
    }
}

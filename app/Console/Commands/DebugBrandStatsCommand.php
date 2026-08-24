<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Quick diagnostic: why does a brand show up as "0 products to process"
 * in supplier:enrich-teplodvor (or similar)? Reports brand row matches,
 * product counts, archived/slug state, and photo/spec completeness.
 *
 *   php artisan debug:brand-stats --brand=Tesy
 */
class DebugBrandStatsCommand extends Command
{
    protected $signature = 'debug:brand-stats {--brand= : Brand name (substring match)}';

    protected $description = 'Diagnose brand/product counts for enrichment anomalies';

    public function handle(): int
    {
        $needle = (string) $this->option('brand');
        if ($needle === '') {
            $this->error('--brand= is required');
            return self::FAILURE;
        }

        $brands = DB::table('brands')->where('name', 'like', '%' . $needle . '%')->get(['id', 'name']);

        if ($brands->isEmpty()) {
            $this->warn("No brand row matches \"{$needle}\".");
            return self::SUCCESS;
        }

        foreach ($brands as $b) {
            $total = DB::table('products')->where('brand_id', $b->id)->count();
            $archived = DB::table('products')->where('brand_id', $b->id)->where('is_archived', true)->count();
            $noSlug = DB::table('products')->where('brand_id', $b->id)
                ->where(function ($q) {
                    $q->whereNull('slug')->orWhere('slug', '');
                })->count();
            $noImages = DB::table('products')->where('brand_id', $b->id)
                ->where(function ($q) {
                    $q->whereNull('images')->orWhere('images', '')->orWhere('images', '[]');
                })->count();
            $noSpecs = DB::table('products')->where('brand_id', $b->id)
                ->where(function ($q) {
                    $q->whereNull('specs')->orWhere('specs', '')->orWhere('specs', '{}');
                })->count();
            $activeNoSlug = DB::table('products')->where('brand_id', $b->id)
                ->where('is_archived', false)
                ->where(function ($q) {
                    $q->whereNull('slug')->orWhere('slug', '');
                })->count();
            $needsEnrich = DB::table('products')->where('brand_id', $b->id)
                ->where('is_archived', false)
                ->whereNotNull('slug')->where('slug', '!=', '')
                ->where(function ($q) {
                    $q->whereNull('images')->orWhere('images', '')->orWhere('images', '[]')
                        ->orWhere(function ($q2) {
                            $q2->whereNull('specs')->orWhere('specs', '')->orWhere('specs', '{}');
                        });
                })->count();

            $rawImgEmptyBracket = DB::table('products')->where('brand_id', $b->id)->where('images', '[]')->count();
            $rawImgEmptyStr = DB::table('products')->where('brand_id', $b->id)->where('images', '')->count();
            $rawImgNull = DB::table('products')->where('brand_id', $b->id)->whereNull('images')->count();
            $imgType = DB::selectOne("SHOW COLUMNS FROM products LIKE 'images'")->Type ?? 'unknown';
            $specType = DB::selectOne("SHOW COLUMNS FROM products LIKE 'specs'")->Type ?? 'unknown';

            $this->line(sprintf('brand id=%d name=%s (images col type=%s, specs col type=%s)', $b->id, $b->name, $imgType, $specType));
            $this->line(sprintf('  raw: images=[] -> %d | images="" -> %d | images NULL -> %d', $rawImgEmptyBracket, $rawImgEmptyStr, $rawImgNull));
            $this->line(sprintf(
                '  total=%d archived=%d no_slug=%d active_no_slug=%d no_images=%d no_specs=%d matches_enrich_query=%d',
                $total, $archived, $noSlug, $activeNoSlug, $noImages, $noSpecs, $needsEnrich
            ));

            $all = DB::table('products')->where('brand_id', $b->id)
                ->get(['id', 'name', 'slug', 'is_archived', 'images', 'specs']);
            foreach ($all as $p) {
                $imgVal = var_export($p->images, true);
                $specVal = var_export($p->specs, true);
                $this->line(sprintf(
                    '    id=%d "%s" archived=%s images=%s specs=%s',
                    $p->id, mb_substr($p->name, 0, 30), $p->is_archived ? '1' : '0',
                    mb_substr($imgVal, 0, 30), mb_substr($specVal, 0, 30)
                ));
            }
        }

        return self::SUCCESS;
    }
}

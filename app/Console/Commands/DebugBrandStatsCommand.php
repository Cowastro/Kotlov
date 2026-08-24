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

            $this->line(sprintf('brand id=%d name=%s', $b->id, $b->name));
            $this->line(sprintf(
                '  total=%d archived=%d no_slug=%d active_no_slug=%d no_images=%d no_specs=%d matches_enrich_query=%d',
                $total, $archived, $noSlug, $activeNoSlug, $noImages, $noSpecs, $needsEnrich
            ));

            $sample = DB::table('products')->where('brand_id', $b->id)
                ->limit(3)->get(['id', 'name', 'slug', 'is_archived', 'images', 'specs']);
            foreach ($sample as $p) {
                $imgLen = strlen((string) $p->images);
                $specLen = strlen((string) $p->specs);
                $this->line(sprintf(
                    '    #%d %s | slug=%s archived=%s images_len=%d specs_len=%d',
                    $p->id, mb_substr($p->name, 0, 40), $p->slug ?: '(none)',
                    $p->is_archived ? '1' : '0', $imgLen, $specLen
                ));
            }
        }

        return self::SUCCESS;
    }
}

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
 *   php artisan debug:brand-stats --name="Прометалл"   # search by product name instead
 */
class DebugBrandStatsCommand extends Command
{
    protected $signature = 'debug:brand-stats
        {--brand= : Brand name (substring match against brands.name)}
        {--name=  : Product name (substring match against products.name) — use when no brand row is found}';

    protected $description = 'Diagnose brand/product counts for enrichment anomalies';

    public function handle(): int
    {
        $brandNeedle = (string) $this->option('brand');
        $nameNeedle  = (string) $this->option('name');

        if ($brandNeedle === '' && $nameNeedle === '') {
            $this->error('--brand= or --name= is required');
            return self::FAILURE;
        }

        if ($nameNeedle !== '') {
            $products = DB::table('products')->where('name', 'like', '%' . $nameNeedle . '%')
                ->get(['id', 'name', 'brand_id', 'slug', 'is_archived']);
            if ($products->isEmpty()) {
                $this->warn("No product name matches \"{$nameNeedle}\".");
                return self::SUCCESS;
            }
            $brandIds = $products->pluck('brand_id')->unique()->filter();
            $brandNames = DB::table('brands')->whereIn('id', $brandIds)->pluck('name', 'id');
            $this->line(sprintf('%d products match "%s":', $products->count(), $nameNeedle));
            foreach ($products as $p) {
                $bn = $p->brand_id ? ($brandNames[$p->brand_id] ?? '?') . " (id={$p->brand_id})" : '(no brand)';
                $this->line(sprintf('  id=%d archived=%s brand=%s | %s', $p->id, $p->is_archived ? '1' : '0', $bn, mb_substr($p->name, 0, 60)));
            }
            return self::SUCCESS;
        }

        $brands = DB::table('brands')->where('name', 'like', '%' . $brandNeedle . '%')->get(['id', 'name', 'is_active']);

        if ($brands->isEmpty()) {
            $this->warn("No brand row matches \"{$brandNeedle}\".");
            return self::SUCCESS;
        }

        foreach ($brands as $b) {
            $total = DB::table('products')->where('brand_id', $b->id)->count();
            $archived = DB::table('products')->where('brand_id', $b->id)->where('is_archived', true)->count();
            $activeNoSlug = DB::table('products')->where('brand_id', $b->id)
                ->where('is_archived', false)
                ->where(function ($q) {
                    $q->whereNull('slug')->orWhere('slug', '');
                })->count();
            $needsEnrich = DB::table('products')->where('brand_id', $b->id)
                ->where('is_archived', false)
                ->whereNotNull('slug')->where('slug', '!=', '')
                ->where(function ($q) {
                    $q->whereNull('images')->orWhere('images', '')->orWhereRaw('JSON_LENGTH(images) = 0')
                        ->orWhere(function ($q2) {
                            $q2->whereNull('specs')->orWhere('specs', '')->orWhereRaw('JSON_LENGTH(specs) = 0');
                        });
                })->count();

            $this->line(sprintf('brand id=%d name=%s is_active=%s', $b->id, $b->name, $b->is_active ? '1' : '0'));
            $this->line(sprintf(
                '  total=%d archived=%d active_no_slug=%d matches_enrich_query=%d',
                $total, $archived, $activeNoSlug, $needsEnrich
            ));
        }

        return self::SUCCESS;
    }
}

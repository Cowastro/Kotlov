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
        {--name=  : Product name (substring match against products.name) — use when no brand row is found}
        {--no-brand : List active products with brand_id IS NULL}
        {--needing-enrich : List all brands with active products still missing photos/specs, by count}
        {--dump : With --brand, print id/name/price/sku for every product instead of aggregate stats}
        {--specs-check : With --brand, print specs JSON length + product_attribute_values row count per product}';

    protected $description = 'Diagnose brand/product counts for enrichment anomalies';

    public function handle(): int
    {
        $brandNeedle = (string) $this->option('brand');
        $nameNeedle  = (string) $this->option('name');
        $noBrand     = (bool) $this->option('no-brand');

        if ($noBrand) {
            $products = DB::table('products')
                ->whereNull('brand_id')
                ->where('is_archived', false)
                ->get(['id', 'name', 'slug']);
            $this->line(sprintf('%d active products with brand_id IS NULL:', $products->count()));
            foreach ($products as $p) {
                $this->line(sprintf('  id=%d | %s', $p->id, mb_substr($p->name, 0, 70)));
            }
            return self::SUCCESS;
        }

        if ((bool) $this->option('needing-enrich')) {
            $rows = DB::table('products')
                ->join('brands', 'brands.id', '=', 'products.brand_id')
                ->where('products.is_archived', false)
                ->whereNotNull('products.slug')->where('products.slug', '!=', '')
                ->where('brands.is_active', true)
                ->where(function ($q) {
                    $q->whereNull('products.images')->orWhere('products.images', '')->orWhereRaw('JSON_LENGTH(products.images) = 0')
                        ->orWhere(function ($q2) {
                            $q2->whereNull('products.specs')->orWhere('products.specs', '')->orWhereRaw('JSON_LENGTH(products.specs) = 0');
                        });
                })
                ->selectRaw('brands.id as brand_id, brands.name as brand_name, COUNT(*) as cnt')
                ->groupBy('brands.id', 'brands.name')
                ->orderByDesc('cnt')
                ->get();
            $this->line(sprintf('%d brands with active products still needing photos/specs:', $rows->count()));
            foreach ($rows as $r) {
                $this->line(sprintf('  %s (id=%d): %d', $r->brand_name, $r->brand_id, $r->cnt));
            }
            return self::SUCCESS;
        }

        if ($brandNeedle === '' && $nameNeedle === '') {
            $this->error('--brand=, --name=, --no-brand, or --needing-enrich is required');
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

        if ((bool) $this->option('specs-check')) {
            foreach ($brands as $b) {
                $products = DB::table('products')->where('brand_id', $b->id)
                    ->where('is_archived', false)
                    ->orderBy('id')
                    ->get(['id', 'name', 'specs', 'content', 'images']);
                $this->line(sprintf('brand id=%d name=%s: %d active products', $b->id, $b->name, $products->count()));
                foreach ($products as $p) {
                    $attrCount = DB::table('product_attribute_values')->where('product_id', $p->id)->count();
                    $specsLen = $p->specs ? strlen((string) $p->specs) : 0;
                    $contentLen = $p->content ? mb_strlen(strip_tags((string) $p->content)) : 0;
                    $imagesArr = $p->images ? json_decode((string) $p->images, true) : [];
                    $imagesCount = is_array($imagesArr) ? count($imagesArr) : 0;
                    $this->line(sprintf(
                        '  id=%d images=%d specs_json_len=%d attr_rows=%d content_len=%d | %s',
                        $p->id, $imagesCount, $specsLen, $attrCount, $contentLen, mb_substr($p->name, 0, 50)
                    ));
                }
            }
            return self::SUCCESS;
        }

        if ((bool) $this->option('dump')) {
            foreach ($brands as $b) {
                $products = DB::table('products')->where('brand_id', $b->id)
                    ->orderBy('id')
                    ->get(['id', 'name', 'sku', 'price', 'currency', 'is_archived']);
                $this->line(sprintf('brand id=%d name=%s: %d products', $b->id, $b->name, $products->count()));
                foreach ($products as $p) {
                    $this->line(sprintf(
                        '  id=%d archived=%s sku=%s price=%s %s | %s',
                        $p->id, $p->is_archived ? '1' : '0', $p->sku ?: '-', $p->price, $p->currency, $p->name
                    ));
                }
            }
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

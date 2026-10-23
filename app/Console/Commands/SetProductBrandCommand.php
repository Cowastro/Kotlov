<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Point one card at the right brand (e.g. a Wester tank filed under UNIPUMP).
 *
 *   php artisan catalog:set-product-brand 3599 wester            # dry run
 *   php artisan catalog:set-product-brand 3599 wester --apply
 */
class SetProductBrandCommand extends Command
{
    protected $signature = 'catalog:set-product-brand {product : Product id} {brand : Brand slug} {--apply : Write the change}';

    protected $description = 'Set the brand of one product card';

    public function handle(): int
    {
        $product = DB::table('products')->where('id', (int) $this->argument('product'))->first(['id', 'slug', 'brand_id']);
        $brand = DB::table('brands')->where('slug', $this->argument('brand'))->first(['id', 'slug']);
        if (! $product || ! $brand) {
            $this->line('metric.product_brand.found=0');

            return self::FAILURE;
        }
        $before = (string) (DB::table('brands')->where('id', $product->brand_id)->value('slug') ?? 'none');
        $this->line("audit.product_brand.change={$product->id}:{$product->slug}:{$before}:{$brand->slug}");

        $changed = (int) $product->brand_id !== (int) $brand->id;
        if ($changed && $this->option('apply')) {
            DB::table('products')->where('id', $product->id)->update(['brand_id' => $brand->id, 'updated_at' => now()]);
        }
        $this->line('metric.product_brand.found=1');
        $this->line('metric.product_brand.updated='.(int) ($changed && $this->option('apply')));

        return self::SUCCESS;
    }
}

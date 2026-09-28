<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ArchiveBrandProductsCommand extends Command
{
    protected $signature = 'catalog:archive-brand
        {brand : Exact brand name}
        {--apply : Archive matching products; default is dry-run}';

    protected $description = 'Safely archive every product assigned to an exact brand name.';

    public function handle(): int
    {
        $brandName = trim((string) $this->argument('brand'));
        $brand = DB::table('brands')->where('name', $brandName)->first(['id', 'name']);

        if (! $brand) {
            $this->error('Brand not found: ' . $brandName);

            return self::FAILURE;
        }

        $products = DB::table('products')
            ->where('brand_id', $brand->id)
            ->where(function ($query) {
                $query->where('is_archived', false)->orWhere('is_active', true);
            })
            ->orderBy('id')
            ->get(['id', 'sku', 'name', 'price', 'is_active', 'is_archived']);

        $this->info(sprintf('Brand: %s; products to archive: %d', $brand->name, $products->count()));

        if ($products->isEmpty()) {
            return self::SUCCESS;
        }

        $this->table(
            ['id', 'sku', 'name', 'price'],
            $products->map(fn ($product) => [
                $product->id,
                $product->sku,
                $product->name,
                $product->price,
            ])->all()
        );

        if (! $this->option('apply')) {
            $this->warn('DRY RUN: no database changes. Add --apply to archive these products.');

            return self::SUCCESS;
        }

        $updated = DB::table('products')
            ->whereIn('id', $products->pluck('id'))
            ->update([
                'is_active' => false,
                'is_archived' => true,
                'in_stock' => false,
                'stock_qty' => 0,
                'availability_status' => 'out_of_stock',
                'updated_at' => now(),
            ]);

        $this->info(sprintf('Archived %d products for brand %s.', $updated, $brand->name));

        return self::SUCCESS;
    }
}

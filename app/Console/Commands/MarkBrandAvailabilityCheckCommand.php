<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MarkBrandAvailabilityCheckCommand extends Command
{
    protected $signature = 'catalog:mark-brand-check
        {--brand=* : Exact brand name to mark, can be repeated}
        {--category=* : Exact category slug to scope products, can be repeated}
        {--apply : Write product and supplier stock status; default is dry-run}';

    protected $description = 'Mark non-archived products for selected brands as "Уточняйте наличие".';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $brands = array_values(array_filter(array_map('trim', (array) $this->option('brand'))));
        $categorySlugs = array_values(array_filter(array_map('trim', (array) $this->option('category'))));

        if ($brands === []) {
            $this->error('At least one --brand is required.');

            return self::FAILURE;
        }

        $categoryIds = $categorySlugs === []
            ? []
            : Category::query()->whereIn('slug', $categorySlugs)->pluck('id')->all();

        $missingCategorySlugs = array_values(array_diff($categorySlugs, Category::query()->whereIn('id', $categoryIds)->pluck('slug')->all()));
        foreach ($missingCategorySlugs as $slug) {
            $this->warn('Category not found: ' . $slug);
        }

        $products = Product::query()
            ->where('is_archived', false)
            ->whereHas('brand', fn ($query) => $query->whereIn('name', $brands))
            ->when($categoryIds !== [], fn ($query) => $query->whereIn('category_id', $categoryIds))
            ->with(['brand:id,name', 'category:id,name,slug'])
            ->orderBy('brand_id')
            ->orderBy('name')
            ->get(['id', 'brand_id', 'category_id', 'name', 'in_stock', 'availability_status']);

        $foundBrands = $products
            ->pluck('brand.name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $missingBrands = array_values(array_diff($brands, $foundBrands));
        foreach ($missingBrands as $brand) {
            $this->warn('No non-archived products found for brand: ' . $brand);
        }

        $rows = $products->map(fn (Product $product): array => [
            $product->id,
            $product->brand?->name ?? '',
            $product->category?->slug ?? '',
            $product->name,
            $product->in_stock ? 'yes' : 'no',
            $product->availability_status ?: '',
        ])->all();

        $supplierRows = DB::table('supplier_products')
            ->whereIn('product_id', $products->pluck('id')->all())
            ->count();

        $willUpdate = $products->filter(
            fn (Product $product): bool => $product->availability_status !== Product::AVAILABILITY_CHECK || (bool) $product->in_stock
        )->count();

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: selected brand products will show "Уточняйте наличие".</>'
            : '<fg=yellow;options=bold>DRY RUN: no database changes.</>');

        $this->table(['metric', 'count'], [
            ['products', $products->count()],
            ['supplier_products', $supplierRows],
            ['already_check', $products->where('availability_status', Product::AVAILABILITY_CHECK)->count()],
            ['will_update_products', $willUpdate],
        ]);

        $this->table(['id', 'brand', 'category', 'name', 'old_in_stock', 'old_status'], array_slice($rows, 0, 160));

        if (! $apply || $products->isEmpty()) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($products): void {
            $ids = $products->pluck('id')->all();
            $now = now();

            Product::query()
                ->whereIn('id', $ids)
                ->update([
                    'in_stock' => false,
                    'availability_status' => Product::AVAILABILITY_CHECK,
                    'stock_qty' => null,
                    'updated_at' => $now,
                ]);

            DB::table('supplier_products')
                ->whereIn('product_id', $ids)
                ->update([
                    'in_stock' => false,
                    'stock_quantity' => null,
                    'stock_status' => 'unknown',
                    'stock_text' => 'Уточняйте наличие',
                    'delivery_days' => null,
                    'last_stock_synced_at' => $now,
                    'updated_at' => $now,
                ]);
        });

        $this->info('Done.');

        return self::SUCCESS;
    }
}

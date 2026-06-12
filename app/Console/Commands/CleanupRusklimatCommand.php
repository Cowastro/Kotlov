<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Archive or delete Rusklimat products that are out of stock
 * and have no other supplier with available stock.
 */
class CleanupRusklimatCommand extends Command
{
    protected $signature = 'supplier:cleanup-rusklimat
        {--dry-run  : Show what would be affected — no changes}
        {--apply    : Archive zero-stock products (is_archived=true, is_active=false)}
        {--delete   : Hard-delete zero-stock products (irreversible!)}';

    protected $description = 'Archive or delete Rusklimat products with zero stock (no other supplier in stock)';

    private const SUPPLIER_CODE = 'rusklimat';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $apply  = (bool) $this->option('apply');
        $delete = (bool) $this->option('delete');

        if (! $dryRun && ! $apply && ! $delete) {
            $this->error('Specify --dry-run, --apply, or --delete');
            return self::FAILURE;
        }

        if ($delete && ! $this->confirm('⚠  --delete will permanently remove products from the database. Continue?')) {
            return self::SUCCESS;
        }

        $supplierId = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id');
        if (! $supplierId) {
            $this->error('Supplier "' . self::SUPPLIER_CODE . '" not found.');
            return self::FAILURE;
        }

        // Products linked to Rusklimat with zero/no stock
        $zeroStockIds = DB::table('supplier_products as sp')
            ->where('sp.supplier_id', $supplierId)
            ->where(function ($q) {
                $q->whereIn('sp.stock_status', ['out_of_stock', 'discontinued'])
                  ->orWhere(function ($q2) {
                      $q2->whereNull('sp.stock_quantity')
                         ->orWhere('sp.stock_quantity', 0);
                  });
            })
            ->pluck('sp.product_id')
            ->toArray();

        if (empty($zeroStockIds)) {
            $this->info('No zero-stock Rusklimat products found.');
            return self::SUCCESS;
        }

        // Exclude products that have stock from any OTHER supplier
        $otherInStockIds = DB::table('supplier_products as sp')
            ->whereIn('sp.product_id', $zeroStockIds)
            ->where('sp.supplier_id', '!=', $supplierId)
            ->where('sp.in_stock', true)
            ->pluck('sp.product_id')
            ->toArray();

        $targetIds = array_values(array_diff($zeroStockIds, $otherInStockIds));

        if (empty($targetIds)) {
            $this->info('All zero-stock products have other suppliers in stock — nothing to do.');
            return self::SUCCESS;
        }

        // Load product info for display
        $products = DB::table('products as p')
            ->leftJoin('categories as c', 'p.category_id', '=', 'c.id')
            ->leftJoin('brands as b', 'p.brand_id', '=', 'b.id')
            ->leftJoin('supplier_products as sp', function ($join) use ($supplierId) {
                $join->on('sp.product_id', '=', 'p.id')->where('sp.supplier_id', $supplierId);
            })
            ->whereIn('p.id', $targetIds)
            ->select('p.id', 'p.sku', 'p.name', 'p.is_archived', 'p.is_active',
                     'c.name as category', 'b.name as brand',
                     'sp.stock_status', 'sp.stock_quantity')
            ->orderBy('c.name')
            ->orderBy('p.name')
            ->get();

        // Summary by category
        $byCategory = $products->groupBy('category')->map->count()->sortDesc();

        $this->newLine();
        $this->info(sprintf('Found <fg=yellow>%d</> products to %s:', count($targetIds), $delete ? 'delete' : 'archive'));
        $this->newLine();

        $this->table(
            ['Category', 'Count'],
            $byCategory->map(fn ($cnt, $cat) => [$cat ?: '—', $cnt])->values()->toArray()
        );

        if ($dryRun) {
            $this->newLine();
            $this->warn('[dry-run] No changes made.');
            $this->line('Run with <fg=green>--apply</> to archive, or <fg=red>--delete</> to hard-delete.');
            return self::SUCCESS;
        }

        if ($delete) {
            // Remove supplier_products first (FK), then products
            DB::table('supplier_products')->whereIn('product_id', $targetIds)->delete();
            $deleted = DB::table('products')->whereIn('id', $targetIds)->delete();
            $this->info(sprintf('<fg=red>Deleted %d products.</>', $deleted));
        } else {
            // Archive
            $archived = DB::table('products')
                ->whereIn('id', $targetIds)
                ->update(['is_archived' => true, 'is_active' => false, 'updated_at' => now()]);
            $this->info(sprintf('<fg=green>Archived %d products (is_archived=true, is_active=false).</>', $archived));
        }

        return self::SUCCESS;
    }
}

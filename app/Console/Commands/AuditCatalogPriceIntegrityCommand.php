<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class AuditCatalogPriceIntegrityCommand extends Command
{
    protected $signature = 'catalog:audit-price-integrity
        {--stale-hours=48 : Age after which a positive stock snapshot requires attention}';

    protected $description = 'Read-only aggregate audit of catalog prices, margins, currencies and stock evidence.';

    public function handle(): int
    {
        $staleHours = max(1, min(24 * 90, (int) $this->option('stale-hours')));
        $minimumMargin = (float) config('shop.order_management.minimum_margin_percent', 10);
        $active = $this->activeProducts();

        $supplierCost = DB::table('supplier_products')
            ->selectRaw('product_id, MIN(price_byn) AS minimum_cost')
            ->whereNotNull('product_id')
            ->where('stock_quantity', '>', 0)
            ->where('price_byn', '>', 0)
            ->groupBy('product_id');

        $metrics = [
            'active_products' => (clone $active)->count(),
            'active_zero_or_missing_price' => (clone $active)
                ->where(fn (Builder $query): Builder => $query
                    ->whereNull('p.price')
                    ->orWhere('p.price', '<=', 0))
                ->count(),
            'active_non_byn_currency' => (clone $active)
                ->whereNotNull('p.currency')
                ->whereRaw('UPPER(p.currency) <> ?', ['BYN'])
                ->count(),
            'invalid_old_price' => (clone $active)
                ->where('p.price_old', '>', 0)
                ->whereColumn('p.price_old', '<=', 'p.price')
                ->count(),
            'marked_in_stock_without_positive_channel' => (clone $active)
                ->where(fn (Builder $query): Builder => $query
                    ->where('p.in_stock', true)
                    ->orWhere('p.availability_status', 'in_stock'))
                ->whereNotExists(fn (Builder $query): Builder => $query
                    ->selectRaw('1')
                    ->from('supplier_products as sp')
                    ->whereColumn('sp.product_id', 'p.id')
                    ->where('sp.stock_quantity', '>', 0))
                ->whereNotExists(fn (Builder $query): Builder => $query
                    ->selectRaw('1')
                    ->from('integration_products as ip')
                    ->join('integration_sources as src', 'src.id', '=', 'ip.integration_source_id')
                    ->whereColumn('ip.product_id', 'p.id')
                    ->where('ip.match_status', 'matched')
                    ->where('ip.stock_quantity', '>', 0)
                    ->where('src.is_active', true))
                ->count(),
            'positive_supplier_stock_without_price' => DB::table('supplier_products')
                ->whereNotNull('product_id')
                ->where('stock_quantity', '>', 0)
                ->where(fn (Builder $query): Builder => $query
                    ->whereNull('price_byn')
                    ->orWhere('price_byn', '<=', 0))
                ->count(),
            'retail_below_supplier_cost' => (clone $active)
                ->joinSub($supplierCost, 'costs', 'costs.product_id', '=', 'p.id')
                ->whereColumn('p.price', '<', 'costs.minimum_cost')
                ->count(),
            'retail_margin_below_minimum' => (clone $active)
                ->joinSub($supplierCost, 'costs', 'costs.product_id', '=', 'p.id')
                ->where('p.price', '>', 0)
                ->whereRaw('((p.price - costs.minimum_cost) / p.price * 100) < ?', [$minimumMargin])
                ->count(),
            'retail_above_four_times_supplier_cost' => (clone $active)
                ->joinSub($supplierCost, 'costs', 'costs.product_id', '=', 'p.id')
                ->whereRaw('p.price > costs.minimum_cost * 4')
                ->count(),
            'stale_positive_supplier_stock' => DB::table('supplier_products')
                ->whereNotNull('product_id')
                ->where('stock_quantity', '>', 0)
                ->whereRaw('COALESCE(last_stock_synced_at, last_synced_at, updated_at) < ?', [now()->subHours($staleHours)])
                ->count(),
            'stale_positive_integration_stock' => DB::table('integration_products as ip')
                ->join('integration_sources as src', 'src.id', '=', 'ip.integration_source_id')
                ->whereNotNull('ip.product_id')
                ->where('ip.match_status', 'matched')
                ->where('ip.stock_quantity', '>', 0)
                ->where('src.is_active', true)
                ->where(fn (Builder $query): Builder => $query
                    ->whereNull('ip.stock_confirmed_at')
                    ->orWhere('ip.stock_confirmed_at', '<', now()->subHours($staleHours)))
                ->count(),
        ];

        foreach ($metrics as $name => $value) {
            $this->line('metric.'.$name.'='.$value);
        }

        $this->line('metric.minimum_margin_percent='.number_format($minimumMargin, 1, '.', ''));
        $this->line('metric.stale_hours='.$staleHours);

        return self::SUCCESS;
    }

    private function activeProducts(): Builder
    {
        return DB::table('products as p')
            ->where('p.is_active', true)
            ->where('p.is_archived', false);
    }
}

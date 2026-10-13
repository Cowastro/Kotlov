<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class AuditCatalogPriceIntegrityCommand extends Command
{
    protected $signature = 'catalog:audit-price-integrity
        {--stale-hours=24 : Age after which a positive supplier stock snapshot requires attention}
        {--output= : Optional private CSV path for product-level price anomalies}
        {--research-output= : Optional procurement-safe CSV path for external market research}';

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
            'price_confirmation_holds' => (clone $active)
                ->where('p.requires_price_confirmation', true)
                ->count(),
        ];

        if (filled($this->option('output'))) {
            $metrics['report_rows'] = $this->writeReport(
                (string) $this->option('output'),
                $active,
                $supplierCost,
                $minimumMargin,
            );
        }

        if (filled($this->option('research-output'))) {
            $metrics['research_rows'] = $this->writeResearchReport(
                (string) $this->option('research-output'),
                $active,
                $supplierCost,
                $minimumMargin,
            );
        }

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

    private function writeReport(
        string $output,
        Builder $active,
        Builder $supplierCost,
        float $minimumMargin,
    ): int {
        $isAbsolute = preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $output) === 1;
        $path = $isAbsolute ? $output : base_path($output);
        File::ensureDirectoryExists(dirname($path));

        $rows = (clone $active)
            ->leftJoinSub($supplierCost, 'costs', 'costs.product_id', '=', 'p.id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->orderBy('p.id')
            ->get([
                'p.id', 'p.sku', 'p.name', 'p.slug', 'p.price', 'p.price_old',
                'p.currency', 'p.availability_status', 'p.updated_at',
                'c.name as category', 'c.slug as category_slug', 'costs.minimum_cost',
            ])
            ->map(function (object $row) use ($minimumMargin): ?array {
                $price = (float) $row->price;
                $oldPrice = (float) $row->price_old;
                $cost = $row->minimum_cost === null ? null : (float) $row->minimum_cost;
                $margin = $price > 0 && $cost !== null
                    ? round(($price - $cost) / $price * 100, 2)
                    : null;
                $issues = [];

                if ($price <= 0) {
                    $issues[] = 'zero_or_missing_price';
                }
                if (filled($row->currency) && mb_strtoupper((string) $row->currency) !== 'BYN') {
                    $issues[] = 'non_byn_currency';
                }
                if ($oldPrice > 0 && $oldPrice <= $price) {
                    $issues[] = 'invalid_old_price';
                }
                if ($cost !== null && $price > 0 && $price < $cost) {
                    $issues[] = 'retail_below_supplier_cost';
                }
                if ($margin !== null && $margin < $minimumMargin) {
                    $issues[] = 'retail_margin_below_minimum';
                }
                if ($cost !== null && $cost > 0 && $price > $cost * 4) {
                    $issues[] = 'retail_above_four_times_supplier_cost';
                }

                if ($issues === []) {
                    return null;
                }

                return [
                    implode('|', $issues),
                    $row->id,
                    $row->sku,
                    $row->name,
                    $row->category,
                    '/'.trim((string) $row->category_slug, '/').'/'.trim((string) $row->slug, '/'),
                    $price > 0 ? number_format($price, 2, '.', '') : '',
                    $oldPrice > 0 ? number_format($oldPrice, 2, '.', '') : '',
                    $cost !== null ? number_format($cost, 2, '.', '') : '',
                    $margin !== null ? number_format($margin, 2, '.', '') : '',
                    $row->currency,
                    $row->availability_status,
                    $row->updated_at,
                ];
            })
            ->filter()
            ->values();

        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Unable to create price audit report: '.$path);
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'issues', 'product_id', 'sku', 'name', 'category', 'path',
            'retail_price', 'old_price', 'minimum_supplier_cost', 'margin_percent',
            'currency', 'availability_status', 'updated_at',
        ], ';', '"', '');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ';', '"', '');
        }
        fclose($handle);

        return $rows->count();
    }

    private function writeResearchReport(
        string $output,
        Builder $active,
        Builder $supplierCost,
        float $minimumMargin,
    ): int {
        $isAbsolute = preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $output) === 1;
        $path = $isAbsolute ? $output : base_path($output);
        File::ensureDirectoryExists(dirname($path));

        $priority = [
            'retail_below_supplier_cost' => 10,
            'retail_margin_below_minimum' => 20,
            'retail_above_four_times_supplier_cost' => 30,
            'zero_or_missing_price' => 40,
            'invalid_old_price' => 50,
            'non_byn_currency' => 60,
        ];

        $rows = (clone $active)
            ->leftJoinSub($supplierCost, 'costs', 'costs.product_id', '=', 'p.id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->get([
                'p.id', 'p.sku', 'p.name', 'p.slug', 'p.price', 'p.price_old',
                'p.currency', 'p.availability_status', 'p.updated_at',
                'c.name as category', 'c.slug as category_slug',
                'b.name as brand', 'costs.minimum_cost',
            ])
            ->map(function (object $row) use ($minimumMargin, $priority): ?array {
                $price = (float) $row->price;
                $oldPrice = (float) $row->price_old;
                $cost = $row->minimum_cost === null ? null : (float) $row->minimum_cost;
                $margin = $price > 0 && $cost !== null
                    ? round(($price - $cost) / $price * 100, 2)
                    : null;
                $issues = [];

                if ($cost !== null && $price > 0 && $price < $cost) {
                    $issues[] = 'retail_below_supplier_cost';
                }
                if ($margin !== null && $margin < $minimumMargin) {
                    $issues[] = 'retail_margin_below_minimum';
                }
                if ($cost !== null && $cost > 0 && $price > $cost * 4) {
                    $issues[] = 'retail_above_four_times_supplier_cost';
                }
                if ($price <= 0) {
                    $issues[] = 'zero_or_missing_price';
                }
                if ($oldPrice > 0 && $oldPrice <= $price) {
                    $issues[] = 'invalid_old_price';
                }
                if (filled($row->currency) && mb_strtoupper((string) $row->currency) !== 'BYN') {
                    $issues[] = 'non_byn_currency';
                }

                if ($issues === []) {
                    return null;
                }

                return [
                    'priority' => min(array_map(fn (string $issue): int => $priority[$issue], $issues)),
                    'issues' => implode('|', $issues),
                    'product_id' => (int) $row->id,
                    'sku' => (string) $row->sku,
                    'name' => (string) $row->name,
                    'brand' => (string) ($row->brand ?? ''),
                    'category' => (string) ($row->category ?? ''),
                    'path' => '/'.trim((string) $row->category_slug, '/').'/'.trim((string) $row->slug, '/'),
                    'retail_price' => $price > 0 ? number_format($price, 2, '.', '') : '',
                    'old_price' => $oldPrice > 0 ? number_format($oldPrice, 2, '.', '') : '',
                    'currency' => (string) $row->currency,
                    'availability_status' => (string) $row->availability_status,
                    'updated_at' => (string) $row->updated_at,
                ];
            })
            ->filter()
            ->sortBy([
                ['priority', 'asc'],
                ['brand', 'asc'],
                ['category', 'asc'],
                ['product_id', 'asc'],
            ])
            ->values();

        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Unable to create market research queue: '.$path);
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'priority', 'issues', 'product_id', 'sku', 'name', 'brand', 'category', 'path',
            'retail_price', 'old_price', 'currency', 'availability_status', 'updated_at',
        ], ';', '"', '');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ';', '"', '');
        }
        fclose($handle);

        return $rows->count();
    }
}

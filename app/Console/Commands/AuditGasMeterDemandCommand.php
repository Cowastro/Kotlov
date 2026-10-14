<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class AuditGasMeterDemandCommand extends Command
{
    protected $signature = 'orders:audit-gas-meter-demand
        {--days=365 : Период недавнего спроса}
        {--report= : Путь CSV-отчёта}';

    protected $description = 'Read-only analysis of archived gas-meter enquiries for a supplier stock proposal.';

    public function handle(): int
    {
        $days = max(30, (int) $this->option('days'));
        $cutoff = now()->subDays($days);
        $categoryId = (int) (DB::table('categories')->where('slug', 'schetchiki-gaza')->value('id') ?? 0);
        if ($categoryId === 0) {
            $this->error('Категория «Счётчики газа» не найдена.');

            return self::FAILURE;
        }

        $products = DB::table('products')
            ->where('category_id', $categoryId)
            ->get(['id', 'sku', 'name', 'price', 'rating', 'reviews_count', 'views_count', 'stock_qty', 'in_stock', 'is_active', 'is_archived'])
            ->keyBy('id');
        $productIds = $products->keys()->all();
        $supplierId = (int) (DB::table('suppliers')->where('code', 'sanbusinessgroup')->value('id') ?? 0);
        $supplierProductIds = DB::table('supplier_products')
            ->where('supplier_id', $supplierId)
            ->whereNotNull('product_id')
            ->pluck('product_id')
            ->map(fn ($id): int => (int) $id)
            ->flip();

        $items = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->whereNotNull('o.archived_at')
            ->where(function ($query) use ($productIds): void {
                $query->whereIn('oi.product_id', $productIds)
                    ->orWhereRaw('LOWER(oi.product_name) LIKE ?', ['%счетчик%газа%']);
            })
            ->get([
                'oi.order_id', 'oi.product_id', 'oi.product_sku', 'oi.product_name', 'oi.quantity', 'oi.total',
                'o.created_at', 'o.archived_at', 'o.archive_reason', 'o.status', 'o.payment_status',
            ]);

        $grouped = $items->groupBy(fn (object $item): string => $item->product_id
            ? 'id:'.(int) $item->product_id
            : 'name:'.$this->normalizeName((string) $item->product_name));
        $rows = $grouped->map(function ($demandItems) use ($products, $cutoff, $supplierProductIds): array {
            $first = $demandItems->first();
            $product = $first->product_id ? $products->get((int) $first->product_id) : null;
            $valid = $demandItems->reject(fn (object $item): bool => preg_match('/дубл|тест/iu', (string) $item->archive_reason) === 1);
            $recent = $valid->filter(fn (object $item): bool => \Illuminate\Support\Carbon::parse($item->created_at)->gte($cutoff));
            $validOrders = $valid->pluck('order_id')->unique()->count();
            $validQuantity = (int) $valid->sum('quantity');
            $recentOrders = $recent->pluck('order_id')->unique()->count();
            $recentQuantity = (int) $recent->sum('quantity');
            $views = (int) ($product->views_count ?? 0);
            $rating = (float) ($product->rating ?? 0);
            $reviews = (int) ($product->reviews_count ?? 0);
            $active = $product && (bool) $product->is_active && ! (bool) $product->is_archived;
            $supplierLinked = $product && $supplierProductIds->has((int) $product->id);
            $stock = $product?->stock_qty === null ? null : (float) $product->stock_qty;
            $score = round($recentOrders * 100 + $recentQuantity * 25 + $validOrders * 10 + min(50, log10(max(1, $views)) * 10) + $rating * 5 + $reviews * 2, 2);
            $target = $recentQuantity > 0 ? max(1, min(5, max((int) $recent->max('quantity'), $recentOrders))) : 0;
            $recommended = $active && $supplierLinked && $target > 0
                ? max(0, $target - (int) max(0, $stock ?? 0))
                : 0;

            return [
                'product_id' => $product?->id ?? $first->product_id,
                'sku' => $product?->sku ?? $first->product_sku,
                'name' => $product?->name ?? $first->product_name,
                'active' => $active,
                'supplier_linked' => $supplierLinked,
                'archived_orders' => $demandItems->pluck('order_id')->unique()->count(),
                'archived_quantity' => (int) $demandItems->sum('quantity'),
                'valid_orders' => $validOrders,
                'valid_quantity' => $validQuantity,
                'recent_orders' => $recentOrders,
                'recent_quantity' => $recentQuantity,
                'excluded_test_duplicate_orders' => $demandItems->pluck('order_id')->unique()->count() - $validOrders,
                'last_request_at' => $valid->max('created_at'),
                'rating' => $rating,
                'reviews_count' => $reviews,
                'views_count' => $views,
                'current_stock' => $stock,
                'retail_price' => $product?->price === null ? null : (float) $product->price,
                'demand_score' => $score,
                'recommended_purchase' => $recommended,
                'recommendation_status' => ! $active && $validOrders > 0
                    ? 'demand_for_inactive_product'
                    : ($recommended > 0 ? 'buy' : 'observe'),
            ];
        })->sortByDesc('demand_score')->values();

        $path = trim((string) $this->option('report')) ?: storage_path('app/reports/suppliers/belomo/gas-meter-demand.csv');
        File::ensureDirectoryExists(dirname($path));
        $handle = fopen($path, 'wb');
        fwrite($handle, "\xEF\xBB\xBF");
        $headers = array_keys($rows->first() ?? [
                'product_id' => null, 'sku' => null, 'name' => null, 'active' => null, 'supplier_linked' => null,
            'archived_orders' => null, 'archived_quantity' => null, 'valid_orders' => null, 'valid_quantity' => null,
            'recent_orders' => null, 'recent_quantity' => null, 'excluded_test_duplicate_orders' => null,
            'last_request_at' => null, 'rating' => null, 'reviews_count' => null, 'views_count' => null,
            'current_stock' => null, 'retail_price' => null, 'demand_score' => null,
            'recommended_purchase' => null, 'recommendation_status' => null,
        ]);
        fputcsv($handle, $headers, ';');
        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($value) => is_bool($value) ? ($value ? '1' : '0') : $value, $row), ';');
        }
        fclose($handle);

        $this->line('metric.gas_meter_demand.archived_orders='.$items->pluck('order_id')->unique()->count());
        $this->line('metric.gas_meter_demand.archived_items='.$items->count());
        $this->line('metric.gas_meter_demand.products='.$rows->count());
        $this->line('metric.gas_meter_demand.buy_positions='.$rows->where('recommended_purchase', '>', 0)->count());
        $this->line('metric.gas_meter_demand.buy_quantity='.$rows->sum('recommended_purchase'));
        $this->line('metric.gas_meter_demand.inactive_demand='.$rows->where('recommendation_status', 'demand_for_inactive_product')->count());
        $this->line('metric.gas_meter_demand.report='.$path);

        return self::SUCCESS;
    }

    private function normalizeName(string $name): string
    {
        return mb_strtolower(preg_replace('/[^\pL\pN]+/u', '', $name) ?? $name);
    }
}

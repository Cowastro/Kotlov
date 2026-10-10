<?php

namespace App\Services\Orders;

use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\OrderItem;
use Illuminate\Support\Collection;

class OrderStockRecommendationService
{
    /**
     * Build read-only warehouse recommendations from all non-cancelled orders.
     * Recent demand controls the target, while all-time demand and recency
     * determine priority.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function recommendations(int $recentDays = 180, string $sourceCode = 'onec'): Collection
    {
        $recentDays = max(30, $recentDays);
        $cutoff = now()->subDays($recentDays);
        $source = IntegrationSource::query()->where('code', $sourceCode)->first();
        $ownStock = $this->ownStockByProduct($source);

        return OrderItem::query()
            ->with(['order:id,status,created_at', 'product:id,name,sku'])
            ->whereNotNull('product_id')
            ->whereHas('order', fn ($query) => $query->where('status', '!=', 'cancelled'))
            ->get()
            ->groupBy('product_id')
            ->map(function (Collection $items, int|string $productId) use ($cutoff, $recentDays, $ownStock): array {
                $recent = $items->filter(fn (OrderItem $item): bool => $item->order?->created_at?->gte($cutoff) === true);
                $product = $items->first()->product;
                $allOrders = $items->pluck('order_id')->unique()->count();
                $recentOrders = $recent->pluck('order_id')->unique()->count();
                $allQuantity = (int) $items->sum('quantity');
                $recentQuantity = (int) $recent->sum('quantity');
                $largestOrder = max(1, (int) $items->max('quantity'));
                $largestRecentOrder = (int) $recent->max('quantity');
                $monthlyVelocity = $recentQuantity / ($recentDays / 30);
                $dailyVelocity = $recentQuantity / $recentDays;
                $targetStock = $recentQuantity > 0
                    ? max($largestRecentOrder, (int) ceil($monthlyVelocity * 2))
                    : 0;
                $currentStock = (float) ($ownStock[(int) $productId] ?? 0);
                $stockCoverageDays = $dailyVelocity > 0
                    ? round($currentStock / $dailyVelocity, 1)
                    : null;
                $lastOrderedAt = $items->max(fn (OrderItem $item) => $item->order?->created_at);
                $recencyBoost = $lastOrderedAt?->gte(now()->subDays(30)) ? 20 : ($lastOrderedAt?->gte(now()->subDays(90)) ? 8 : 0);
                $priorityScore = round($recentOrders * 10 + $recentQuantity * 3 + $allOrders + $recencyBoost, 1);
                $recommendedPurchase = max(0, (int) ceil($targetStock - $currentStock));
                $stockState = match (true) {
                    $recentQuantity === 0 => 'no_recent_demand',
                    $currentStock <= 0 => 'out_of_stock',
                    $recommendedPurchase > 0 => 'below_target',
                    default => 'enough',
                };
                $targetBasis = max($largestRecentOrder, (int) ceil($monthlyVelocity * 2));

                return [
                    'product_id' => (int) $productId,
                    'sku' => $product?->sku ?? $items->first()->product_sku,
                    'name' => $product?->name ?? $items->first()->product_name,
                    'orders_all' => $allOrders,
                    'quantity_all' => $allQuantity,
                    'revenue_all' => round((float) $items->sum('total'), 2),
                    'orders_recent' => $recentOrders,
                    'quantity_recent' => $recentQuantity,
                    'monthly_velocity' => round($monthlyVelocity, 2),
                    'daily_velocity' => round($dailyVelocity, 4),
                    'largest_order' => $largestOrder,
                    'largest_recent_order' => $largestRecentOrder,
                    'last_ordered_at' => $lastOrderedAt,
                    'current_own_stock' => $currentStock,
                    'stock_coverage_days' => $stockCoverageDays,
                    'target_stock' => $targetStock,
                    'recommended_purchase' => $recommendedPurchase,
                    'stock_state' => $stockState,
                    'explanation' => $this->explanation(
                        $recentDays,
                        $recentOrders,
                        $recentQuantity,
                        $monthlyVelocity,
                        $largestRecentOrder,
                        $targetBasis,
                        $currentStock,
                        $recommendedPurchase,
                    ),
                    'priority_score' => $priorityScore,
                ];
            })
            ->filter(fn (array $row): bool => $row['orders_all'] > 0)
            ->sortByDesc(fn (array $row): array => [
                $row['recommended_purchase'] > 0 ? 1 : 0,
                $row['priority_score'],
                $row['quantity_all'],
            ])
            ->values();
    }

    private function explanation(
        int $recentDays,
        int $recentOrders,
        int $recentQuantity,
        float $monthlyVelocity,
        int $largestRecentOrder,
        int $targetStock,
        float $currentStock,
        int $recommendedPurchase,
    ): string {
        if ($recentQuantity === 0) {
            return "За последние {$recentDays} дней спроса не было. Целевой запас не формируется.";
        }

        $demand = number_format($monthlyVelocity, 2, ',', ' ');
        $stock = number_format($currentStock, 3, ',', ' ');
        $stock = rtrim(rtrim($stock, '0'), ',');
        $basis = "максимум из крупнейшего заказа ({$largestRecentOrder} шт.) и двух месяцев спроса";

        if ($recommendedPurchase === 0) {
            return "За {$recentDays} дней: {$recentOrders} заказ(а), {$recentQuantity} шт.; {$demand} шт./мес. Цель {$targetStock} шт. — {$basis}. На складе {$stock} шт., пополнение не требуется.";
        }

        return "За {$recentDays} дней: {$recentOrders} заказ(а), {$recentQuantity} шт.; {$demand} шт./мес. Цель {$targetStock} шт. — {$basis}. На складе {$stock} шт.; рекомендуется добавить {$recommendedPurchase} шт.";
    }

    /** @return Collection<int, float> */
    private function ownStockByProduct(?IntegrationSource $source): Collection
    {
        if (! $source) {
            return collect();
        }

        return IntegrationProduct::query()
            ->where('integration_source_id', $source->id)
            ->where('match_status', 'matched')
            ->whereNotNull('product_id')
            ->get(['product_id', 'stock_quantity'])
            ->groupBy('product_id')
            ->map(fn (Collection $offers): float => round((float) $offers->sum('stock_quantity'), 3));
    }
}

<?php

namespace App\Services\Orders;

use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\OrderItem;
use Illuminate\Support\Collection;

class OrderStockRecommendationService
{
    private const CONFIRMED_DEMAND_STATUSES = ['confirmed', 'processing', 'shipped', 'delivered'];

    /**
     * Build read-only warehouse recommendations from confirmed demand.
     * New unpaid orders are retained as unconfirmed interest, but never affect
     * the target stock or purchase recommendation.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function recommendations(int $recentDays = 180, string $sourceCode = 'onec'): Collection
    {
        $recentDays = max(30, $recentDays);
        $cutoff = now()->subDays($recentDays);
        $source = IntegrationSource::query()->where('code', $sourceCode)->first();
        $ownStock = $this->ownStockEvidenceByProduct($source);

        return OrderItem::query()
            ->with(['order:id,status,payment_status,created_at', 'product:id,name,sku'])
            ->whereNotNull('product_id')
            ->whereHas('order', fn ($query) => $query->where('status', '!=', 'cancelled'))
            ->get()
            ->groupBy('product_id')
            ->map(function (Collection $items, int|string $productId) use ($cutoff, $recentDays, $ownStock, $source): array {
                $confirmed = $items->filter(fn (OrderItem $item): bool => $this->isConfirmedDemand($item));
                $interest = $items->reject(fn (OrderItem $item): bool => $this->isConfirmedDemand($item));
                $recent = $confirmed->filter(fn (OrderItem $item): bool => $item->order?->created_at?->gte($cutoff) === true);
                $recentInterest = $interest->filter(fn (OrderItem $item): bool => $item->order?->created_at?->gte($cutoff) === true);
                $product = $items->first()->product;
                $allOrders = $confirmed->pluck('order_id')->unique()->count();
                $recentOrders = $recent->pluck('order_id')->unique()->count();
                $allQuantity = (int) $confirmed->sum('quantity');
                $recentQuantity = (int) $recent->sum('quantity');
                $interestOrders = $interest->pluck('order_id')->unique()->count();
                $interestQuantity = (int) $interest->sum('quantity');
                $recentInterestOrders = $recentInterest->pluck('order_id')->unique()->count();
                $recentInterestQuantity = (int) $recentInterest->sum('quantity');
                $largestOrder = $confirmed->isEmpty() ? 0 : max(1, (int) $confirmed->max('quantity'));
                $largestRecentOrder = (int) $recent->max('quantity');
                $monthlyVelocity = $recentQuantity / ($recentDays / 30);
                $dailyVelocity = $recentQuantity / $recentDays;
                $targetStock = $recentQuantity > 0
                    ? max($largestRecentOrder, (int) ceil($monthlyVelocity * 2))
                    : 0;
                $stockEvidence = $ownStock->get((int) $productId, [
                    'quantity' => null,
                    'status' => $source ? 'not_linked' : 'source_missing',
                    'label' => $source ? 'Нет привязки к 1С' : 'Источник не найден',
                    'confirmed_at' => null,
                    'offer_count' => 0,
                    'integration_product_id' => null,
                ]);
                $currentStock = $stockEvidence['quantity'];
                $stockCoverageDays = $dailyVelocity > 0 && $currentStock !== null
                    ? round($currentStock / $dailyVelocity, 1)
                    : null;
                $lastOrderedAt = $confirmed->max(fn (OrderItem $item) => $item->order?->created_at);
                $lastInterestAt = $interest->max(fn (OrderItem $item) => $item->order?->created_at);
                $recencyBoost = $lastOrderedAt?->gte(now()->subDays(30)) ? 20 : ($lastOrderedAt?->gte(now()->subDays(90)) ? 8 : 0);
                $priorityScore = round($recentOrders * 10 + $recentQuantity * 3 + $allOrders + $recencyBoost, 1);
                $stockDataReady = $stockEvidence['status'] === 'confirmed';
                $recommendedPurchase = $targetStock === 0
                    ? 0
                    : ($stockDataReady
                        ? max(0, (int) ceil($targetStock - $currentStock))
                        : null);
                $stockState = match (true) {
                    $recentQuantity === 0 && $recentInterestQuantity > 0 => 'interest_only',
                    $recentQuantity === 0 => 'no_recent_demand',
                    ! $stockDataReady => 'stock_unverified',
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
                    'revenue_all' => round((float) $confirmed->sum('total'), 2),
                    'orders_recent' => $recentOrders,
                    'quantity_recent' => $recentQuantity,
                    'interest_orders_all' => $interestOrders,
                    'interest_quantity_all' => $interestQuantity,
                    'interest_orders_recent' => $recentInterestOrders,
                    'interest_quantity_recent' => $recentInterestQuantity,
                    'monthly_velocity' => round($monthlyVelocity, 2),
                    'daily_velocity' => round($dailyVelocity, 4),
                    'largest_order' => $largestOrder,
                    'largest_recent_order' => $largestRecentOrder,
                    'last_ordered_at' => $lastOrderedAt,
                    'last_interest_at' => $lastInterestAt,
                    'current_own_stock' => $currentStock,
                    'stock_coverage_days' => $stockCoverageDays,
                    'stock_data_status' => $stockEvidence['status'],
                    'stock_data_label' => $stockEvidence['label'],
                    'stock_data_ready' => $stockDataReady,
                    'stock_confirmed_at' => $stockEvidence['confirmed_at'],
                    'stock_offer_count' => $stockEvidence['offer_count'],
                    'stock_integration_product_id' => $stockEvidence['integration_product_id'],
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
                        $stockEvidence['status'],
                        $stockEvidence['label'],
                        $recentInterestOrders,
                        $recentInterestQuantity,
                    ),
                    'priority_score' => $priorityScore,
                ];
            })
            ->filter(fn (array $row): bool => $row['orders_all'] > 0 || $row['interest_orders_all'] > 0)
            ->sortByDesc(fn (array $row): array => [
                ($row['recommended_purchase'] ?? 0) > 0 ? 2 : ($row['quantity_recent'] > 0 && ! $row['stock_data_ready'] ? 1 : 0),
                $row['priority_score'],
                $row['quantity_all'] + $row['interest_quantity_all'],
            ])
            ->values();
    }

    private function isConfirmedDemand(OrderItem $item): bool
    {
        return $item->order?->payment_status === 'paid'
            || in_array($item->order?->status, self::CONFIRMED_DEMAND_STATUSES, true);
    }

    private function explanation(
        int $recentDays,
        int $recentOrders,
        int $recentQuantity,
        float $monthlyVelocity,
        int $largestRecentOrder,
        int $targetStock,
        ?float $currentStock,
        ?int $recommendedPurchase,
        string $stockDataStatus,
        string $stockDataLabel,
        int $recentInterestOrders,
        int $recentInterestQuantity,
    ): string {
        $interestNote = $recentInterestQuantity > 0
            ? " Неподтверждённые заявки: {$recentInterestOrders} / {$recentInterestQuantity} шт.; в закупку не учитываются."
            : '';

        if ($recentQuantity === 0) {
            return "За последние {$recentDays} дней подтверждённого спроса не было. Целевой запас не формируется.".$interestNote;
        }

        $demand = number_format($monthlyVelocity, 2, ',', ' ');
        $basis = "максимум из крупнейшего заказа ({$largestRecentOrder} шт.) и двух месяцев спроса";

        if ($stockDataStatus !== 'confirmed') {
            return "За {$recentDays} дней подтверждено: {$recentOrders} заказ(а), {$recentQuantity} шт.; {$demand} шт./мес. Цель {$targetStock} шт. — {$basis}. Решение о закупке заблокировано: {$stockDataLabel}.".$interestNote;
        }

        $stock = number_format((float) $currentStock, 3, ',', ' ');
        $stock = rtrim(rtrim($stock, '0'), ',');

        if ($recommendedPurchase === 0) {
            return "За {$recentDays} дней подтверждено: {$recentOrders} заказ(а), {$recentQuantity} шт.; {$demand} шт./мес. Цель {$targetStock} шт. — {$basis}. На складе {$stock} шт., пополнение не требуется.".$interestNote;
        }

        return "За {$recentDays} дней подтверждено: {$recentOrders} заказ(а), {$recentQuantity} шт.; {$demand} шт./мес. Цель {$targetStock} шт. — {$basis}. На складе {$stock} шт.; рекомендуется добавить {$recommendedPurchase} шт.".$interestNote;
    }

    /** @return Collection<int, array{quantity:?float,status:string,label:string,confirmed_at:mixed,offer_count:int,integration_product_id:?int}> */
    private function ownStockEvidenceByProduct(?IntegrationSource $source): Collection
    {
        if (! $source) {
            return collect();
        }

        return IntegrationProduct::query()
            ->where('integration_source_id', $source->id)
            ->where('match_status', 'matched')
            ->whereNotNull('product_id')
            ->get(['id', 'product_id', 'stock_quantity', 'stock_confirmed_at'])
            ->groupBy('product_id')
            ->map(function (Collection $offers) use ($source): array {
                $hasMissingStock = $offers->contains(fn (IntegrationProduct $offer): bool => $offer->stock_quantity === null);
                $hasMissingConfirmation = $offers->contains(fn (IntegrationProduct $offer): bool => $offer->stock_confirmed_at === null);
                $freshAfter = now()->subMinutes($source->staleAfterMinutes());
                $hasStaleConfirmation = $offers->contains(
                    fn (IntegrationProduct $offer): bool => $offer->stock_confirmed_at?->lt($freshAfter) === true,
                );
                [$status, $label] = match (true) {
                    $hasMissingStock => ['missing_stock', 'Остаток не передан'],
                    $hasMissingConfirmation => ['unconfirmed', 'Остаток не подтверждён обменом'],
                    $hasStaleConfirmation => ['stale', 'Данные остатка устарели'],
                    default => ['confirmed', 'Подтверждено 1С'],
                };

                return [
                    'quantity' => $hasMissingStock ? null : round((float) $offers->sum('stock_quantity'), 3),
                    'status' => $status,
                    'label' => $label,
                    'confirmed_at' => $offers->max('stock_confirmed_at'),
                    'offer_count' => $offers->count(),
                    'integration_product_id' => $offers->count() === 1
                        ? (int) $offers->first()->getKey()
                        : null,
                ];
            });
    }
}

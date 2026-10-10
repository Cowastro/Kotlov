<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderEconomicSnapshot;

class OrderEconomicSnapshotRecorder
{
    public function capturePlaced(Order $order): OrderEconomicSnapshot
    {
        $existing = $order->economicSnapshots()
            ->where('kind', OrderEconomicSnapshot::KIND_PLACED)
            ->first();

        if ($existing) {
            return $existing;
        }

        $order->loadMissing('items');
        $summary = app(OrderItemSupplyContextResolver::class)->summarize($order);
        $isComplete = $summary['items_count'] > 0
            && $summary['missing_price_count'] === 0
            && $summary['unresolved_count'] === 0;

        $status = match (true) {
            $summary['items_count'] === 0 => OrderEconomicSnapshot::STATUS_INCOMPLETE,
            $summary['missing_price_count'] > 0 && $summary['unresolved_count'] > 0 => OrderEconomicSnapshot::STATUS_INCOMPLETE,
            $summary['unresolved_count'] > 0 => OrderEconomicSnapshot::STATUS_MISSING_SUPPLIER,
            $summary['missing_price_count'] > 0 => OrderEconomicSnapshot::STATUS_MISSING_PRICE,
            default => OrderEconomicSnapshot::STATUS_COMPLETE,
        };

        $taxModes = $order->items
            ->groupBy(fn ($item): string => $item->supply_price_tax_mode ?: 'unknown')
            ->map->count()
            ->all();

        return $order->economicSnapshots()->create([
            'kind' => OrderEconomicSnapshot::KIND_PLACED,
            'status' => $status,
            'currency' => 'BYN',
            'goods_sale_total' => $summary['sale_total'],
            'delivery_revenue' => (float) $order->delivery_price,
            'discount_total' => (float) $order->discount,
            'order_total' => (float) $order->total,
            'known_purchase_total' => $summary['purchase_total'],
            'purchase_total' => $isComplete ? $summary['purchase_total'] : null,
            'known_goods_margin_total' => $summary['margin_total'],
            'goods_margin_total' => $isComplete ? $summary['margin_total'] : null,
            'goods_margin_percent' => $isComplete ? $summary['margin_percent'] : null,
            'delivery_cost' => null,
            'payment_fee' => null,
            'marketplace_commission' => null,
            'net_profit' => null,
            'items_count' => $summary['items_count'],
            'priced_items_count' => $summary['priced_items_count'],
            'missing_purchase_price_count' => $summary['missing_price_count'],
            'missing_supplier_count' => $summary['unresolved_count'],
            'tax_modes' => $taxModes,
            'unknown_costs' => ['delivery_cost', 'payment_fee', 'marketplace_commission'],
            'captured_at' => now(),
        ]);
    }
}

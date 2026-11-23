<?php

namespace App\Services\Integrations\OneCBridge;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Supplier;
use App\Services\Orders\OrderItemFulfillmentPicker;

/**
 * A line of an open order whose 1C item has enough free stock on our warehouse needs no decision: «Наш склад», in
 * stock, bought at the 1C cost. Filled in, not confirmed — the manager still confirms the order to the customer.
 * Runs after every 1C stock read; a line a manager already routed is never touched.
 */
class OneCOwnStockRouting
{
    private const OPEN = ['new', 'checking', 'confirmed'];

    public function __construct(private readonly OrderItemFulfillmentPicker $picker) {}

    /** @return int lines filled in */
    public function fillOpenOrders(): int
    {
        $n = 0;
        Order::query()->whereIn('status', self::OPEN)->whereNull('archived_at')->where('created_at', '>', now()->subDays(60))
            ->whereHas('items', fn ($q) => $q->whereNull('fulfillment_route')->whereNull('fulfillment_confirmed_at')->whereNotNull('product_id'))
            ->with('items')->chunkById(50, function ($orders) use (&$n): void {
                foreach ($orders as $order) {
                    $n += $this->fill($order);
                }
            });

        return $n;
    }

    public function fill(Order $order): int
    {
        $n = 0;
        foreach ($order->items as $item) {
            $n += $this->fillLine($item) ? 1 : 0;
        }

        return $n;
    }

    private function fillLine(OrderItem $item): bool
    {
        if ($item->fulfillment_route !== null || $item->fulfillment_confirmed_at !== null || ! $item->product_id) {
            return false;
        }
        $offers = $this->picker->offers($item);
        $defaults = $this->picker->defaults($item, $offers);
        $offer = $offers[$defaults['supplier_id']] ?? null;
        // Only our warehouse with 1C stock read and enough of it free.
        if ($defaults['fulfillment_route'] !== 'own_stock' || $defaults['availability_status'] !== 'available'
            || ! $offer || ! str_starts_with((string) $offer['source'], '1С,')) {
            return false;
        }
        $supplier = Supplier::query()->find($defaults['supplier_id']);
        $item->forceFill([
            'fulfillment_route' => 'own_stock',
            'fulfillment_supplier_id' => $supplier?->id,
            'fulfillment_supplier_name' => $supplier?->name,
            // the 1C cost only — never the selling price; no cost yet, the manager enters it
            'fulfillment_purchase_price' => (float) $item->product?->onec_cost_byn > 0 ? (float) $item->product->onec_cost_byn : null,
            'fulfillment_availability_status' => 'available',
            'fulfillment_note' => 'Из 1С: '.$offer['source'],
        ])->save();

        return true;
    }
}

<?php

namespace App\Services;

use App\Models\CustomerSelection;

class SelectionCartMerge
{
    /** Merge only live, authorized stock. Origin quantities make repeat clicks idempotent. */
    public function merge(CustomerSelection $selection, array $snapshot, array $cart, ?array $only = null): array
    {
        $added = 0;
        $conflicts = 0;
        $processed = [];
        foreach ($selection->items as $id => $item) {
            if ($only !== null ? ! in_array((int) $id, $only, true) : in_array((int) $id, array_map('intval', $selection->meta['desk_excluded_ids'] ?? []), true)) {
                continue;
            }
            $allocation = $snapshot[$id];
            if ($allocation['ready'] < 1) {
                continue;
            }
            $offer = $allocation['offer'];
            $existing = $cart[$id] ?? null;
            if ($existing && (($existing['pricing_type'] ?? '') !== 'b2b' || ($existing['supply_option'] ?? '') !== $offer['key'])) {
                $conflicts++;

                continue;
            }
            $quantity = (int) ($existing['quantity'] ?? 0);
            $origins = [];
            $remaining = $quantity;
            foreach (($existing['request_origins'] ?? []) as $origin => $count) {
                $count = min($remaining, max(0, (int) $count));
                if ($count > 0) {
                    $origins[$origin] = $count;
                }
                $remaining -= $count;
            }
            $own = $origins[$selection->id] ?? 0;
            $increment = min(max(0, $allocation['ready'] - $own), max(0, min(999, $allocation['stock_capacity']) - $quantity));
            // Validate the combined line, including quantities already in the cart.
            $rules = $offer['order_rules'] ?? [];
            $multiple = (float) ($rules['multiple'] ?? 1);
            if ($multiple >= 1 && floor($multiple) === $multiple) {
                $target = $quantity + $increment;
                $increment = max(0, $target - ($target % (int) $multiple) - $quantity);
            }
            if ($quantity + $increment < (float) ($rules['minimum'] ?? 1) || $increment < 1) {
                continue;
            }
            $origins[$selection->id] = $own + $increment;
            $cart[$id] = array_replace($existing ?? $item, [
                'quantity' => $quantity + $increment, 'price' => $offer['price'], 'pricing_type' => 'b2b',
                'supply_option' => $offer['key'], 'integration_product_id' => $offer['integration_product_id'],
                'supplier_product_id' => $offer['supplier_product_id'], 'warehouse_stock_id' => $offer['warehouse_stock_id'],
                'warehouse_name' => $offer['warehouse_name'], 'source_name' => $offer['source_label'],
                'order_rules' => $rules, 'price_tax_mode' => 'inclusive', 'request_origins' => $origins,
            ]);
            unset($cart[$id]['allocation']);
            $added += $increment;
            $processed[] = (int) $id;
        }

        return compact('cart', 'added', 'conflicts', 'processed');
    }
}

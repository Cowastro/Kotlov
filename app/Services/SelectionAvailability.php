<?php

namespace App\Services;

use App\Models\CustomerSelection;
use App\Models\Product;
use App\Models\User;

/** A demand allocation, never a reservation. Only current, authorized offers count. */
class SelectionAvailability
{
    public function check(CustomerSelection $selection, User $user): array
    {
        $items = $selection->items ?? [];
        $products = Product::query()->active()->notArchived()->whereIn('id', array_keys($items))->get()->keyBy('id');
        $options = app(B2bSupplyOptionResolver::class)->optionsForUser($user, array_keys($items))->groupBy('product_id');
        $result = [];
        foreach ($items as $id => $item) {
            $key = $item['supply_option'] ?? (! empty($item['supplier_product_id']) ? 'supplier:'.$item['supplier_product_id']
                : (! empty($item['integration_product_id']) ? 'integration:'.$item['integration_product_id'] : null));
            $offers = $options->get((int) $id, collect());
            // Never silently switch the supplier selected by the manager/client.
            $offer = $key ? $offers->firstWhere('key', $key) : null;
            if (! $key && $user->isAdmin() && ! empty($item['workspace_purchase']['supplier'])) {
                $matching = $offers->where('source_label', $item['workspace_purchase']['supplier']);
                if ($matching->count() === 1) {
                    $offer = $matching->first();
                }
            }
            $quantity = (int) $item['quantity'];
            $stock = $offer['stock'] ?? null;
            $ready = $products->has((int) $id) && $stock !== null ? min($quantity, max(0, (int) floor($stock))) : 0;
            $rules = $offer['order_rules'] ?? [];
            // A partial shipment must still satisfy the supplier's order rules.
            $multiple = (float) ($rules['multiple'] ?? 1);
            if ($multiple >= 1 && floor($multiple) === $multiple) {
                $ready -= $ready % (int) $multiple;
            }
            if ($ready < (float) ($rules['minimum'] ?? 1)) {
                $ready = 0;
            }
            $result[$id] = [
                'ready' => $ready, 'waiting' => $quantity - $ready,
                'stock_capacity' => $stock === null ? 0 : max(0, (int) floor($stock)),
                'fingerprint' => $this->fingerprint($item),
                'unknown' => $stock === null, 'checked_at' => now()->toIso8601String(),
                'stock_key' => $offer['stock_key'] ?? null,
                // Only public price and supply identifiers are stored; no procurement data.
                'offer' => $offer ? array_intersect_key($offer, array_flip(['key', 'price', 'integration_product_id', 'supplier_product_id', 'warehouse_stock_id', 'warehouse_name', 'source_label', 'order_rules'])) : null,
            ];
        }

        return $result;
    }

    public function annotate(array $items, array $allocations): array
    {
        return array_map(function (array $item) use ($allocations): array {
            $allocation = $allocations[$item['id']] ?? null;
            // A quantity edit invalidates the previous check, rather than inventing stock.
            $valid = $allocation && ($allocation['fingerprint'] ?? '') === $this->fingerprint($item);
            $item['allocation'] = $valid ? $allocation : null;

            return $item;
        }, $items);
    }

    private function fingerprint(array $item): string
    {
        return hash('sha256', json_encode([(int) $item['quantity'], $item['supply_option'] ?? null,
            $item['supplier_product_id'] ?? null, $item['integration_product_id'] ?? null,
            $item['workspace_purchase']['supplier_key'] ?? null]));
    }
}

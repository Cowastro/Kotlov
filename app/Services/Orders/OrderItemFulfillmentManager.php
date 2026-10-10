<?php

namespace App\Services\Orders;

use App\Models\OrderItem;
use App\Models\OrderItemFulfillmentHistory;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderItemFulfillmentManager
{
    public function confirm(
        OrderItem $item,
        string $route,
        ?Supplier $supplier,
        ?User $user,
        ?string $note = null,
        ?float $purchasePrice = null,
    ): OrderItem {
        if (! array_key_exists($route, OrderItem::FULFILLMENT_ROUTES)) {
            throw ValidationException::withMessages([
                'fulfillment_route' => 'Выберите допустимый способ исполнения.',
            ]);
        }

        if (in_array($route, ['supplier_purchase', 'direct_supplier'], true) && ! $supplier) {
            throw ValidationException::withMessages([
                'supplier_id' => 'Для закупки или прямой передачи необходимо выбрать поставщика.',
            ]);
        }

        return DB::transaction(function () use ($item, $route, $supplier, $user, $note, $purchasePrice): OrderItem {
            $item = OrderItem::query()->lockForUpdate()->findOrFail($item->getKey());
            $previousRoute = $item->fulfillment_route;
            $previousSupplierId = $item->fulfillment_supplier_id;
            $previousSupplierName = $item->fulfillment_supplier_name;
            $previousPurchasePrice = $item->fulfillment_purchase_price;

            $confirmedPrice = $purchasePrice;
            if ($confirmedPrice === null && $supplier?->id === $item->supply_supplier_id) {
                $confirmedPrice = $item->supply_purchase_price !== null
                    ? (float) $item->supply_purchase_price
                    : null;
            }

            $item->forceFill([
                'fulfillment_route' => $route,
                'fulfillment_supplier_id' => $supplier?->id,
                'fulfillment_supplier_name' => $supplier?->name,
                'fulfillment_supplier_contact' => $supplier?->contact,
                'fulfillment_purchase_price' => $route === 'own_stock'
                    ? ($purchasePrice ?? ($item->supply_purchase_price !== null ? (float) $item->supply_purchase_price : null))
                    : $confirmedPrice,
                'fulfillment_confirmed_by' => $user?->id,
                'fulfillment_confirmed_at' => now(),
                'fulfillment_note' => filled($note) ? trim($note) : null,
            ])->save();

            OrderItemFulfillmentHistory::query()->create([
                'order_item_id' => $item->id,
                'user_id' => $user?->id,
                'previous_route' => $previousRoute,
                'previous_supplier_id' => $previousSupplierId,
                'previous_supplier_name' => $previousSupplierName,
                'previous_purchase_price' => $previousPurchasePrice,
                'route' => $route,
                'supplier_id' => $supplier?->id,
                'supplier_name' => $supplier?->name,
                'supplier_contact' => $supplier?->contact,
                'purchase_price' => $item->fulfillment_purchase_price,
                'note' => filled($note) ? trim($note) : null,
            ]);

            return $item->refresh();
        });
    }
}

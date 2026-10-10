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

        return DB::transaction(function () use ($item, $route, $supplier, $user, $note): OrderItem {
            $item->forceFill([
                'fulfillment_route' => $route,
                'fulfillment_supplier_id' => $supplier?->id,
                'fulfillment_supplier_name' => $supplier?->name,
                'fulfillment_supplier_contact' => $supplier?->contact,
                'fulfillment_confirmed_by' => $user?->id,
                'fulfillment_confirmed_at' => now(),
                'fulfillment_note' => filled($note) ? trim($note) : null,
            ])->save();

            OrderItemFulfillmentHistory::query()->create([
                'order_item_id' => $item->id,
                'user_id' => $user?->id,
                'route' => $route,
                'supplier_id' => $supplier?->id,
                'supplier_name' => $supplier?->name,
                'supplier_contact' => $supplier?->contact,
                'note' => filled($note) ? trim($note) : null,
            ]);

            return $item->refresh();
        });
    }
}

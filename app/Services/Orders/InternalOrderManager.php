<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class InternalOrderManager
{
    public function confirm(Order $order, User $actor): void
    {
        DB::transaction(function () use ($order, $actor): void {
            $order = $this->locked($order, $actor);
            $items = $order->items()->lockForUpdate()->get();
            if ($items->isEmpty()) {
                $this->fail('В заказе нет позиций.');
            }
            $picker = app(OrderItemFulfillmentPicker::class);
            foreach ($items as $item) {
                if ($item->fulfillment_confirmed_at) {
                    if ($item->fulfillment_availability_status === 'unavailable') {
                        $this->fail('В позиции «'.$item->product_name.'» отмечено отсутствие. Сначала исправьте её исполнение.');
                    }

                    continue;
                }
                $offers = $picker->offers($item);
                $preferred = $item->fulfillment_supplier_id ?: $item->supply_supplier_id;
                if (! $preferred && count($offers) !== 1) {
                    $this->fail('Сначала выберите поставщика для позиции «'.$item->product_name.'».');
                }
                $preferred ??= array_key_first($offers);
                if (! isset($offers[$preferred])) {
                    $this->fail('Поставщик недоступен для позиции «'.$item->product_name.'».');
                }
                $supplier = Supplier::where('is_active', true)->findOrFail($preferred);
                $route = $offers[$preferred]['own'] ? 'own_stock'
                    : ($item->fulfillment_route === 'direct_supplier' ? 'direct_supplier' : 'supplier_purchase');
                app(OrderItemFulfillmentManager::class)->confirm(
                    $item, $route, $supplier, $actor,
                    'Общее подтверждение внутреннего заказа менеджером.',
                    $item->fulfillment_purchase_price !== null ? (float) $item->fulfillment_purchase_price
                        : ($item->supply_purchase_price !== null ? (float) $item->supply_purchase_price : $offers[$preferred]['price']),
                    'available',
                );
            }
            $order->forceFill(['is_internal' => true])->save();
            $attributes = ['availability_confirmed_at' => $order->availability_confirmed_at ?? now(),
                'availability_confirmed_by' => $order->availability_confirmed_by ?? $actor->id];
            $previousStatus = $order->status;
            $order->transitionTo(in_array($order->status, ['new', 'checking'], true) ? 'confirmed' : $order->status,
                'Внутренний заказ: менеджер подтвердил все позиции одной операцией.', $attributes);
            if ($previousStatus === $order->status) {
                $order->statusHistory()->create(['user_id' => $actor->id, 'status_from' => $order->status,
                    'status_to' => $order->status, 'comment' => 'Внутренний заказ: общее подтверждение позиций.']);
            }
        });
    }

    public function complete(Order $order, User $actor): void
    {
        DB::transaction(function () use ($order, $actor): void {
            $order = $this->locked($order, $actor);
            if (! $order->is_internal || ! $order->hasConfirmedFulfillmentForAllItems()) {
                $this->fail('Сначала подтвердите все позиции внутреннего заказа.');
            }
            if ($order->items()->where('fulfillment_availability_status', 'unavailable')->exists()) {
                $this->fail('В заказе остались позиции с отсутствием. Сначала исправьте их исполнение.');
            }
            $order->forceFill(['deal_on_hold' => false, 'deal_next_action' => null,
                'deal_control_at' => null, 'deal_version' => (int) $order->deal_version + 1])->save();
            $order->transitionTo('delivered', 'Внутренний заказ успешно закрыт менеджером. Оплата и документы 1С не изменены.');
        });
    }

    private function locked(Order $order, User $actor): Order
    {
        abort_unless($actor->is_active && in_array($actor->role, ['admin', 'manager', 'sales_manager'], true), 403);
        $order = Order::lockForUpdate()->findOrFail($order->id);
        if (! $actor->isAdmin()) {
            Gate::forUser($actor)->authorize('update', $order);
        }
        if (in_array($order->status, ['cancelled', 'delivered', 'completed'], true) || $order->archived_at
            || str_starts_with($order->number, 'DBY-') || $order->legacyB2bOrder()->exists()) {
            $this->fail('Для этого заказа действие недоступно.');
        }

        return $order;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['internal_order' => $message]);
    }
}

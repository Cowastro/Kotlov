<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WholeOrderFulfillment
{
    public function fingerprint(Order $order): string
    {
        return hash('sha256', $order->items()->orderBy('id')->get(['id', 'product_id', 'product_name', 'product_sku', 'quantity', 'price'])->toJson());
    }

    public function preview(Order $order): array
    {
        $picker = app(OrderItemFulfillmentPicker::class);
        $remaining = [];

        return $order->items()->orderBy('id')->get()->map(function ($item) use ($picker, &$remaining) {
            $offers = $picker->offers($item);
            $defaults = $picker->defaults($item, $offers);
            $offer = $offers[$defaults['supplier_id']] ?? null;
            $known = $offer !== null && $offer['quantity'] !== null;
            $pool = $item->product_id.':'.$defaults['supplier_id'];
            if ($known && ! array_key_exists($pool, $remaining)) {
                $remaining[$pool] = max(0, (float) $offer['quantity']);
            }
            $available = $known ? min((int) $item->quantity, $remaining[$pool]) : null;
            if ($item->fulfillment_availability_status === 'reserved' && $item->fulfillment_confirmed_at) {
                $available = (int) $item->quantity; // Already held stock is not part of the free pool.
            } elseif ($known) {
                $remaining[$pool] -= $available;
            }
            $status = $available === null ? 'clarifying' : ($available >= $item->quantity ? 'available' : ($available > 0 ? 'partial' : 'awaiting_supply'));

            return ['item' => $item, 'route' => $defaults['fulfillment_route'] ?: 'clarifying',
                'supplier_id' => $defaults['supplier_id'], 'price' => $defaults['purchase_price'],
                'own_cost_label' => $defaults['fulfillment_route'] === 'own_stock'
                    ? ($offer['own_cost_label'] ?? \App\Models\OneCItem::grossCostLabel(null))
                    : null,
                'available' => $available, 'missing' => $available === null ? null : $item->quantity - $available,
                'status' => $item->fulfillment_confirmed_at ? $item->fulfillment_availability_status : $status,
                'source' => $offer['source'] ?? 'Источник уточняется'];
        })->all();
    }

    public function confirm(Order $order, User $actor, string $fingerprint, ?string $expectedDate = null, ?string $customerNote = null): array
    {
        $actor = $actor->fresh();
        abort_unless($actor?->is_active && $actor->isManager(), 403);
        $warning = null;
        $order = DB::transaction(function () use ($order, $actor, $fingerprint, $expectedDate, $customerNote, &$warning) {
            $locked = Order::lockForUpdate()->findOrFail($order->id);
            if (! in_array($locked->status, ['new', 'checking', 'confirmed'], true) || $locked->archived_at) {
                throw ValidationException::withMessages(['fingerprint' => 'Заказ уже передан в исполнение или закрыт. Обновите страницу.']);
            }
            $locked->items()->lockForUpdate()->get();
            if (! hash_equals($this->fingerprint($locked), $fingerprint)) {
                throw ValidationException::withMessages(['fingerprint' => 'Состав или решения по заказу изменились. Откройте подтверждение заново.']);
            }
            if (! $locked->items()->exists()) {
                throw ValidationException::withMessages(['fingerprint' => 'В заказе нет позиций.']);
            }
            foreach ($this->preview($locked) as $row) {
                $item = $row['item'];
                if (! $item->fulfillment_confirmed_at) {
                    $supplier = $row['supplier_id'] ? Supplier::where('is_active', true)->find($row['supplier_id']) : null;
                    app(OrderItemFulfillmentManager::class)->confirm($item, $supplier || $row['route'] === 'own_stock' ? $row['route'] : 'clarifying',
                        $supplier, $actor, 'Принят весь заказ. Резервирование по мере поступления.', $row['price'], $row['status'], useOwnStockSnapshot: false);
                    $item->forceFill(['fulfillment_available_quantity' => $row['available'],
                        'fulfillment_expected_date' => in_array($row['status'], ['partial', 'awaiting_supply', 'clarifying'], true) ? $expectedDate : null])->save();
                }
                if (! $item->fulfillment_reservation_requested_at) {
                    $item->forceFill(['fulfillment_reservation_requested_at' => now()])->save();
                }
            }
            if ($customerNote !== null) {
                $locked->update(['fulfillment_customer_note' => trim($customerNote)]);
            }
            // Acceptance saves all positions; 1C is an explicit later step
            // after execution and delivery preparation.

            return $locked->fresh(['items', 'user']);
        });
        // A delivery failure must not undo the accepted items or enqueue another 1C order.
        try {
            $result = app(OrderAvailabilityConfirmation::class)->confirmAndNotify($order, $actor);
        } catch (\Throwable $exception) {
            report($exception);
            $result = ['order' => $order->fresh(), 'notified' => false];
            $warning = collect([$warning, 'Заказ сохранён. Не удалось отправить письмо клиенту; повторите уведомление.'])->filter()->implode(' ');
        }

        return $result + ['reservation_warning' => $warning];
    }
}

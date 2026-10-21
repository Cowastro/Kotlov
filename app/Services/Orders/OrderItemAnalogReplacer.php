<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class OrderItemAnalogReplacer
{
    /** @return array{old_name:string,new_name:string,quantity:int} */
    public function replace(Order $order, int $itemId, int $productId, ?User $manager): array
    {
        return DB::transaction(function () use ($order, $itemId, $productId, $manager): array {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (! in_array($lockedOrder->status, ['new', 'checking'], true)
                || $lockedOrder->archived_at !== null
                || $lockedOrder->payment_status === 'paid') {
                throw new DomainException('Подбирать аналог можно только до подтверждения наличия и оплаты заказа.');
            }

            $item = OrderItem::query()
                ->where('order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->findOrFail($itemId);
            $product = Product::query()->active()->notArchived()->findOrFail($productId);

            if ((int) $item->product_id === (int) $product->id) {
                throw new DomainException('Выберите другой товар в качестве аналога.');
            }

            $oldName = $item->product_name;
            $item->update([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'price' => (float) $product->price,
            ]);

            $subtotal = round((float) $lockedOrder->items()->sum('total'), 2);
            $lockedOrder->forceFill([
                'subtotal' => $subtotal,
                'total' => round(max(0, $subtotal - (float) $lockedOrder->discount) + (float) $lockedOrder->delivery_price, 2),
                'corrected_at' => now(),
                'corrected_by' => $manager?->id,
                'manager_id' => $lockedOrder->manager_id ?: $manager?->id,
            ])->save();

            OrderStatusHistory::query()->create([
                'order_id' => $lockedOrder->id,
                'user_id' => $manager?->id,
                'status_from' => $lockedOrder->status,
                'status_to' => $lockedOrder->status,
                'comment' => 'Подобран аналог: «'.$oldName.'» заменён на «'.$product->name.'». Наличие новой позиции требуется подтвердить заново.',
            ]);

            return [
                'old_name' => $oldName,
                'new_name' => $product->name,
                'quantity' => (int) $item->quantity,
            ];
        });
    }
}

<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\SupplierOrderRequestItem;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class OrderItemAnalogReplacer
{
    /** @return array{old_name:string,new_name:string,quantity:int} */
    public function replace(Order $order, int $itemId, int $productId, ?User $manager, bool $keepSalePrice = false, ?string $note = null, ?int $expectedProductId = null): array
    {
        $manager = $manager?->fresh();
        if (! $manager?->is_active || ! $manager->isManager()) {
            throw new DomainException('Заменять позиции может только менеджер.');
        }

        return DB::transaction(function () use ($order, $itemId, $productId, $manager, $keepSalePrice, $note, $expectedProductId): array {
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

            if ($expectedProductId !== null && (int) $item->product_id !== $expectedProductId) {
                throw new DomainException('Позиция уже изменена в другой вкладке. Обновите заказ.');
            }
            if (SupplierOrderRequestItem::query()->where('order_item_id', $item->id)
                ->whereHas('request', fn ($q) => $q->whereIn('status', ['draft', 'sent', 'acknowledged', 'fulfilled']))->exists()) {
                throw new DomainException('У позиции есть заявка поставщику. Сначала отмените её или согласуйте изменение резерва.');
            }
            if (! $keepSalePrice && (float) $product->price <= 0) {
                throw new DomainException('Цена новой карточки не указана. Согласуйте цену перед заменой.');
            }

            if ((int) $item->product_id === (int) $product->id) {
                throw new DomainException('Выберите другой товар в качестве аналога.');
            }

            $oldName = $item->product_name;
            $oldSku = $item->product_sku;
            $oldPrice = (float) $item->price;
            $item->update([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'price' => $keepSalePrice ? $oldPrice : (float) $product->price,
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
                'comment' => 'Замена позиции: «'.$oldName.'» ['.$oldSku.'] заменён на «'.$product->name.'» ['.$product->sku.']. Количество '.$item->quantity
                    .'. Цена '.number_format($oldPrice, 2, '.', '').' → '.number_format((float) $item->price, 2, '.', '').' BYN. '
                    .($keepSalePrice ? 'Согласованная цена клиента сохранена. ' : 'Применена цена новой карточки. ')
                    .'Наличие новой позиции требуется подтвердить заново.'.(filled($note) ? ' Причина: '.mb_substr(trim($note), 0, 500) : ''),
            ]);

            return [
                'old_name' => $oldName,
                'new_name' => $product->name,
                'quantity' => (int) $item->quantity,
            ];
        });
    }
}

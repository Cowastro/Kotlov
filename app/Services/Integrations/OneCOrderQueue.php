<?php

namespace App\Services\Integrations;

use App\Models\IntegrationSource;
use App\Models\Order;
use App\Models\OrderIntegrationDelivery;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class OneCOrderQueue
{
    public function __construct(private readonly OrderIntegrationRouter $router) {}

    /** @param array<string, mixed> $orderAttributes */
    public function queue(Order $order, ?User $actor, array $orderAttributes = []): OrderIntegrationDelivery
    {
        return DB::transaction(function () use ($order, $actor, $orderAttributes): OrderIntegrationDelivery {
            $lockedOrder = Order::query()->with('items.integrationProduct')->lockForUpdate()->findOrFail($order->id);
            $source = IntegrationSource::query()
                ->where('code', 'onec')
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $source) {
                throw new DomainException('Активное подключение центральной 1С не настроено.');
            }

            if ($lockedOrder->status !== 'confirmed') {
                throw new DomainException('Передать в 1С можно только подтверждённый заказ.');
            }

            if ($lockedOrder->items->isNotEmpty()
                && $lockedOrder->items->contains(fn ($item): bool => blank($item->fulfillment_confirmed_at))) {
                throw new DomainException('Сначала подтвердите исполнение всех позиций заказа.');
            }

            if (! $this->router->orderBelongsToSource($lockedOrder, $source)) {
                throw new DomainException('Позиции заказа не сопоставлены с центральной 1С.');
            }

            $delivery = OrderIntegrationDelivery::query()->lockForUpdate()->firstOrNew([
                'order_id' => $lockedOrder->id,
                'integration_source_id' => $source->id,
            ]);

            if ($delivery->exported_at || in_array($delivery->status, [
                OrderIntegrationDelivery::STATUS_SENT,
                OrderIntegrationDelivery::STATUS_ACKNOWLEDGED,
            ], true)) {
                throw new DomainException('Заказ уже был передан в 1С. Повторная передача заблокирована.');
            }

            $lockedOrder->transitionTo(
                'processing',
                'Заказ передан в очередь центральной 1С.',
                $orderAttributes,
            );

            $delivery->fill([
                'status' => OrderIntegrationDelivery::STATUS_PENDING,
                'external_id' => 'kotlov-order-'.$lockedOrder->id,
                'queued_at' => now(),
                'queued_by' => $actor?->id,
                'last_attempted_at' => null,
                'exported_at' => null,
                'status_received_at' => null,
                'last_error' => null,
            ])->save();

            return $delivery->fresh(['source', 'queuedBy']);
        });
    }
}

<?php

namespace App\Services\Shipping;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EvropochtaShipmentManagement
{
    public function __construct(private readonly Evropochta $carrier) {}

    private function order(Order $order, User $actor, bool $edit): Order
    {
        $actor = $actor->fresh();
        abort_unless($actor?->is_active && $actor->isManager(), 403);
        $current = OrderResource::getEloquentQuery()->findOrFail($order->id);
        abort_unless($edit ? OrderResource::canEdit($current) : OrderResource::canView($current), 403);
        if ($current->delivery_type !== 'evropochta' || blank($current->evropochta_tracking_number)) {
            throw new EvropochtaShipmentException('У заказа нет отправления Европочты.');
        }
        if ($edit && ($current->archived_at || in_array($current->status, ['cancelled', 'delivered'], true))) {
            throw new EvropochtaShipmentException('Закрытое отправление недоступно для изменения.');
        }
        return $current;
    }

    private function key(Order $order): string
    {
        return 'evropochta.order-status.v2.'.$order->evropochta_tracking_number;
    }

    private function remember(Order $order, array $status): void
    {
        $key = $this->key($order);
        Cache::put($key.':latest', $status, now()->addDays(30));
        Cache::put($key.':refreshing', true, now()->addMinutes(3));
        Cache::forget($key.':unavailable');
        app(CarrierOrderProgress::class)->euro($order, $status, now(), $order->evropochta_tracking_number);
    }

    public function refresh(Order $order, User $actor): string
    {
        $order = $this->order($order, $actor, false);
        $key = $this->key($order);
        $result = Cache::lock($key.':request', 45)->get(function () use ($order, $key): string {
            if (! Cache::add($key.':refreshing', true, now()->addMinutes(3))) {
                return 'Статус уже проверялся недавно. Повторная проверка доступна через 3 минуты.';
            }
            $number = $order->evropochta_tracking_number;
            $observedAt = now();
            try {
                $status = $this->carrier->shipmentStatus($number);
            } catch (EvropochtaShipmentException $exception) {
                app(CarrierOrderProgress::class)->euroCheckFailed($order, $number, $observedAt);

                throw $exception;
            }
            $this->remember($order, $status);
            return $status['status'];
        });
        return $result ?: 'Запрос к Европочте уже выполняется.';
    }

    public function changePayment(Order $order, User $actor, string $review): void
    {
        $this->mutate($order, $actor, function (Order $locked) use ($actor, $review): void {
            if (! hash_equals(EvropochtaPayment::signature($locked), $review)) {
                throw new EvropochtaShipmentException('Расчёт изменился. Откройте окно и проверьте итог заново.');
            }
            if ($reason = EvropochtaPayment::blocker($locked)) throw new EvropochtaShipmentException($reason);
            $amount = EvropochtaPayment::collect($locked);
            $this->carrier->updatePayment($locked->evropochta_tracking_number, $amount);
            $quote = $locked->evropochta_quote ?? [];
            $quote['shipment'] = array_replace($quote['shipment'] ?? [], [
                'number' => $locked->evropochta_tracking_number, 'payment_amount' => $amount,
                'shipment_payer' => EvropochtaPayment::SENDER, 'cash_on_delivery_payer' => EvropochtaPayment::SENDER,
                'source' => 'api_update', 'recorded_at' => now()->toIso8601String(), 'status' => 'Заявка создана',
            ]);
            $locked->evropochta_quote = $quote;
            if ($locked->payment_status === 'pending') $locked->payment_type = EvropochtaCheckout::COD_PAYMENT;
            $locked->save();
            $locked->statusHistory()->create(['user_id' => $actor->id, 'status_from' => $locked->status,
                'status_to' => $locked->status, 'comment' => 'Европочта подтвердила изменение через API: к получению '
                    .number_format($amount, 2, ',', ' ').' BYN. Доставка и комиссия — за счёт магазина.']);
        });
    }

    public function cancel(Order $order, User $actor): void
    {
        $this->mutate($order, $actor, function (Order $locked) use ($actor): void {
            $status = $this->carrier->cancelShipment($locked->evropochta_tracking_number);
            $this->remember($locked, $status);
            $locked->statusHistory()->create(['user_id' => $actor->id, 'status_from' => $locked->status,
                'status_to' => $locked->status, 'comment' => 'Европочта подтвердила отмену отправления через API. Сам заказ не отменён.']);
        });
    }

    private function mutate(Order $order, User $actor, callable $action): void
    {
        $order = $this->order($order, $actor, true);
        $result = Cache::lock($this->key($order).':request', 45)->get(function () use ($order, $actor, $action): bool {
            DB::transaction(function () use ($order, $actor, $action): void {
                $locked = Order::lockForUpdate()->findOrFail($order->id);
                if ($locked->evropochta_tracking_number !== $order->evropochta_tracking_number) {
                    throw new EvropochtaShipmentException('Трек изменился. Откройте отправление заново.');
                }
                $locked = $this->order($locked, $actor, true);
                $status = $this->carrier->shipmentStatus($locked->evropochta_tracking_number);
                if ($status['code'] !== Evropochta::STATUS_CREATED) {
                    throw new EvropochtaShipmentException('Европочта уже приняла посылку или отменила заявку. Изменение и отмена здесь недоступны.');
                }
                $action($locked);
            });
            return true;
        });
        if (! $result) throw new EvropochtaShipmentException('Другой запрос к Европочте уже выполняется. Повторите позже.');
    }
}

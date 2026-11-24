<?php

namespace App\Services\Shipping;

use App\Jobs\SendEvropochtaTrackingSms;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Telephony\PhoneNumber;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class EvropochtaOrderShipment
{
    public function __construct(private readonly Evropochta $carrier) {}

    public function preparationUnavailableReason(Order $order): ?string
    {
        if ($order->status !== 'processing') return 'Сначала подтвердите заказ и передайте его в исполнение.';
        if ($order->delivery_type !== EvropochtaCheckout::METHOD
            || in_array($order->status, ['shipped', 'delivered', 'cancelled', 'completed'], true)
            || filled($order->evropochta_tracking_number)) {
            return 'Подготовка доступна для заказа Европочты до оформления отправления.';
        }

        return $this->carrier->configured() ? null : 'Ключ API Европочты не настроен.';
    }

    public function prepare(Order $order, array $input): array
    {
        if ($reason = $this->preparationUnavailableReason($order)) {
            throw new EvropochtaShipmentException($reason);
        }

        $data = Validator::make($input, [
            'store_id_start' => ['required', 'integer', 'min:1'],
            'store_id_finish' => ['required', 'integer', 'min:1'],
            'weight' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:50'],
            'length' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:250'],
            'width' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:250'],
            'height' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:250'],
        ])->validate();
        $destination = (int) $data['store_id_finish'];
        $parcel = [
            'store_id_start' => (int) $data['store_id_start'],
            'weight' => (float) $data['weight'],
            'length' => (float) $data['length'],
            'width' => (float) $data['width'],
            'height' => (float) $data['height'],
        ];

        if ($destination === $parcel['store_id_start']) {
            throw ValidationException::withMessages(['store_id_finish' => 'Выберите другое отделение получения.']);
        }

        $items = $this->orderItems($order);
        if ($items->isEmpty()) {
            throw new EvropochtaShipmentException('Добавьте товары в заказ перед подготовкой отправления.');
        }
        $signature = $this->itemSignature($items);

        try {
            $quote = $this->carrier->estimate($parcel, $destination);
        } catch (RuntimeException $error) {
            throw new EvropochtaShipmentException($error->getMessage(), previous: $error);
        }

        return DB::transaction(function () use ($order, $quote, $signature): array {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            if ($reason = $this->preparationUnavailableReason($locked)) {
                throw new EvropochtaShipmentException($reason);
            }
            $items = $this->orderItems($locked);
            if ($this->itemSignature($items) !== $signature) {
                throw new EvropochtaShipmentException('Состав заказа изменился. Проверьте коробку и пересчитайте тариф.');
            }

            $subtotal = round((float) $items->sum('total'), 2);
            $price = (float) $quote['customer_price'];
            $total = round(max(0, $subtotal - max(0, (float) $locked->discount)) + $price, 2);

            if (in_array($locked->payment_status, ['paid', 'refunded'], true) && $total !== (float) $locked->total) {
                throw new EvropochtaShipmentException('Новый тариф меняет итог заказа с проведённой оплатой. Сначала согласуйте сумму оплаты.');
            }

            $quote['order_item_signature'] = $signature;
            if (! $locked->update([
                'evropochta_quote' => $quote,
                'delivery_city' => $quote['city'],
                'delivery_region' => $quote['region'] ?: null,
                'delivery_address' => $quote['address'],
                'delivery_price' => $price,
                'subtotal' => $subtotal,
                'total' => $total,
            ])) {
                throw new EvropochtaShipmentException('Не удалось сохранить расчёт Европочты в заказе.');
            }

            return $quote;
        });
    }

    public function bindQuoteToOrder(array $quote, Order $order): array
    {
        return $quote + ['order_item_signature' => $this->itemSignature($this->orderItems($order))];
    }

    /** @return Collection<int, OrderItem> */
    private function orderItems(Order $order): Collection
    {
        return $order->items()->orderBy('id')->get(['id', 'product_id', 'product_sku', 'quantity', 'total']);
    }

    private function itemSignature(Collection $items): string
    {
        $contents = $items->map(fn (OrderItem $item): array => [
            (int) $item->id, (int) $item->product_id, (string) $item->product_sku, (int) $item->quantity,
        ]);

        return hash('sha256', $contents->toJson());
    }

    public function unavailableReason(Order $order): ?string
    {
        if ($order->status !== 'processing' && blank($order->evropochta_tracking_number)) return 'Сначала подтвердите заказ и передайте его в исполнение.';
        if ($order->delivery_type !== EvropochtaCheckout::METHOD || in_array($order->status, ['cancelled', 'delivered'], true)) {
            return 'Этот заказ недоступен для оформления Европочтой.';
        }
        if (! $this->carrier->configured()) {
            return 'Ключ API Европочты не настроен.';
        }

        if ($order->payment_type === EvropochtaCheckout::COD_PAYMENT
            && ($order->payment_status !== 'pending' || (float) $order->total <= 0)) {
            return 'Для наложенного платежа нужен заказ с положительной суммой, ожидающий оплаты.';
        }

        $signature = $order->evropochta_quote['order_item_signature'] ?? null;
        if ($signature !== null && $signature !== $this->itemSignature($this->orderItems($order))) {
            return 'Состав заказа изменился. Подготовьте отправление и пересчитайте тариф.';
        }

        $parcel = $order->evropochta_quote['parcel'] ?? [];
        $destination = $order->evropochta_quote['store_id_finish'] ?? null;
        $origin = $parcel['store_id_start'] ?? null;
        if (! filter_var($origin, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            || ! filter_var($destination, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            || (int) $origin === (int) $destination) {
            return 'В заказе нет подтверждённых отделений отправления и получения.';
        }
        if (! is_numeric($parcel['weight'] ?? null) || $parcel['weight'] < 0.01 || $parcel['weight'] > 50) {
            return 'В заказе нет допустимого веса посылки.';
        }
        foreach (['length', 'width', 'height'] as $side) {
            if (! is_numeric($parcel[$side] ?? null) || (float) $parcel[$side] < 0.01) {
                return 'В заказе не указаны все размеры посылки в упаковке.';
            }
        }

        try {
            Evropochta::isOversize($parcel);
        } catch (RuntimeException $error) {
            return $error->getMessage();
        }

        return null;
    }

    public function create(Order $order, array $recipient): string
    {
        return DB::transaction(function () use ($order, $recipient): string {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            if ($lockedOrder->delivery_type === EvropochtaCheckout::METHOD && filled($lockedOrder->evropochta_tracking_number)) {
                return $lockedOrder->evropochta_tracking_number;
            }
            if ($reason = $this->unavailableReason($lockedOrder)) {
                throw new EvropochtaShipmentException($reason);
            }

            $contact = Validator::make($recipient, [
                'receiver_name' => ['required', 'string', 'min:2', 'max:100'],
                'receiver_surname' => ['required', 'string', 'min:2', 'max:100'],
                'receiver_patronymic_name' => ['nullable', 'string', 'max:100'],
                'receiver_phone_number' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]+$/D'],
            ])->validate();
            $rawPhone = $contact['receiver_phone_number'];
            $phone = PhoneNumber::normalize(PhoneNumber::format($rawPhone) ?? $rawPhone);
            if (! $phone || ! preg_match('/^\d{7,15}$/D', $phone)) {
                throw ValidationException::withMessages(['receiver_phone_number' => 'Укажите один номер телефона с кодом страны.']);
            }

            $quote = $lockedOrder->evropochta_quote;
            $parcel = $quote['parcel'];
            $payload = [
                'external_id' => 'KOTLOV-ORDER-'.$lockedOrder->getKey(),
                'is_juristic' => true,
                'delivery_type' => 1,
                'is_auto_delivery' => false,
                'shipment_payer' => 0,
                'store_id_start' => (int) $parcel['store_id_start'],
                'store_id_finish' => (int) $quote['store_id_finish'],
                'weight' => round((float) $parcel['weight'], 2),
                'length' => round((float) $parcel['length'], 2),
                'width' => round((float) $parcel['width'], 2),
                'height' => round((float) $parcel['height'], 2),
                'is_oversize' => Evropochta::isOversize($parcel),
                'receiver_name' => trim($contact['receiver_name']),
                'receiver_surname' => trim($contact['receiver_surname']),
                'receiver_phone_number' => $phone,
            ];
            if (filled($contact['receiver_patronymic_name'] ?? null)) {
                $payload['receiver_patronymic_name'] = trim($contact['receiver_patronymic_name']);
            }

            if ($lockedOrder->payment_type === EvropochtaCheckout::COD_PAYMENT) {
                $amount = (float) $lockedOrder->total;
                $payload['payment_amount'] = $amount;
                $payload['declared_amount'] = $amount;
                $payload['cash_on_delivery_payer'] = 0;
            }

            // The order lock prevents parallel requests. The carrier's external_id also protects retries after a timeout.
            $number = $this->carrier->createShipment($payload);
            $notify = (bool) ($recipient['notify_receiver'] ?? true);
            $smsStatus = match (true) {
                ! $notify => 'skipped',
                ! SendEvropochtaTrackingSms::configured() => 'unavailable',
                default => 'pending',
            };
            $lockedOrder->evropochta_tracking_number = $number;
            $lockedOrder->evropochta_sms_status = $smsStatus;
            // Keep the amounts sent at creation, rather than recomputing them from a later edited order.
            $quote['shipment'] = ['number' => $number, 'status' => 'Заявка создана',
                'payment_amount' => $payload['payment_amount'] ?? 0,
                'declared_amount' => $payload['declared_amount'] ?? null,
                'shipment_payer' => $payload['shipment_payer'],
                'cash_on_delivery_payer' => $payload['cash_on_delivery_payer'] ?? null,
                'created_at' => now()->toIso8601String()];
            $lockedOrder->evropochta_quote = $quote;
            if (! $lockedOrder->save()) {
                throw new EvropochtaShipmentException('Трек-номер получен, но не сохранён. Повторите действие для восстановления номера.');
            }

            app(\App\Services\Orders\EvropochtaTrackingSmsJournal::class)->sync($lockedOrder);

            if ($smsStatus === 'pending') {
                $orderId = (int) $lockedOrder->getKey();
                DB::afterCommit(static function () use ($orderId, $number, $phone): void {
                    SendEvropochtaTrackingSms::dispatchAfterResponse($orderId, $number, $phone);
                });
            }

            return $number;
        });
    }
}

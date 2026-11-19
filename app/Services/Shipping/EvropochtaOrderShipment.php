<?php

namespace App\Services\Shipping;

use App\Models\Order;
use App\Services\Telephony\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class EvropochtaOrderShipment
{
    public function __construct(private readonly Evropochta $carrier) {}

    public function unavailableReason(Order $order): ?string
    {
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
            $lockedOrder->evropochta_tracking_number = $number;
            if (! $lockedOrder->save()) {
                throw new EvropochtaShipmentException('Трек-номер получен, но не сохранён. Повторите действие для восстановления номера.');
            }

            return $number;
        });
    }
}

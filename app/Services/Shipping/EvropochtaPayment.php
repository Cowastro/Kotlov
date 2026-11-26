<?php

namespace App\Services\Shipping;

use App\Models\Order;

/** One customer total; the shop pays the carrier's shipping and collection fees. */
class EvropochtaPayment
{
    public const SENDER = 0;

    public static function collect(Order $order): float
    {
        return $order->payment_status === 'paid' ? 0.0 : round((float) $order->total, 2);
    }

    public static function blocker(Order $order): ?string
    {
        if (! in_array($order->payment_status, ['pending', 'paid'], true)) {
            return 'Проверьте статус оплаты заказа перед оформлением отправления.';
        }
        if ($order->payment_status === 'pending' && (float) $order->total <= 0) {
            return 'Для наложенного платежа нужен заказ с положительной суммой, ожидающий оплаты.';
        }
        $expected = round(max(0, (float) $order->subtotal - max(0, (float) $order->discount)) + (float) $order->delivery_price, 2);
        if ((float) $order->delivery_price < 0 || (float) $order->subtotal < 0 || abs($expected - (float) $order->total) > 0.001) {
            return 'Товары, скидка и доставка не совпадают с итогом заказа. Пересчитайте заказ перед оформлением.';
        }

        return null;
    }

    public static function payload(Order $order): array
    {
        $fields = ['shipment_payer' => self::SENDER];
        if ($order->payment_status === 'pending') {
            $fields += ['payment_amount' => self::collect($order), 'declared_amount' => self::collect($order),
                'cash_on_delivery_payer' => self::SENDER];
        }

        return $fields;
    }

    public static function signature(Order $order): string
    {
        return hash('sha256', json_encode([$order->id, (int) round((float) $order->subtotal * 100),
            (int) round((float) $order->discount * 100), (int) round((float) $order->delivery_price * 100),
            (int) round((float) $order->total * 100), $order->payment_type, $order->payment_status, $order->evropochta_quote['parcel'] ?? [],
            $order->evropochta_quote['store_id_finish'] ?? null, $order->evropochta_quote['recipient'] ?? null], JSON_THROW_ON_ERROR));
    }
}

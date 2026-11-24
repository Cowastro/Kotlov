<?php

namespace App\Services\Shipping;

use App\Models\Order;
use Illuminate\Support\Facades\Cache;

class EvropochtaShipmentSummary
{
    public static function snapshot(Order $order): ?array
    {
        $snapshot = $order->evropochta_quote['shipment'] ?? null;
        return is_array($snapshot) && filled($order->evropochta_tracking_number)
            && ($snapshot['number'] ?? null) === $order->evropochta_tracking_number ? $snapshot : null;
    }

    public static function amount(Order $order): ?float
    {
        $snapshot = self::snapshot($order);
        if ($snapshot !== null && is_numeric($snapshot['payment_amount'] ?? null)) return (float) $snapshot['payment_amount'];
        return $order->payment_type === EvropochtaCheckout::COD_PAYMENT ? (float) $order->total : null;
    }

    public static function source(Order $order): string
    {
        if (! self::snapshot($order)) return self::amount($order) !== null ? 'По заказу · проверьте сумму в заявке' : 'Сумма отправления не сохранена';
        return self::amount($order) > 0 ? 'При оформлении отправления' : 'Без наложенного платежа';
    }

    public static function payer(Order $order, string $field): string
    {
        return match (self::snapshot($order)[$field] ?? null) {
            0 => 'Отправитель',
            1 => 'Получатель',
            default => 'Не сохранён',
        };
    }

    public static function status(Order $order): string
    {
        $number = $order->evropochta_tracking_number;
        if (blank($number)) return 'Не оформлено';
        if ($order->carrier_provider === 'evropochta' && $order->carrier_reference === $number && filled($order->carrier_status_text)) {
            return $order->carrier_status_text;
        }
        return Cache::get('evropochta.order-status.v2.'.$number.':latest')['status']
            ?? (self::snapshot($order)['status'] ?? 'Статус ещё не проверен');
    }

    public static function color(Order $order): string
    {
        if ($order->carrier_provider !== 'evropochta' || $order->carrier_reference !== $order->evropochta_tracking_number) return 'gray';
        return match ($order->carrier_stage) {
            'delivered' => 'success', 'attention' => 'danger', 'ready' => 'primary',
            'transit' => 'warning', 'accepted' => 'info', default => 'gray',
        };
    }
}

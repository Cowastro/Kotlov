<?php

namespace App\Listeners;

use App\Events\NewOrderCreated;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendNewOrderToTelegram
{
    public function handle(NewOrderCreated $event): void
    {
        $token  = config('services.telegram.bot_token');
        $chatId = config('services.telegram.orders_chat_id');

        if (!$token || !$chatId) {
            return;
        }

        $order = $event->order;

        $deliveryNames = collect(config('shop.delivery_methods', []))
            ->mapWithKeys(fn($m, $k) => [$k => $m['name'] ?? $k])
            ->toArray();

        $paymentNames = collect(config('shop.payment_methods', []))
            ->mapWithKeys(fn($m, $k) => [$k => $m['name'] ?? $k])
            ->toArray();

        $items = $order->items->map(fn($item) =>
            "  • {$item->product_name}" .
            ($item->product_sku ? " [{$item->product_sku}]" : '') .
            " × {$item->quantity} шт. = " . number_format($item->total, 2, '.', ' ') . ' BYN'
        )->join("\n");

        $delivery = Order::DELIVERY_TYPES[$order->delivery_type]
            ?? $deliveryNames[$order->delivery_type]
            ?? $order->delivery_type;

        $payment = Order::PAYMENT_TYPES[$order->payment_type]
            ?? $paymentNames[$order->payment_type]
            ?? $order->payment_type;

        $address = implode(', ', array_filter([
            $order->delivery_region,
            $order->delivery_city,
            $order->delivery_address,
        ]));

        $viewUrl = url('/admin/orders/' . $order->id);

        $text = implode("\n", [
            "🛒 *Новый заказ {$order->number}*",
            "",
            "👤 *Клиент:* {$order->customer_name}",
            "📞 *Телефон:* {$order->customer_phone}",
            $order->customer_email ? "📧 *Email:* {$order->customer_email}" : null,
            "",
            "📦 *Доставка:* {$delivery}",
            $address ? "📍 *Адрес:* {$address}" : null,
            "💳 *Оплата:* {$payment}",
            "",
            "🧾 *Товары:*",
            $items,
            "",
            "💰 *Итого: " . number_format($order->total, 2, '.', ' ') . " BYN*",
            "",
            "🔗 [Открыть в админке]({$viewUrl})",
        ]);

        // убираем null-строки
        $text = implode("\n", array_filter(explode("\n", $text), fn($l) => $l !== null));

        try {
            Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id'    => $chatId,
                'text'       => $text,
                'parse_mode' => 'Markdown',
            ]);
        } catch (\Exception $e) {
            Log::error('Telegram notification failed: ' . $e->getMessage());
        }
    }
}

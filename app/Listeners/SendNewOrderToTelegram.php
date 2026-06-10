<?php

namespace App\Listeners;

use App\Events\NewOrderCreated;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendNewOrderToTelegram implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;
    public int $backoff = 30; // повтор через 30 сек при ошибке

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

        // Экранируем спецсимволы Markdown v1 в пользовательских полях
        $escape = fn(?string $s) => $s ? str_replace(['_', '*', '`', '['], ['\_', '\*', '\`', '\['], $s) : '';

        $lines = [
            "🛒 *Новый заказ {$order->number}*",
            "",
            "👤 *Клиент:* " . $escape($order->customer_name),
            "📞 *Телефон:* {$order->customer_phone}",
            $order->customer_email ? "📧 *Email:* {$order->customer_email}" : null,
            "",
            "📦 *Доставка:* " . $escape($delivery),
            $address ? "📍 *Адрес:* " . $escape($address) : null,
            "💳 *Оплата:* " . $escape($payment),
            "",
            "🧾 *Товары:*",
            $items,
            "",
            "💰 *Итого: " . number_format($order->total, 2, '.', ' ') . " BYN*",
            $order->comment ? "💬 *Комментарий клиента:* " . $escape($order->comment) : null,
            "",
            "🔗 [Открыть в админке]({$viewUrl})",
        ];

        $text = implode("\n", array_filter($lines, fn($l) => $l !== null));

        try {
            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id'      => $chatId,
                'text'         => $text,
                'parse_mode'   => 'Markdown',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [[
                        [
                            'text'          => '✋ Взять заказ',
                            'callback_data' => 'take_order:' . $order->id,
                        ],
                    ]],
                ]),
            ]);

            $messageId = $response->json('result.message_id');

            if ($messageId) {
                $order->updateQuietly(['telegram_message_id' => $messageId]);
            }
        } catch (\Throwable $e) {
            Log::error('Telegram notification failed: ' . $e->getMessage());
        }
    }
}

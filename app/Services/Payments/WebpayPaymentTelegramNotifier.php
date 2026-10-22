<?php

namespace App\Services\Payments;

use App\Models\WebpayPaymentAttempt;
use App\Services\TelegramApi;
use Illuminate\Support\Facades\Log;

class WebpayPaymentTelegramNotifier
{
    public function __construct(private readonly TelegramApi $telegram) {}

    public function notify(WebpayPaymentAttempt $attempt): void
    {
        $chatId = config('services.telegram.orders_chat_id');

        if (! config('services.telegram.bot_token') || ! $chatId) {
            Log::warning('WEBPAY Telegram notification skipped: credentials are not configured.', [
                'payment_attempt_id' => $attempt->id,
                'order_id' => $attempt->order_id,
            ]);

            return;
        }

        $claimed = WebpayPaymentAttempt::query()
            ->whereKey($attempt->id)
            ->where('status', 'paid')
            ->whereNull('telegram_notified_at')
            ->where(function ($query): void {
                $query->whereNull('telegram_notification_claimed_at')
                    ->orWhere('telegram_notification_claimed_at', '<', now()->subMinutes(5));
            })
            ->update(['telegram_notification_claimed_at' => now()]) === 1;

        if (! $claimed) {
            return;
        }

        $attempt->loadMissing('order');
        $order = $attempt->order;
        $text = implode("\n", [
            '✅ *Заказ оплачен через WEBPAY*',
            '',
            '🧾 *Заказ:* '.$this->escape($order->number),
            '💰 *Сумма:* '.number_format((float) $attempt->amount, 2, '.', ' ').' '.$this->escape($attempt->currency),
            '🔗 [Открыть заказ]('.url('/admin/orders/'.$order->id).')',
        ]);

        try {
            $result = $this->telegram->sendMessage($chatId, $text, $order->telegram_message_id ? [
                'reply_to_message_id' => (int) $order->telegram_message_id,
            ] : []);

            if (! ($result['ok'] ?? false)) {
                throw new \RuntimeException('Telegram API did not confirm delivery.');
            }

            WebpayPaymentAttempt::query()->whereKey($attempt->id)->update([
                'telegram_notified_at' => now(),
                'telegram_notification_claimed_at' => null,
            ]);
        } catch (\Throwable $exception) {
            WebpayPaymentAttempt::query()->whereKey($attempt->id)->update([
                'telegram_notification_claimed_at' => null,
            ]);
            Log::error('WEBPAY Telegram notification failed.', [
                'payment_attempt_id' => $attempt->id,
                'order_id' => $attempt->order_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function escape(?string $value): string
    {
        return str_replace(['_', '*', '`', '['], ['\_', '\*', '\`', '\['], (string) $value);
    }
}

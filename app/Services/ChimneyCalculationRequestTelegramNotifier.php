<?php

namespace App\Services;

use App\Models\ChimneyCalculationRequest;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ChimneyCalculationRequestTelegramNotifier
{
    public function __construct(private readonly TelegramApi $telegram) {}

    public function notify(ChimneyCalculationRequest $calculation): void
    {
        $this->notifyAdmin($calculation);

        $chatId = config('services.telegram.contact_requests_chat_id') ?: config('services.telegram.orders_chat_id');
        if (! config('services.telegram.bot_token') || ! $chatId) {
            Log::warning('Telegram chimney calculation notification skipped: credentials are not configured.', [
                'chimney_calculation_request_id' => $calculation->id,
            ]);

            return;
        }

        $escape = fn (?string $value): string => str_replace(
            ['_', '*', '`', '['], ['\_', '\*', '\`', '\['], (string) $value
        );
        $items = collect($calculation->items)->map(fn (array $item): string => '• '.$escape($item['name']).' × '.(int) $item['quantity'].' = '.number_format((float) $item['total'], 2, '.', ' ').' BYN'
        )->join("\n");
        $mode = $calculation->promotion_mode === 'equipment_bundle'
            ? 'оборудование + дымоход'
            : 'самостоятельный подбор дымохода';
        $url = url('/admin/chimney-calculation-requests/'.$calculation->id);
        $lines = [
            "🏗 *Новый запрос на расчёт {$calculation->number}*",
            '',
            '*Клиент:* '.$escape($calculation->customer_name),
            '*Телефон:* '.$escape($calculation->customer_phone),
            $calculation->customer_email ? '*Email:* '.$escape($calculation->customer_email) : null,
            '*Условие:* '.$escape($mode).' −'.number_format((float) $calculation->discount_percent, 0).'%',
            '',
            '*Состав:*',
            $items,
            '',
            '*Сумма товаров:* '.number_format((float) $calculation->subtotal, 2, '.', ' ').' BYN',
            '*Скидка:* −'.number_format((float) $calculation->discount, 2, '.', ' ').' BYN',
            '*Итого:* '.number_format((float) $calculation->total, 2, '.', ' ').' BYN',
            '',
            $calculation->configuration ? '*Параметры:*'."\n".$escape($calculation->configuration) : null,
            "[Открыть расчёт в админке]({$url})",
        ];

        try {
            $result = $this->telegram->sendMessage($chatId, implode("\n", array_filter($lines, fn ($line) => $line !== null)), [
                'reply_markup' => json_encode(['inline_keyboard' => [[[
                    'text' => '✅ Подтвердить и создать заказ',
                    'callback_data' => 'confirm_chimney:'.$calculation->id,
                ]]]], JSON_UNESCAPED_UNICODE),
            ]);
            $messageId = $result['result']['message_id'] ?? null;
            if ($messageId) {
                $calculation->update(['telegram_message_id' => $messageId, 'telegram_notified_at' => now()]);
            }
        } catch (\Throwable $exception) {
            Log::error('Telegram chimney calculation notification failed: '.$exception->getMessage(), [
                'chimney_calculation_request_id' => $calculation->id,
            ]);
        }
    }

    private function notifyAdmin(ChimneyCalculationRequest $calculation): void
    {
        $recipients = User::query()->whereIn('role', ['admin', 'manager', 'sales_manager'])->where('is_active', true)->get();
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::make()
            ->title('Новый запрос на расчёт дымохода '.$calculation->number)
            ->body($calculation->customer_name.' · '.$calculation->customer_phone.' · '.number_format((float) $calculation->total, 2, '.', ' ').' BYN')
            ->icon('heroicon-o-building-storefront')
            ->iconColor('warning')
            ->sendToDatabase($recipients);
    }
}

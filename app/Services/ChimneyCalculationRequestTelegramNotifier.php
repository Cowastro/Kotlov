<?php

namespace App\Services;

use App\Filament\Pages\ChimneyCalculator;
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
        $itemsCount = count($calculation->items ?? []);
        $url = ChimneyCalculator::getUrl(['calculation' => $calculation->id]);
        $lines = [
            "🏗 *Новый запрос на расчёт {$calculation->number}*",
            '',
            '*Клиент:* '.$escape($calculation->customer_name),
            '*Телефон:* '.$escape($calculation->customer_phone),
            $calculation->customer_email ? '*Email:* '.$escape($calculation->customer_email) : null,
            '*Позиций в комплекте:* '.$itemsCount,
            '*Итого:* '.number_format((float) $calculation->total, 2, '.', ' ').' BYN',
            "[Открыть заявку в калькуляторе]({$url})",
        ];

        try {
            $result = $this->telegram->sendMessage($chatId, implode("\n", array_filter($lines, fn ($line) => $line !== null)));
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

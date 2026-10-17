<?php

namespace App\Services\Telephony;

use App\Filament\Resources\TelephonyCalls\TelephonyCallResource;
use App\Models\TelephonyCall;
use App\Services\TelegramApi;
use Illuminate\Support\Facades\Log;

class MissedCallTelegramNotifier
{
    public function __construct(private readonly TelegramApi $telegram) {}

    public function send(TelephonyCall $call): bool
    {
        if (! $this->needsNotification($call) || $call->telegram_notified_at) {
            return true;
        }

        $chatId = config('services.telegram.telephony_chat_id');
        if (! config('services.telegram.bot_token') || ! $chatId) {
            Log::warning('Telegram missed call notification skipped: credentials are not configured.', [
                'telephony_call_id' => $call->id,
            ]);

            return false;
        }

        if (! $this->claim($call)) {
            return true;
        }

        $call->refresh()->loadMissing(['assignedUser', 'crmCounterparty', 'crmContact', 'order', 'matchedUser', 'contactRequest']);

        try {
            $result = $this->telegram->sendMessage(
                $chatId,
                $this->message($call),
                [
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [[
                            [
                                'text' => '☎️ Открыть звонок в CRM',
                                'url' => TelephonyCallResource::getUrl('view', ['record' => $call], panel: 'admin'),
                            ],
                        ]],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
            );

            if ($result['ok'] ?? false) {
                $call->updateQuietly([
                    'telegram_message_id' => $result['result']['message_id'] ?? null,
                    'telegram_notified_at' => now(),
                    'telegram_notification_claimed_at' => null,
                ]);

                Log::info('Telegram missed call notification sent.', [
                    'telephony_call_id' => $call->id,
                    'telegram_message_id' => $result['result']['message_id'] ?? null,
                ]);

                return true;
            }

            Log::error('Telegram missed call notification failed.', [
                'telephony_call_id' => $call->id,
                'response' => $result,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Telegram missed call notification exception: '.$exception->getMessage(), [
                'telephony_call_id' => $call->id,
            ]);
        }

        $call->updateQuietly(['telegram_notification_claimed_at' => null]);

        return false;
    }

    private function needsNotification(TelephonyCall $call): bool
    {
        return $call->direction === 'inbound'
            && $call->handling_status === 'callback_required'
            && $call->finished_at !== null;
    }

    private function claim(TelephonyCall $call): bool
    {
        return TelephonyCall::query()
            ->whereKey($call->id)
            ->whereNull('telegram_notified_at')
            ->where(function ($query): void {
                $query->whereNull('telegram_notification_claimed_at')
                    ->orWhere('telegram_notification_claimed_at', '<', now()->subMinutes(5));
            })
            ->update(['telegram_notification_claimed_at' => now()]) === 1;
    }

    private function message(TelephonyCall $call): string
    {
        $escape = static fn (mixed $value): string => htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );
        $startedAt = ($call->started_at ?? $call->created_at ?? now())
            ->copy()
            ->timezone(config('app.display_timezone', 'Europe/Minsk'));
        $phone = $this->displayPhone($call->customer_phone);
        $status = TelephonyCall::STATUSES[$call->status] ?? $call->status;
        $manager = $call->assignedUser?->name;

        $lines = [
            '☎️ <b>Пропущенный входящий звонок</b>',
            '',
            '<b>Номер:</b> <code>'.$escape($phone).'</code>',
            '<b>Клиент:</b> '.$escape($call->contact_name),
            '<b>Время:</b> '.$startedAt->format('d.m.Y H:i:s').' (Минск)',
            '<b>Длительность вызова:</b> '.$this->formatDuration((int) ($call->duration ?? 0)),
            '<b>Результат:</b> '.$escape($status),
            $manager ? '<b>Ответственный:</b> '.$escape($manager) : '<b>Ответственный:</b> не назначен',
            $call->order ? '<b>Заказ:</b> '.$escape($call->order->number) : null,
            '',
            '🔴 <b>Нужно перезвонить</b>',
        ];

        return implode("\n", array_filter($lines, static fn (?string $line): bool => $line !== null));
    }

    private function displayPhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?: '';

        return $digits !== '' ? '+'.$digits : 'Номер не определён';
    }

    private function formatDuration(int $seconds): string
    {
        return sprintf('%02d:%02d', intdiv(max(0, $seconds), 60), max(0, $seconds) % 60);
    }
}

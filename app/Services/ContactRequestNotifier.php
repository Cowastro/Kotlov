<?php

namespace App\Services;

use App\Models\ContactRequest;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ContactRequestNotifier
{
    public function __construct(private readonly TelegramApi $telegram) {}

    public function notify(ContactRequest $contactRequest): void
    {
        $this->sendAdminNotification($contactRequest);
        $this->sendTelegramNotification($contactRequest);
    }

    private function sendAdminNotification(ContactRequest $contactRequest): void
    {
        $recipients = User::query()->whereIn('role', ['admin', 'manager', 'sales_manager'])->where('is_active', true)->get();
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::make()
            ->title($contactRequest->source === 'ai_selection' ? 'Клиент просит помочь с AI-подбором' : 'Новая заявка на консультацию')
            ->body(implode(' · ', array_filter([
                $contactRequest->name,
                $contactRequest->phone,
                $contactRequest->product_name,
                $contactRequest->city,
            ])))
            ->icon('heroicon-o-chat-bubble-left-right')
            ->iconColor('warning')
            ->sendToDatabase($recipients);
    }

    private function sendTelegramNotification(ContactRequest $contactRequest): void
    {
        $chatId = config('services.telegram.contact_requests_chat_id');
        if (! config('services.telegram.bot_token') || ! $chatId) {
            Log::warning('Telegram consultation notification skipped: credentials are not configured.', [
                'contact_request_id' => $contactRequest->id,
            ]);

            return;
        }

        $escape = fn (?string $value): string => str_replace(
            ['_', '*', '`', '['],
            ['\_', '\*', '\`', '\['],
            (string) $value
        );
        $sourceLabels = [
            'product_page' => 'Карточка товара',
            'consultation_form' => 'Форма консультации',
            'quick_callback' => 'Быстрый звонок за 5 минут',
            'ai_selection' => 'AI-подбор',
        ];
        $source = $contactRequest->source
            ? ($sourceLabels[$contactRequest->source] ?? $contactRequest->source)
            : null;
        $lines = [
            $contactRequest->source === 'ai_selection' ? '🤖 *Нужна помощь с AI-подбором*' : '🔥 *Новая заявка на консультацию*',
            '',
            $source ? '*Источник:* '.$escape($source) : null,
            $contactRequest->city ? '*Город:* '.$escape($contactRequest->city) : null,
            $contactRequest->product_name ? "*Раздел / товар:*\n".$escape($contactRequest->product_name) : null,
            $contactRequest->product_url ? "*Страница:*\n".$escape($contactRequest->product_url) : null,
            '',
            '*Имя:* '.$escape($contactRequest->name),
            '*Телефон:* '.$escape($contactRequest->phone),
            $contactRequest->email ? '*Email:* '.$escape($contactRequest->email) : null,
            "*Что искал клиент:*\n".$escape($contactRequest->message ?: 'Не указано'),
            '',
            '*Дата:* '.$contactRequest->created_at->format('d.m.Y H:i'),
        ];

        try {
            $result = $this->telegram->sendMessage(
                $chatId,
                implode("\n", array_filter($lines, fn ($line) => $line !== null))
            );
            if (! ($result['ok'] ?? false)) {
                Log::error('Telegram consultation notification failed.', [
                    'contact_request_id' => $contactRequest->id,
                    'response' => $result,
                ]);
            }
        } catch (\Throwable $exception) {
            Log::error('Telegram consultation notification exception: '.$exception->getMessage(), [
                'contact_request_id' => $contactRequest->id,
            ]);
        }
    }
}

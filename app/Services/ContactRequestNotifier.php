<?php

namespace App\Services;

use App\Filament\Resources\ContactRequests\ContactRequestResource;
use App\Models\ContactRequest;
use App\Models\LeadChannel;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ContactRequestNotifier
{
    private readonly TelegramStorefrontPrice $storefrontPrice;

    public function __construct(
        private readonly TelegramApi $telegram,
        ?TelegramStorefrontPrice $storefrontPrice = null,
    ) {
        $this->storefrontPrice = $storefrontPrice ?? app(TelegramStorefrontPrice::class);
    }

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

        $isMessenger = $contactRequest->external_source === 'telegram_bot' || isset(LeadChannel::LABELS[$contactRequest->external_source]);
        $preview = $isMessenger
            ? $contactRequest->leadMessages()->where('direction', 'incoming')->latest('id')->value('body')
            : $contactRequest->message;

        Notification::make()
            ->title($isMessenger ? 'Новое сообщение · '.$contactRequest->channelLabel() : match ($contactRequest->source) {
                'ai_selection' => 'Клиент просит помочь с AI-подбором',
                'b2b_price_request' => 'Оптовик запросил цену',
                default => 'Новая заявка на консультацию',
            })
            ->body(e(implode(' · ', array_filter([
                $contactRequest->name,
                ...($isMessenger ? [] : [$contactRequest->phone, $contactRequest->product_name, $contactRequest->city]),
                Str::limit(Str::squish((string) $preview), 120),
            ]))))
            ->icon('heroicon-o-chat-bubble-left-right')
            ->iconColor('warning')
            ->viewData(['inboxAlert' => true])
            ->actions([
                Action::make('openContactRequest')
                    ->label($isMessenger ? 'Ответить' : 'Открыть обращение')
                    ->url(ContactRequestResource::getUrl('view', ['record' => $contactRequest], panel: 'admin'))
                    ->markAsRead(),
            ])
            ->sendToDatabase($recipients);
    }

    private function sendTelegramNotification(ContactRequest $contactRequest): void
    {
        // Messenger conversations already have their own inbox notifications.
        if (in_array($contactRequest->source, ['telegram_channel', 'viber', 'instagram', 'whatsapp'], true)) {
            return;
        }

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
        $source = $contactRequest->source
            ? (ContactRequest::$sources[$contactRequest->source] ?? $contactRequest->source)
            : null;
        $product = $contactRequest->product_id
            ? $contactRequest->product()->first()
            : $this->storefrontPrice->productFromUrl($contactRequest->product_url);
        $lines = [
            match ($contactRequest->source) {
                'ai_selection' => '🤖 *Нужна помощь с AI-подбором*',
                'b2b_price_request' => '💰 *Оптовик запросил цену*',
                default => '🔥 *Новая заявка на консультацию*',
            },
            '',
            $source ? '*Источник:* '.$escape($source) : null,
            $contactRequest->city ? '*Город:* '.$escape($contactRequest->city) : null,
            $contactRequest->product_name ? "*Раздел / товар:*\n".$escape($contactRequest->product_name) : null,
            $this->storefrontPrice->line($product),
            $contactRequest->product_url ? "*Страница:*\n".$escape($contactRequest->product_url) : null,
            '',
            '*Имя:* '.$escape($contactRequest->name),
            '*Телефон:* '.$escape($contactRequest->phone ?: 'Не указан'),
            $contactRequest->email ? '*Email:* '.$escape($contactRequest->email) : null,
            "*Что искал клиент:*\n".$escape($contactRequest->message ?: 'Не указано'),
            '',
            '*Дата:* '.$contactRequest->created_at->format('d.m.Y H:i'),
        ];

        try {
            $result = $this->telegram->sendMessage(
                $chatId,
                implode("\n", array_filter($lines, fn ($line) => $line !== null)),
                [
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [[
                                'text' => '✋ Взять заявку',
                                'callback_data' => 'take_contact_request:'.$contactRequest->id,
                            ]],
                            [[
                                'text' => '🗂 Открыть заявку в CRM',
                                'url' => url('/admin/contact-requests/'.$contactRequest->id),
                            ]],
                        ],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]
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

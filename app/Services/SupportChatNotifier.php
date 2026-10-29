<?php

namespace App\Services;

use App\Filament\Resources\SupportConversations\SupportConversationResource;
use App\Models\SupportConversation;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class SupportChatNotifier
{
    private readonly TelegramStorefrontPrice $storefrontPrice;

    public function __construct(
        private readonly TelegramApi $telegram,
        ?TelegramStorefrontPrice $storefrontPrice = null,
    ) {
        $this->storefrontPrice = $storefrontPrice ?? app(TelegramStorefrontPrice::class);
    }

    public function notifyNewConversation(SupportConversation $conversation): void
    {
        $this->notify($conversation, 'Новое сообщение в онлайн-чате');
        $this->sendTelegramNotification($conversation);
    }

    public function notifyNewMessage(SupportConversation $conversation): void
    {
        $this->notify($conversation, 'Клиент снова написал в онлайн-чат');
    }

    private function notify(SupportConversation $conversation, string $title): void
    {
        $recipients = User::query()
            ->whereIn('role', ['admin', 'manager', 'sales_manager'])
            ->where('is_active', true)
            ->get();

        if ($recipients->isNotEmpty()) {
            Notification::make()
                ->title($title)
                ->body($conversation->name.' · '.$conversation->phone)
                ->icon('heroicon-o-chat-bubble-left-right')
                ->iconColor('warning')
                ->actions([
                    Action::make('open')
                        ->label('Открыть чат')
                        ->url(SupportConversationResource::getUrl('view', ['record' => $conversation], panel: 'admin')),
                ])
                ->sendToDatabase($recipients);
        }
    }

    private function sendTelegramNotification(SupportConversation $conversation): void
    {
        $chatId = config('services.telegram.support_chat_id');
        if (! config('services.telegram.bot_token') || ! $chatId) {
            Log::warning('Telegram support-chat notification skipped: credentials are not configured.', [
                'support_conversation_id' => $conversation->id,
            ]);

            return;
        }

        $firstMessage = $conversation->messages()->where('sender_type', 'visitor')->oldest('id')->value('body');
        $escape = fn (?string $value): string => str_replace(
            ['_', '*', '`', '['],
            ['\_', '\*', '\`', '\['],
            (string) $value
        );
        $adminUrl = SupportConversationResource::getUrl('view', ['record' => $conversation], panel: 'admin');
        $storefrontPrice = $this->storefrontPrice->line(
            $this->storefrontPrice->productFromUrl($conversation->page_url),
        );
        $lines = [
            '💬 *Новый онлайн-чат*',
            '',
            '*Имя:* '.$escape($conversation->name),
            '*Телефон:* '.$escape($conversation->phone),
            "*Сообщение:*\n".$escape($firstMessage ?: 'Не указано'),
            $storefrontPrice,
            $conversation->page_url ? "*Страница:*\n".$escape($conversation->page_url) : null,
            '',
            '[Открыть диалог в админке]('.$adminUrl.')',
        ];

        try {
            $result = $this->telegram->sendMessage(
                $chatId,
                implode("\n", array_filter($lines, fn ($line): bool => $line !== null))
            );

            if (! ($result['ok'] ?? false)) {
                Log::error('Telegram support-chat notification failed.', [
                    'support_conversation_id' => $conversation->id,
                    'response' => $result,
                ]);
            }
        } catch (\Throwable $exception) {
            Log::error('Telegram support-chat notification exception: '.$exception->getMessage(), [
                'support_conversation_id' => $conversation->id,
            ]);
        }
    }
}

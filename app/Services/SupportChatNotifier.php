<?php

namespace App\Services;

use App\Filament\Resources\SupportConversations\SupportConversationResource;
use App\Models\SupportConversation;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class SupportChatNotifier
{
    public function notifyNewConversation(SupportConversation $conversation): void
    {
        $this->notify($conversation, 'Новое сообщение в онлайн-чате');
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

        if ($recipients->isEmpty()) {
            return;
        }

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

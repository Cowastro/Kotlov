<?php

namespace App\Filament\Resources\SupportConversations\Pages;

use App\Filament\Resources\SupportConversations\SupportConversationResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;

class ViewSupportConversation extends ViewRecord
{
    protected static string $resource = SupportConversationResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->record->messages()
            ->where('sender_type', 'visitor')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        $this->refreshConversation();
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function pollConversation(): void
    {
        $this->record->messages()
            ->where('sender_type', 'visitor')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        $this->refreshConversation();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reply')
                ->label('Ответить клиенту')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->visible(fn (): bool => $this->record->status !== 'closed')
                ->schema([
                    Textarea::make('body')
                        ->label('Сообщение')
                        ->rows(6)
                        ->required()
                        ->maxLength(2000),
                ])
                ->modalHeading('Ответ в онлайн-чат')
                ->modalDescription(fn (): string => 'Ответ увидит '.$this->record->name.' в этом диалоге.')
                ->modalSubmitActionLabel('Отправить')
                ->action(function (array $data): void {
                    $this->record->messages()->create([
                        'sender_type' => 'manager',
                        'sender_id' => auth()->id(),
                        'body' => $data['body'],
                    ]);
                    $this->record->update([
                        'status' => 'answered',
                        'assigned_user_id' => auth()->id(),
                        'last_message_at' => now(),
                        'last_manager_message_at' => now(),
                    ]);
                    $this->refreshConversation();
                    Notification::make()->success()->title('Ответ отправлен в чат')->send();
                }),
            Action::make('close')
                ->label('Закрыть диалог')
                ->icon('heroicon-o-check-circle')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status !== 'closed')
                ->action(function (): void {
                    $this->record->update(['status' => 'closed']);
                    $this->refreshConversation();
                }),
            Action::make('reopen')
                ->label('Открыть снова')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (): bool => $this->record->status === 'closed')
                ->action(function (): void {
                    $this->record->update(['status' => 'new']);
                    $this->refreshConversation();
                }),
        ];
    }

    private function refreshConversation(): void
    {
        $this->record->refresh()->load(['messages.sender', 'assignedUser']);
    }
}

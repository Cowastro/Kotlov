<?php

namespace App\Filament\Resources\SupportConversations\Pages;

use App\Filament\Resources\SupportConversations\SupportConversationResource;
use App\Models\SupportChatSetting;
use App\Services\SupportChatAutoResponder;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSupportConversations extends ListRecords
{
    protected static string $resource = SupportConversationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleAiReplies')
                ->label(fn (): string => SupportChatSetting::aiEnabled()
                    ? 'AI-ответы включены'
                    : 'Включить AI-ответы')
                ->icon(fn (): string => SupportChatSetting::aiEnabled()
                    ? 'heroicon-o-bolt'
                    : 'heroicon-o-sparkles')
                ->color(fn (): string => SupportChatSetting::aiEnabled() ? 'success' : 'gray')
                ->requiresConfirmation()
                ->modalHeading(fn (): string => SupportChatSetting::aiEnabled()
                    ? 'Выключить AI-ответы?'
                    : 'Включить безопасные AI-ответы?')
                ->modalDescription(fn (): string => SupportChatSetting::aiEnabled()
                    ? 'После выключения новые сообщения будут ждать живого консультанта.'
                    : 'DeepSeek задаст до трёх уточняющих вопросов без цен и обещаний, затем передаст запрос менеджеру. Контакты клиента уже сохранены до начала диалога.')
                ->action(function (): void {
                    $setting = SupportChatSetting::current();
                    $enabled = ! $setting->ai_enabled;

                    if ($enabled && ! SupportChatAutoResponder::configured()) {
                        Notification::make()
                            ->danger()
                            ->title('DeepSeek не настроен')
                            ->body('Добавьте AI_API_KEY и AI_API_URL на сервере.')
                            ->send();

                        return;
                    }

                    $setting->update([
                        'ai_enabled' => $enabled,
                        'enabled_by' => $enabled ? auth()->id() : null,
                        'enabled_at' => $enabled ? now() : null,
                    ]);

                    Notification::make()
                        ->success()
                        ->title($enabled ? 'AI-ответы включены' : 'AI-ответы выключены')
                        ->body($enabled
                            ? 'AI уточняет потребность, но не придумывает цены, наличие и характеристики.'
                            : 'Новые сообщения будут ждать ответа сотрудника.')
                        ->send();
                }),
        ];
    }
}

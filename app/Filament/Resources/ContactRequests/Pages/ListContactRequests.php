<?php

namespace App\Filament\Resources\ContactRequests\Pages;

use App\Filament\Pages\LeadChannelSettings;
use App\Filament\Resources\ContactRequests\ContactRequestResource;
use App\Filament\Resources\SupportConversations\SupportConversationResource;
use App\Models\ContactRequest;
use App\Models\SupportChatSetting;
use App\Services\SupportChatAutoResponder;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;

class ListContactRequests extends ListRecords
{
    protected static string $resource = ContactRequestResource::class;

    #[Locked]
    public string $columnsLayoutVersion = 'compact-v1';

    public function mount(): void
    {
        $this->columnsLayoutVersion = 'compact-v2';
        parent::mount();
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function getTableColumnsSessionKey(): string
    {
        return parent::getTableColumnsSessionKey().'.'.$this->columnsLayoutVersion;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('channelSettings')
                ->label('Каналы обращений')
                ->icon('heroicon-o-cog-6-tooth')
                ->visible(fn (): bool => LeadChannelSettings::canAccess())
                ->url(fn (): string => LeadChannelSettings::getUrl()),
            Action::make('toggleAiReplies')
                ->visible(fn (): bool => SupportConversationResource::canViewAny())
                ->label(fn (): string => SupportChatSetting::aiEnabled()
                    ? 'AI чата включён'
                    : 'AI чата выключен')
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

    public function getTabs(): array
    {
        if (! ContactRequest::inboxStateAvailable()) {
            return [];
        }

        return [
            'attention' => Tab::make('Требуют реакции')
                ->modifyQueryUsing(fn (Builder $query) => $query->needsAttention())
                ->excludeQueryWhenResolvingRecord()
                ->badge(ContactRequest::query()->needsAttention()->count() ?: null)
                ->badgeColor('danger'),
            'active' => Tab::make('Активные')
                ->modifyQueryUsing(fn (Builder $query) => $query->activeInbox())
                ->excludeQueryWhenResolvingRecord(),
            'archive' => Tab::make('Архив')
                ->modifyQueryUsing(fn (Builder $query) => $query->archivedInbox())
                ->excludeQueryWhenResolvingRecord(),
        ];
    }
}

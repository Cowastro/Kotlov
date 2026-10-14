<?php

namespace App\Filament\Resources\CrmMailThreads\Pages;

use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Filament\Widgets\CrmMailHealthOverview;
use App\Models\CrmMailbox;
use App\Models\CrmMailThread;
use App\Services\CrmMail\CrmMailSender;
use App\Services\CrmMail\ImapMailboxSynchronizer;
use App\Services\CrmMail\SupplierRequestMailDraftService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class ListCrmMailThreads extends ListRecords
{
    protected static string $resource = CrmMailThreadResource::class;

    public function getSubheading(): ?string
    {
        return 'Входящие, исходящие, черновики, спам и история общения с клиентами и поставщиками в одном месте.';
    }

    public function mount(): void
    {
        parent::mount();

        $query = CrmMailThread::query()->followUpDue();
        if (! auth()->user()?->isAdmin()) {
            $query->where(fn (Builder $query): Builder => $query
                ->whereNull('assigned_user_id')
                ->orWhere('assigned_user_id', auth()->id()));
        }

        $dueCount = $query->count();
        if ($dueCount > 0) {
            Notification::make()
                ->warning()
                ->title('Пора проконтролировать потенциальные сделки')
                ->body('Просроченных контактов: '.$dueCount.'. Откройте вкладку «Нужен контроль».')
                ->persistent()
                ->send();
        }
    }

    public function getTabs(): array
    {
        return [
            'inbox' => Tab::make('Рабочая почта')
                ->icon('heroicon-o-inbox')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereNotIn('status', ['draft', 'spam'])
                    ->whereHas('messages', fn (Builder $messages): Builder => $messages->where('direction', 'incoming')))
                ->badge(CrmMailThread::query()
                    ->whereNotIn('status', ['draft', 'spam'])
                    ->whereHas('messages', fn (Builder $messages): Builder => $messages->where('direction', 'incoming'))
                    ->count()),
            'unread' => Tab::make('Непрочитанные')
                ->icon('heroicon-o-envelope')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereNotIn('status', ['draft', 'spam'])
                    ->where('unread_count', '>', 0))
                ->badge(CrmMailThread::query()
                    ->whereNotIn('status', ['draft', 'spam'])
                    ->where('unread_count', '>', 0)
                    ->count() ?: null)
                ->badgeColor('warning'),
            'incoming' => Tab::make('Входящие')
                ->icon('heroicon-o-arrow-down-tray')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereNotIn('status', ['draft', 'spam'])
                    ->latestMessageDirection('incoming'))
                ->badge(CrmMailThread::query()
                    ->whereNotIn('status', ['draft', 'spam'])
                    ->latestMessageDirection('incoming')
                    ->count() ?: null)
                ->badgeColor('warning'),
            'potential_deals' => Tab::make('Потенциальные сделки')
                ->icon('heroicon-o-currency-dollar')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->potentialDeals())
                ->badge(CrmMailThread::query()->potentialDeals()->count() ?: null)
                ->badgeColor('info'),
            'follow_up' => Tab::make('Нужен контроль')
                ->icon('heroicon-o-bell-alert')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->followUpDue())
                ->badge(CrmMailThread::query()->followUpDue()->count() ?: null)
                ->badgeColor('danger'),
            'drafts' => Tab::make('Черновики')
                ->icon('heroicon-o-document-text')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'draft'))
                ->badge(CrmMailThread::query()->where('status', 'draft')->count() ?: null)
                ->badgeColor('warning'),
            'sent' => Tab::make('Исходящие')
                ->icon('heroicon-o-paper-airplane')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereNotIn('status', ['draft', 'spam'])
                    ->latestMessageDirection('outgoing'))
                ->badge(CrmMailThread::query()
                    ->whereNotIn('status', ['draft', 'spam'])
                    ->latestMessageDirection('outgoing')
                    ->count() ?: null)
                ->badgeColor('success'),
            'spam' => Tab::make('Спам')
                ->icon('heroicon-o-no-symbol')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'spam'))
                ->badge(CrmMailThread::query()->where('status', 'spam')->count() ?: null)
                ->badgeColor('danger'),
            'trash' => Tab::make('Корзина')
                ->icon('heroicon-o-trash')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->onlyTrashed())
                ->badge(CrmMailThread::onlyTrashed()->count() ?: null)
                ->badgeColor('danger'),
            'all' => Tab::make('Все письма')
                ->icon('heroicon-o-queue-list')
                ->badge(CrmMailThread::query()->count()),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sync_mail')
                ->label('Проверить почту')
                ->icon('heroicon-o-arrow-path')
                ->action(function (): void {
                    $imported = 0;
                    $skipped = 0;
                    $errors = [];

                    foreach (CrmMailbox::query()->where('is_enabled', true)->get() as $mailbox) {
                        try {
                            $result = app(ImapMailboxSynchronizer::class)->sync($mailbox);
                            $imported += $result['imported'];
                            $skipped += $result['skipped'];
                        } catch (Throwable $exception) {
                            $errors[] = $mailbox->email.': '.$exception->getMessage();
                        }
                    }

                    if ($errors !== []) {
                        Notification::make()->danger()->title('Не удалось проверить часть ящиков')
                            ->body(implode("\n", $errors))->send();

                        return;
                    }

                    Notification::make()->success()->title('Почта проверена')
                        ->body("Новых писем: {$imported}; уже загружено ранее: {$skipped}.")->send();
                }),
            Action::make('compose')
                ->label('Написать письмо')
                ->icon('heroicon-o-pencil-square')
                ->color('primary')
                ->schema(array_merge([
                    Select::make('mailbox_id')
                        ->label('Отправить с ящика')
                        ->options(fn (): array => CrmMailbox::query()->where('is_enabled', true)->pluck('email', 'id')->all())
                        ->default(fn (): ?int => CrmMailbox::query()->where('is_enabled', true)->value('id'))
                        ->required(),
                    TextInput::make('participant_name')->label('Имя / компания получателя')->maxLength(255),
                ], CrmMailThreadResource::draftFields(true)))
                ->modalHeading('Новое исходящее письмо')
                ->modalDescription('Письмо можно отправить сразу или сохранить для дальнейшего редактирования.')
                ->modalWidth('6xl')
                ->modalSubmitActionLabel('Отправить письмо')
                ->extraModalFooterActions(fn (Action $action): array => [
                    $action->makeModalSubmitAction('saveDraft', arguments: ['save_as_draft' => true])
                        ->label('Сохранить в черновики')
                        ->icon('heroicon-o-document-text')
                        ->color('gray'),
                ])
                ->action(function (array $data, array $arguments): void {
                    try {
                        $thread = app(SupplierRequestMailDraftService::class)->create(
                            CrmMailbox::query()->findOrFail($data['mailbox_id']),
                            auth()->user(),
                            CrmMailThreadResource::mergeRecipients($data, 'recipients', 'address_book_to'),
                            $data['subject'],
                            $data['body'],
                            $data['participant_name'] ?? null,
                            CrmMailThreadResource::mergeRecipients($data, 'cc', 'address_book_cc'),
                            $data['attachments'] ?? [],
                            $data['attachment_file_names'] ?? [],
                        );
                    } catch (Throwable $exception) {
                        Notification::make()->danger()->title('Письмо не сохранено')->body($exception->getMessage())->send();

                        return;
                    }

                    if ($arguments['save_as_draft'] ?? false) {
                        Notification::make()->success()->title('Письмо сохранено в черновики')
                            ->body('Его можно открыть, отредактировать и отправить позже.')->send();
                        $this->redirect(CrmMailThreadResource::getUrl('view', ['record' => $thread]));

                        return;
                    }

                    try {
                        app(CrmMailSender::class)->sendDraft($thread, auth()->user());
                        Notification::make()->success()->title('Письмо отправлено')->send();
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->warning()
                            ->title('Письмо не отправлено и сохранено в черновиках')
                            ->body($exception->getMessage())
                            ->send();
                    }

                    $this->redirect(CrmMailThreadResource::getUrl('view', ['record' => $thread]));
                }),
            Action::make('empty_trash')
                ->label('Очистить корзину')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn (): bool => $this->activeTab === 'trash'
                    && auth()->user()?->isAdmin() === true
                    && CrmMailThread::onlyTrashed()->exists())
                ->requiresConfirmation()
                ->modalHeading('Очистить корзину полностью?')
                ->modalDescription('Все переписки в корзине и их письма будут удалены навсегда. Это действие нельзя отменить.')
                ->action(function (): void {
                    $count = CrmMailThread::onlyTrashed()->count();
                    CrmMailThread::onlyTrashed()->forceDelete();

                    Notification::make()
                        ->success()
                        ->title('Корзина очищена')
                        ->body('Удалено переписок: '.$count)
                        ->send();
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            CrmMailHealthOverview::class,
        ];
    }
}

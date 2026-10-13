<?php

namespace App\Filament\Resources\CrmMailThreads\Pages;

use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Services\CrmMail\CrmMailSender;
use App\Services\CrmMail\SupplierRequestMailDraftService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Throwable;

class ViewCrmMailThread extends ViewRecord
{
    protected static string $resource = CrmMailThreadResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->record->messages()->where('direction', 'incoming')->update(['is_read' => true]);
        $this->record->forceFill(['unread_count' => 0])->save();
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('take')
                ->label('Взять в работу')->icon('heroicon-o-hand-raised')->color('warning')
                ->visible(fn (): bool => $this->record->status !== 'draft'
                    && ($this->record->assigned_user_id !== auth()->id() || $this->record->status === 'new'))
                ->action(function (): void {
                    $this->record->takeFor((int) auth()->id());
                    $this->record->refresh();
                }),
            Action::make('reply')
                ->label('Ответить')->icon('heroicon-o-paper-airplane')->color('primary')
                ->visible(fn (): bool => $this->record->status !== 'draft')
                ->schema([
                    Textarea::make('body')
                        ->label('Ответ клиенту')
                        ->rows(10)
                        ->required()
                        ->maxLength(20000)
                        ->helperText('Система добавит фирменную шапку KOTLOV, подпись менеджера, телефоны и ссылку на сайт. Текстовая версия письма создаётся автоматически.'),
                ])
                ->requiresConfirmation()
                ->modalHeading('Отправить ответ клиенту')
                ->modalDescription(fn (): string => 'Письмо будет отправлено на '.$this->record->participant_email)
                ->action(function (array $data): void {
                    try {
                        app(CrmMailSender::class)->reply($this->record, $data['body'], auth()->user());
                        $this->record->refresh();
                        Notification::make()->success()->title('Ответ отправлен')->send();
                    } catch (Throwable $exception) {
                        Notification::make()->danger()->title('Письмо не отправлено')->body($exception->getMessage())->send();
                    }
                }),
            Action::make('edit_draft')
                ->label('Редактировать')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(fn (): bool => $this->record->status === 'draft')
                ->fillForm(fn (): array => CrmMailThreadResource::draftData($this->record))
                ->schema(CrmMailThreadResource::draftFields(false))
                ->action(function (array $data): void {
                    app(SupplierRequestMailDraftService::class)->update(
                        $this->record,
                        $data['recipient'] ?? null,
                        $data['subject'],
                        $data['body'],
                        auth()->user(),
                    );
                    $this->record->refresh();
                    Notification::make()->success()->title('Черновик сохранён')->send();
                }),
            Action::make('send_draft')
                ->label('Отправить')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === 'draft')
                ->fillForm(fn (): array => CrmMailThreadResource::draftData($this->record))
                ->schema(CrmMailThreadResource::draftFields(true))
                ->requiresConfirmation()
                ->modalHeading('Отправить письмо поставщику?')
                ->modalDescription(fn (): string => filled($this->record->participant_email)
                    ? 'Письмо будет отправлено на '.$this->record->participant_email
                    : 'Сначала укажите email поставщика.')
                ->action(function (array $data): void {
                    try {
                        $thread = app(SupplierRequestMailDraftService::class)->update(
                            $this->record,
                            $data['recipient'],
                            $data['subject'],
                            $data['body'],
                            auth()->user(),
                        );
                        app(CrmMailSender::class)->sendDraft($thread, auth()->user());
                        $this->record->refresh();
                        Notification::make()->success()->title('Письмо поставщику отправлено')->send();
                    } catch (Throwable $exception) {
                        Notification::make()->danger()->title('Письмо не отправлено')->body($exception->getMessage())->send();
                    }
                }),
            EditAction::make()->label('Карточка лида')->visible(fn (): bool => $this->record->status !== 'draft'),
            Action::make('mark_spam')
                ->label('В спам')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->visible(fn (): bool => ! in_array($this->record->status, ['draft', 'spam'], true))
                ->requiresConfirmation()
                ->modalHeading('Пометить переписку как спам?')
                ->modalDescription('Переписка исчезнет из рабочей почты и будет доступна во вкладке «Спам».')
                ->action(function (): void {
                    $this->record->markAsSpam();
                    $this->redirect(CrmMailThreadResource::getUrl('index', ['activeTab' => 'spam']));
                }),
            Action::make('restore_spam')
                ->label('Вернуть из спама')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === 'spam')
                ->action(function (): void {
                    $this->record->restoreFromSpam();
                    $this->redirect(CrmMailThreadResource::getUrl('index'));
                }),
            Action::make('trash')
                ->label('В корзину')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Переместить переписку в корзину?')
                ->modalDescription('Переписку можно восстановить из вкладки «Корзина». Новое входящее письмо вернёт её в работу автоматически.')
                ->action(function (): void {
                    $this->record->delete();
                    $this->redirect(CrmMailThreadResource::getUrl('index'));
                }),
        ];
    }
}

<?php

namespace App\Filament\Resources\CrmMailThreads\Pages;

use App\Filament\Resources\CrmCounterparties\CrmCounterpartyResource;
use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Services\CrmMail\CrmMailSender;
use App\Services\CrmMail\SupplierRequestMailDraftService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
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
        if (! $this->record->hasValidRecipient()) {
            $this->record = app(SupplierRequestMailDraftService::class)
                ->refreshRecipientFromSupplier($this->record);
        }
        $this->record->messages()->where('direction', 'incoming')->update(['is_read' => true]);
        $this->record->forceFill(['unread_count' => 0])->save();

        if ($this->record->status === 'draft'
            && $this->record->supplier_order_request_id
            && ! $this->record->hasValidRecipient()) {
            Notification::make()
                ->warning()
                ->title('Не указан email поставщика')
                ->body('Добавьте электронную почту в карточку контрагента. До этого отправка письма недоступна.')
                ->persistent()
                ->send();
        }
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
                    Select::make('address_book_cc')
                        ->label('Адресная книга — копия')
                        ->options(fn (): array => CrmMailThreadResource::addressBookOptions())
                        ->multiple()->searchable()->preload(),
                    TagsInput::make('cc')
                        ->label('Копия (CC)')
                        ->placeholder('copy@example.com'),
                    Textarea::make('body')
                        ->label('Ответ клиенту')
                        ->rows(10)
                        ->required()
                        ->maxLength(20000)
                        ->helperText('Система добавит фирменную шапку KOTLOV, подпись менеджера, телефоны и ссылку на сайт. Текстовая версия письма создаётся автоматически.'),
                    FileUpload::make('attachments')
                        ->label('Вложения')
                        ->disk('local')
                        ->directory('crm-mail-attachments')
                        ->visibility('private')
                        ->multiple()->maxFiles(10)->maxSize(15360)
                        ->storeFileNamesIn('attachment_file_names')
                        ->helperText('До 10 файлов, каждый не более 15 МБ.'),
                ])
                ->requiresConfirmation()
                ->modalHeading('Отправить ответ клиенту')
                ->modalDescription(fn (): string => 'Письмо будет отправлено на '.$this->record->participant_email)
                ->action(function (array $data): void {
                    try {
                        app(CrmMailSender::class)->reply(
                            $this->record,
                            $data['body'],
                            auth()->user(),
                            CrmMailThreadResource::mergeRecipients($data, 'cc', 'address_book_cc'),
                            $data['attachments'] ?? [],
                            $data['attachment_file_names'] ?? [],
                        );
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
                ->modalWidth('6xl')
                ->action(function (array $data): void {
                    app(SupplierRequestMailDraftService::class)->update(
                        $this->record,
                        CrmMailThreadResource::mergeRecipients($data, 'recipients', 'address_book_to'),
                        $data['subject'],
                        $data['body'],
                        auth()->user(),
                        CrmMailThreadResource::mergeRecipients($data, 'cc', 'address_book_cc'),
                        $data['attachments'] ?? [],
                        $data['attachment_file_names'] ?? [],
                    );
                    $this->record->refresh();
                    Notification::make()->success()->title('Черновик сохранён')->send();
                }),
            Action::make('add_supplier_email')
                ->label('Добавить email в карточку')
                ->icon('heroicon-o-at-symbol')
                ->color('danger')
                ->visible(fn (): bool => $this->record->status === 'draft'
                    && $this->record->supplier_order_request_id !== null
                    && ! $this->record->hasValidRecipient()
                    && $this->supplierEmailCardUrl() !== null)
                ->url(fn (): ?string => $this->supplierEmailCardUrl())
                ->openUrlInNewTab(),
            Action::make('refresh_supplier_email')
                ->label('Проверить email')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn (): bool => $this->record->status === 'draft'
                    && $this->record->supplier_order_request_id !== null
                    && ! $this->record->hasValidRecipient())
                ->action(function (): void {
                    $this->record = app(SupplierRequestMailDraftService::class)
                        ->refreshRecipientFromSupplier($this->record);

                    if ($this->record->hasValidRecipient()) {
                        Notification::make()->success()->title('Email поставщика добавлен в черновик')->send();

                        return;
                    }

                    Notification::make()
                        ->warning()
                        ->title('Email всё ещё не указан')
                        ->body('Сохраните электронную почту в карточке контрагента и повторите проверку.')
                        ->send();
                }),
            Action::make('send_draft')
                ->label('Отправить')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === 'draft')
                ->disabled(fn (): bool => ! $this->record->hasValidRecipient())
                ->tooltip(fn (): ?string => $this->record->hasValidRecipient()
                    ? null
                    : 'Сначала добавьте email в карточку поставщика')
                ->fillForm(fn (): array => CrmMailThreadResource::draftData($this->record))
                ->schema(CrmMailThreadResource::draftFields(true))
                ->modalWidth('6xl')
                ->requiresConfirmation()
                ->modalHeading('Отправить письмо поставщику?')
                ->modalDescription(fn (): string => filled($this->record->participant_email)
                    ? 'Письмо будет отправлено на '.$this->record->participant_email
                    : 'Сначала укажите email поставщика.')
                ->action(function (array $data): void {
                    try {
                        $thread = app(SupplierRequestMailDraftService::class)->update(
                            $this->record,
                            CrmMailThreadResource::mergeRecipients($data, 'recipients', 'address_book_to'),
                            $data['subject'],
                            $data['body'],
                            auth()->user(),
                            CrmMailThreadResource::mergeRecipients($data, 'cc', 'address_book_cc'),
                            $data['attachments'] ?? [],
                            $data['attachment_file_names'] ?? [],
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

    private function supplierEmailCardUrl(): ?string
    {
        $this->record->loadMissing('supplierOrderRequest.supplier.crmCounterparty');
        $supplier = $this->record->supplierOrderRequest?->supplier;

        if ($supplier?->crmCounterparty) {
            return CrmCounterpartyResource::getUrl('edit', [
                'record' => $supplier->crmCounterparty,
            ]);
        }

        return $supplier
            ? SupplierResource::getUrl('edit', ['record' => $supplier])
            : null;
    }
}

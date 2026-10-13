<?php

namespace App\Filament\Resources\CrmMailThreads\Pages;

use App\Filament\Resources\CommercialDocuments\CommercialDocumentResource;
use App\Filament\Resources\CrmCounterparties\CrmCounterpartyResource;
use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Models\Category;
use App\Services\CommercialDocuments\QuickCommercialOfferSaver;
use App\Services\CommercialDocuments\QuickCommercialOfferService;
use App\Services\CrmMail\CrmMailSender;
use App\Services\CrmMail\SupplierRequestMailDraftService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
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
                ->modalHeading('Отправить ответ клиенту')
                ->modalDescription(fn (): string => 'Письмо будет отправлено на '.$this->record->participant_email)
                ->modalSubmitActionLabel('Отправить письмо')
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
            Action::make('create_quote')
                ->label('Быстрое КП')
                ->icon('heroicon-o-document-currency-dollar')
                ->color('success')
                ->visible(fn (): bool => $this->record->status !== 'draft')
                ->modalHeading('Быстрое коммерческое предложение')
                ->modalDescription('Для разбора тема и текст письма передаются настроенному DeepSeek. Проверьте найденные данные — после сохранения система подготовит PDF и черновик ответа.')
                ->modalSubmitActionLabel('Создать КП и письмо')
                ->modalWidth('5xl')
                ->fillForm(function (): array {
                    $prepared = app(QuickCommercialOfferService::class)->prepare($this->record);

                    return array_merge($prepared['prefill'], [
                        'product_ids' => $prepared['default_product_ids'],
                        'analysis_summary' => $prepared['analysis_summary'],
                        'unmatched_items_summary' => CrmMailThreadResource::unmatchedItemsSummary($prepared['unmatched_items']),
                        'save_client_to_crm' => true,
                    ]);
                })
                ->schema([
                    TextInput::make('client_company')->label('Компания')->required()->maxLength(255),
                    TextInput::make('client_name')->label('Контактное лицо')->maxLength(255),
                    TextInput::make('client_email')->label('Email')->email()->required()->maxLength(255),
                    TextInput::make('client_phone')->label('Телефон')->maxLength(80),
                    TextInput::make('client_unp')->label('УНП')->length(9),
                    TextInput::make('client_address')->label('Юридический адрес')->maxLength(500)->columnSpanFull(),
                    Textarea::make('analysis_summary')->label('Результат автоматического разбора')
                        ->disabled()->dehydrated(false)->rows(2)->columnSpanFull(),
                    Toggle::make('save_client_to_crm')->label('Сохранить компанию и контактное лицо в CRM')
                        ->helperText('Будут сохранены только проверенные вами реквизиты и контакты.'),
                    Textarea::make('unmatched_items_summary')->label('Не найдено в каталоге')
                        ->disabled()->dehydrated(false)->rows(2)->columnSpanFull(),
                    ...CrmMailThreadResource::manualProductSearchFields(),
                    Toggle::make('create_missing_products')->label('Создать черновики отсутствующих товаров')->live()
                        ->helperText('Если товара нет в базе, будет создана неактивная карточка из ручного запроса. Она не появится на сайте до проверки менеджером.'),
                    Select::make('draft_category_id')->label('Категория для новых черновиков')
                        ->options(fn (): array => Category::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()->visible(fn (Get $get): bool => (bool) $get('create_missing_products'))
                        ->required(fn (Get $get): bool => (bool) $get('create_missing_products')),
                    Select::make('product_ids')
                        ->label('Товары для КП')
                        ->multiple()->required(fn (Get $get): bool => ! (bool) $get('create_missing_products'))->searchable()
                        ->options(function (Get $get): array {
                            $service = app(QuickCommercialOfferService::class);

                            return $service->productLabels((array) $get('manual_suggestion_ids'))
                                + $service->prepare($this->record)['suggestions'];
                        })
                        ->getSearchResultsUsing(fn (string $search): array => app(QuickCommercialOfferService::class)->searchProducts($search))
                        ->getOptionLabelsUsing(fn (array $values): array => app(QuickCommercialOfferService::class)->productLabels($values))
                        ->helperText('Сверху — варианты, найденные по теме, тексту и артикулам письма. Можно найти любой товар каталога.'),
                ])
                ->action(function (array $data) {
                    $prepared = app(QuickCommercialOfferService::class)->prepare($this->record);
                    $saver = app(QuickCommercialOfferSaver::class);
                    if ($data['save_client_to_crm'] ?? false) {
                        $saver->saveClient($this->record, $data, $prepared['analysis']);
                    }
                    $missingItems = CrmMailThreadResource::quickOfferMissingItems($prepared, $data);
                    $newProducts = ($data['create_missing_products'] ?? false)
                        ? $saver->createDraftProducts($missingItems, (int) $data['draft_category_id'])
                        : [];
                    session()->put('commercial_document_prefill', array_merge($prepared['prefill'], [
                        'client_company' => $data['client_company'],
                        'client_name' => $data['client_name'] ?? null,
                        'client_email' => $data['client_email'],
                        'client_phone' => $data['client_phone'] ?? null,
                        'client_unp' => $data['client_unp'] ?? null,
                        'client_address' => $data['client_address'] ?? null,
                    ]));
                    session()->put('commercial_document_product_ids', array_values(array_unique(array_merge($data['product_ids'] ?? [], array_keys($newProducts)))));
                    session()->put('commercial_document_product_quantities', $prepared['product_quantities'] + $newProducts);
                    session()->put('commercial_document_create_mail_draft', true);

                    return redirect(CommercialDocumentResource::getUrl('create'));
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
                    : 'Сначала укажите хотя бы одного получателя')
                ->fillForm(fn (): array => CrmMailThreadResource::draftData($this->record))
                ->schema(CrmMailThreadResource::draftFields(true))
                ->modalWidth('6xl')
                ->modalSubmitActionLabel('Отправить письмо')
                ->modalHeading('Отправить письмо')
                ->modalDescription(fn (): string => filled($this->record->participant_email)
                    ? 'Письмо будет отправлено на '.$this->record->participant_email
                    : 'Укажите хотя бы одного получателя.')
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
                        Notification::make()->success()->title('Письмо отправлено')->send();
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

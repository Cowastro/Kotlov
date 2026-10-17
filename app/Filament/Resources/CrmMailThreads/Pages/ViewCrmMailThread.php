<?php

namespace App\Filament\Resources\CrmMailThreads\Pages;

use App\Filament\Resources\CommercialDocuments\CommercialDocumentResource;
use App\Filament\Resources\CrmCounterparties\CrmCounterpartyResource;
use App\Filament\Resources\CrmMailThreads\Actions\ManageMailThreadNotesAction;
use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Models\Category;
use App\Models\CrmMailMessage;
use App\Services\CommercialDocuments\QuickCommercialOfferSaver;
use App\Services\CommercialDocuments\QuickCommercialOfferService;
use App\Services\CrmMail\CrmMailSender;
use App\Services\CrmMail\SupplierRequestMailDraftService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Throwable;

class ViewCrmMailThread extends ViewRecord
{
    protected static string $resource = CrmMailThreadResource::class;

    protected array $extraBodyAttributes = ['class' => 'crm-mail-thread-page'];

    public ?int $forwardMessageId = null;

    public ?int $replyMessageId = null;

    /** @var array<int> */
    public array $initiallyUnreadMessageIds = [];

    public function mount(int|string $record): void
    {
        parent::mount($record);
        if (! $this->record->hasValidRecipient()) {
            $this->record = app(SupplierRequestMailDraftService::class)
                ->refreshRecipientFromSupplier($this->record);
        }

        $this->initiallyUnreadMessageIds = $this->record->messages()
            ->where('direction', 'incoming')
            ->where('is_read', false)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

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
            ManageMailThreadNotesAction::make(),
            Action::make('take')
                ->label('Взять в работу')->icon('heroicon-o-hand-raised')->color('warning')
                ->extraAttributes(['class' => 'crm-thread-action crm-thread-action--take'])
                ->visible(fn (): bool => $this->canTakeThread())
                ->action(function (): void {
                    $this->takeThread();
                }),
            Action::make('reply')
                ->label('Ответить')->icon('heroicon-o-paper-airplane')->color('primary')
                ->extraAttributes(['class' => 'crm-thread-action crm-thread-action--reply'])
                ->visible(fn (): bool => $this->record->status !== 'draft')
                ->schema([
                    CrmMailThreadResource::addressBookSelect('address_book_cc', 'Адресная книга — копия'),
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
                ->modalDescription(fn (): string => 'Ответ на «'.($this->replySourceMessage()->subject ?: 'Без темы').'» будет отправлен на '.$this->replyRecipient())
                ->modalSubmitActionLabel('Отправить письмо')
                ->action(function (array $data): void {
                    try {
                        $message = $this->replySourceMessage();
                        app(CrmMailSender::class)->reply(
                            $this->record,
                            $data['body'],
                            auth()->user(),
                            CrmMailThreadResource::mergeRecipients($data, 'cc', 'address_book_cc'),
                            $data['attachments'] ?? [],
                            $data['attachment_file_names'] ?? [],
                            $message,
                        );
                        $this->replyMessageId = null;
                        $this->refreshThreadForDisplay();
                        Notification::make()->success()->title('Ответ отправлен')->send();
                    } catch (Throwable $exception) {
                        Notification::make()->danger()->title('Письмо не отправлено')->body($exception->getMessage())->send();
                    }
                }),
            Action::make('forward')
                ->label('Переслать')
                ->icon('heroicon-o-arrow-right')
                ->color('gray')
                ->extraAttributes(['class' => 'crm-thread-action crm-thread-action--forward'])
                ->visible(fn (): bool => $this->record->status !== 'draft')
                ->fillForm(fn (): array => $this->forwardFormData())
                ->schema([
                    CrmMailThreadResource::addressBookSelect('address_book_to', 'Адресная книга — кому'),
                    TagsInput::make('recipients')
                        ->label('Кому')
                        ->placeholder('recipient@example.com')
                        ->helperText('Укажите хотя бы одного получателя вручную или выберите его из адресной книги.'),
                    CrmMailThreadResource::addressBookSelect('address_book_cc', 'Адресная книга — копия'),
                    TagsInput::make('cc')
                        ->label('Копия (CC)')
                        ->placeholder('copy@example.com'),
                    TextInput::make('subject')
                        ->label('Тема')
                        ->required()
                        ->maxLength(998),
                    Textarea::make('body')
                        ->label('Текст письма')
                        ->rows(14)
                        ->required()
                        ->maxLength(50000),
                    CheckboxList::make('original_attachments')
                        ->label('Вложения исходного письма')
                        ->options(fn (): array => $this->forwardAttachmentOptions())
                        ->visible(fn (): bool => $this->forwardAttachmentOptions() !== [])
                        ->columns(1),
                    FileUpload::make('attachments')
                        ->label('Добавить вложения')
                        ->disk('local')
                        ->directory('crm-mail-attachments')
                        ->visibility('private')
                        ->multiple()->maxFiles(10)->maxSize(15360)
                        ->storeFileNamesIn('attachment_file_names')
                        ->helperText('До 10 дополнительных файлов, каждый не более 15 МБ.'),
                ])
                ->modalHeading('Переслать письмо')
                ->modalDescription('Получатели пересылки не изменят клиента, указанного в карточке переписки.')
                ->modalSubmitActionLabel('Переслать')
                ->modalWidth('4xl')
                ->action(function (array $data): void {
                    try {
                        $message = $this->forwardSourceMessage();
                        $recipients = CrmMailThreadResource::mergeRecipients($data, 'recipients', 'address_book_to');
                        $cc = CrmMailThreadResource::mergeRecipients($data, 'cc', 'address_book_cc');
                        $originalAttachments = $this->selectedForwardAttachments(
                            $message,
                            $data['original_attachments'] ?? [],
                        );
                        $attachments = array_merge(
                            array_column($originalAttachments, 'path'),
                            array_values($data['attachments'] ?? []),
                        );
                        $attachmentNames = collect($originalAttachments)
                            ->mapWithKeys(fn (array $attachment): array => [$attachment['path'] => $attachment['name']])
                            ->merge($data['attachment_file_names'] ?? [])
                            ->all();

                        app(CrmMailSender::class)->forward(
                            $this->record,
                            $message,
                            $recipients,
                            $data['subject'],
                            $data['body'],
                            auth()->user(),
                            $cc,
                            $attachments,
                            $attachmentNames,
                        );
                        $this->forwardMessageId = null;
                        $this->refreshThreadForDisplay();
                        Notification::make()->success()->title('Письмо переслано')->send();
                    } catch (Throwable $exception) {
                        Notification::make()->danger()->title('Письмо не переслано')->body($exception->getMessage())->send();
                    }
                }),
            Action::make('create_quote')
                ->label('Быстрое КП')
                ->icon('heroicon-o-document-currency-dollar')
                ->color('success')
                ->extraAttributes(['class' => 'crm-thread-action crm-thread-action--quote'])
                ->visible(fn (): bool => $this->record->status !== 'draft')
                ->modalHeading('Быстрое коммерческое предложение')
                ->modalDescription('Для разбора используются тема, текст письма и PDF-вложения. Товары ищутся напрямую в нашей базе. Проверьте данные — после сохранения система подготовит PDF и черновик ответа.')
                ->modalSubmitActionLabel('Создать КП и письмо')
                ->modalWidth('7xl')
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
                    Section::make('Клиент')
                        ->compact()
                        ->columns(2)
                        ->schema([
                            TextInput::make('client_company')->label('Компания')->required()->maxLength(255),
                            TextInput::make('client_name')->label('Контактное лицо')->maxLength(255),
                            TextInput::make('client_email')->label('Email')->email()->required()->maxLength(255),
                            TextInput::make('client_phone')->label('Телефон')->maxLength(80),
                            TextInput::make('client_unp')->label('УНП')->length(9),
                            TextInput::make('client_address')->label('Юридический адрес')->maxLength(500),
                            Toggle::make('save_client_to_crm')->label('Сохранить клиента в CRM')
                                ->helperText('Сохраняются только проверенные поля.')
                                ->columnSpanFull(),
                        ]),
                    Section::make('Автоматический разбор письма')
                        ->compact()
                        ->collapsible()
                        ->collapsed()
                        ->schema([
                            Textarea::make('analysis_summary')->label('Что распознано')
                                ->disabled()->dehydrated(false)->rows(2),
                            Textarea::make('unmatched_items_summary')->label('Не найдено в каталоге')
                                ->disabled()->dehydrated(false)->rows(2),
                        ]),
                    Section::make('Подбор товара')
                        ->compact()
                        ->schema([
                            ...CrmMailThreadResource::manualProductSearchFields(),
                            Toggle::make('create_missing_products')->label('Создать черновик, если товара нет')->live()
                                ->helperText('Черновик не появится на сайте до проверки.'),
                            Select::make('draft_category_id')->label('Категория нового черновика')
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
                                ->helperText('Найденные варианты показаны выше. Можно найти любой товар каталога.'),
                        ]),
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
                ->extraAttributes(['class' => 'crm-thread-action crm-thread-action--edit-draft'])
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
                    $this->refreshThreadForDisplay();
                    Notification::make()->success()->title('Черновик сохранён')->send();
                }),
            Action::make('add_supplier_email')
                ->label('Добавить email в карточку')
                ->icon('heroicon-o-at-symbol')
                ->color('danger')
                ->extraAttributes(['class' => 'crm-thread-action crm-thread-action--supplier-email'])
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
                ->extraAttributes(['class' => 'crm-thread-action crm-thread-action--refresh-email'])
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
                ->extraAttributes(['class' => 'crm-thread-action crm-thread-action--send-draft'])
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
                        $this->refreshThreadForDisplay();
                        Notification::make()->success()->title('Письмо отправлено')->send();
                    } catch (Throwable $exception) {
                        Notification::make()->danger()->title('Письмо не отправлено')->body($exception->getMessage())->send();
                    }
                }),
            EditAction::make()
                ->label('Карточка лида')
                ->icon('heroicon-o-identification')
                ->color('gray')
                ->extraAttributes(['class' => 'crm-thread-action crm-thread-action--lead'])
                ->visible(fn (): bool => $this->record->status !== 'draft'),
            Action::make('mark_spam')
                ->label('В спам')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->extraAttributes(['class' => 'crm-thread-action crm-thread-action--spam'])
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
                ->extraAttributes(['class' => 'crm-thread-action crm-thread-action--restore'])
                ->visible(fn (): bool => $this->record->status === 'spam')
                ->action(function (): void {
                    $this->record->restoreFromSpam();
                    $this->redirect(CrmMailThreadResource::getUrl('index'));
                }),
            Action::make('trash')
                ->label('В корзину')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->extraAttributes(['class' => 'crm-thread-action crm-thread-action--trash'])
                ->requiresConfirmation()
                ->modalHeading('Переместить переписку в корзину?')
                ->modalDescription('Переписку можно восстановить из вкладки «Корзина». Новое входящее письмо вернёт её в работу автоматически.')
                ->action(function (): void {
                    $this->record->delete();
                    $this->redirect(CrmMailThreadResource::getUrl('index'));
                }),
        ];
    }

    public function openForward(int $messageId): void
    {
        $this->record->messages()->findOrFail($messageId);
        $this->forwardMessageId = $messageId;
        $this->mountAction('forward');
    }

    public function canTakeThread(): bool
    {
        return $this->record->status !== 'draft'
            && ($this->record->assigned_user_id !== auth()->id() || $this->record->status === 'new');
    }

    public function canMarkPotentialDeal(): bool
    {
        return ! in_array($this->record->status, ['draft', 'spam'], true)
            && blank($this->record->sales_stage);
    }

    public function takeThread(): void
    {
        if (! $this->canTakeThread()) {
            return;
        }

        $this->record->takeFor((int) auth()->id());
        $this->refreshThreadForDisplay();
        Notification::make()->success()->title('Переписка взята в работу')->send();
    }

    public function markPotentialDeal(): void
    {
        if (! $this->canMarkPotentialDeal()) {
            return;
        }

        $this->record->update([
            'sales_stage' => 'new_inquiry',
            'assigned_user_id' => $this->record->assigned_user_id ?: auth()->id(),
            'next_follow_up_at' => now()->addWeekday()->setTime(10, 0),
            'follow_up_note' => 'Уточнить потребность клиента и следующий шаг.',
        ]);
        $this->refreshThreadForDisplay();
        Notification::make()->success()->title('Добавлено в потенциальные сделки')->send();
    }

    private function refreshThreadForDisplay(): void
    {
        $this->record->refresh();
        $this->record->load([
            'messages' => fn ($query) => $query
                ->reorder()
                ->orderByDesc('received_at')
                ->orderByDesc('id'),
        ]);
    }

    public function openReply(int $messageId): void
    {
        $message = $this->record->messages()->findOrFail($messageId);
        abort_unless($message->direction === 'incoming', 404);

        $this->replyMessageId = $message->id;
        $this->mountAction('reply');
    }

    private function replySourceMessage(): CrmMailMessage
    {
        $query = $this->record->messages();

        if ($this->replyMessageId !== null) {
            return $query->findOrFail($this->replyMessageId);
        }

        $incoming = $query->reorder()
            ->where('direction', 'incoming')
            ->latest('received_at')
            ->latest('id')
            ->first();

        return $incoming ?? $this->record->messages()
            ->reorder()
            ->where('direction', '!=', 'draft')
            ->latest('received_at')
            ->latest('id')
            ->firstOrFail();
    }

    private function replyRecipient(): string
    {
        $message = $this->replySourceMessage();

        return $message->direction === 'incoming' && filter_var($message->from_email, FILTER_VALIDATE_EMAIL)
            ? (string) $message->from_email
            : (string) $this->record->participant_email;
    }

    private function forwardSourceMessage(): CrmMailMessage
    {
        $query = $this->record->messages();

        if ($this->forwardMessageId !== null) {
            return $query->findOrFail($this->forwardMessageId);
        }

        return $query->reorder()->latest('received_at')->latest('id')->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function forwardFormData(): array
    {
        $message = $this->forwardSourceMessage();
        $attachments = $this->forwardAttachmentOptions();

        return [
            'recipients' => [],
            'cc' => [],
            'subject' => preg_match('/^fwd?\s*:/iu', (string) $message->subject)
                ? (string) $message->subject
                : 'Fwd: '.($message->subject ?: 'Без темы'),
            'body' => app(CrmMailSender::class)->forwardedText($message),
            'original_attachments' => array_keys($attachments),
        ];
    }

    /** @return array<string, string> */
    private function forwardAttachmentOptions(): array
    {
        return collect($this->forwardSourceMessage()->metadata['attachments'] ?? [])
            ->filter(fn (mixed $attachment): bool => is_array($attachment)
                && filled($attachment['path'] ?? null)
                && str_starts_with((string) $attachment['path'], 'crm-mail-attachments/'))
            ->mapWithKeys(fn (array $attachment): array => [
                (string) $attachment['path'] => (string) ($attachment['name'] ?? basename((string) $attachment['path'])),
            ])
            ->all();
    }

    /** @return list<array{path: string, name: string}> */
    private function selectedForwardAttachments(CrmMailMessage $message, array $selectedPaths): array
    {
        $selectedPaths = array_fill_keys(array_map('strval', $selectedPaths), true);

        return collect($message->metadata['attachments'] ?? [])
            ->filter(fn (mixed $attachment): bool => is_array($attachment)
                && isset($selectedPaths[(string) ($attachment['path'] ?? '')]))
            ->map(fn (array $attachment): array => [
                'path' => (string) $attachment['path'],
                'name' => (string) ($attachment['name'] ?? basename((string) $attachment['path'])),
            ])
            ->values()
            ->all();
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

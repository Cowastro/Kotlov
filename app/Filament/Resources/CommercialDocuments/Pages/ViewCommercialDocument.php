<?php

namespace App\Filament\Resources\CommercialDocuments\Pages;

use App\Filament\Resources\CommercialDocuments\Actions\RecordCommercialDocumentContactAction;
use App\Filament\Resources\CommercialDocuments\CommercialDocumentResource;
use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\CommercialDocument;
use App\Services\CommercialContracts\ClientContractTemplateGenerator;
use App\Services\CommercialContracts\CommercialContractGenerator;
use App\Models\IntegrationSource;
use App\Models\Supplier;
use App\Services\CommercialDocuments\CommercialDocumentPortal;
use App\Services\CommercialDocuments\CommercialDocumentReadiness;
use App\Services\CommercialDocuments\CommercialDocumentWorkflow;
use App\Services\Pricing\ConfirmedPurchasePriceService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;
use Throwable;

class ViewCommercialDocument extends ViewRecord
{
    protected static string $resource = CommercialDocumentResource::class;

    public function getTitle(): string
    {
        return $this->record->number;
    }

    public function getSubheading(): ?string
    {
        return implode(' · ', [
            CommercialDocument::TYPES[$this->record->type] ?? $this->record->type,
            CommercialDocument::STATUSES[$this->record->status] ?? $this->record->status,
            $this->record->issued_at?->format('d.m.Y'),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            RecordCommercialDocumentContactAction::make(),
            Action::make('open_mail_thread')
                ->label('Открыть переписку')
                ->icon('heroicon-o-envelope-open')
                ->color('gray')
                ->visible(fn (): bool => $this->record->mailThread !== null)
                ->url(fn (): string => CrmMailThreadResource::getUrl('view', [
                    'record' => $this->record->mailThread,
                ])),
            EditAction::make()
                ->label('Редактировать')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->visible(fn (): bool => $this->record->status === 'draft'),
            ActionGroup::make([
                Action::make('confirm_purchase_price')
                    ->label('Добавить счёт поставщика')
                    ->icon('heroicon-o-document-check')
                    ->color('warning')
                    ->visible(fn (): bool => $this->record->items()->whereNotNull('product_id')->exists())
                    ->modalHeading('Подтверждённая закупочная цена')
                    ->modalDescription('Счёт станет источником закупочной цены для расчёта маржи и проверки готовности коммерческого предложения.')
                    ->schema([
                        Select::make('commercial_document_item_id')
                            ->label('Позиция коммерческого предложения')
                            ->options(fn (): array => $this->record->items()
                                ->whereNotNull('product_id')
                                ->get()
                                ->mapWithKeys(fn ($item): array => [
                                    $item->id => $item->name.' · '.number_format((float) $item->unit_price, 2, ',', ' ').' BYN',
                                ])->all())
                            ->required()
                            ->searchable(),
                        Select::make('supplier_id')
                            ->label('Поставщик из базы')
                            ->options(fn (): array => Supplier::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->helperText('Необязательно: если поставщика ещё нет в базе, укажите компанию ниже.'),
                        TextInput::make('supplier_name')
                            ->label('Компания поставщика')
                            ->required(fn (Get $get): bool => blank($get('supplier_id'))),
                        TextInput::make('document_number')
                            ->label('Номер счёта')
                            ->placeholder('Например, 9558'),
                        DatePicker::make('document_date')
                            ->label('Дата счёта')
                            ->default(today())
                            ->native(false)
                            ->required(),
                        DatePicker::make('valid_until')
                            ->label('Цена действует до')
                            ->default(today()->addDays(3))
                            ->native(false),
                        TextInput::make('source_price')
                            ->label('Цена из счёта за единицу')
                            ->numeric()
                            ->minValue(0.01)
                            ->required(),
                        Select::make('currency')
                            ->label('Валюта')
                            ->options(['BYN' => 'BYN', 'RUB' => 'RUB', 'EUR' => 'EUR', 'USD' => 'USD'])
                            ->default('BYN')
                            ->live()
                            ->required(),
                        TextInput::make('currency_rate')
                            ->label('Курс к BYN')
                            ->numeric()
                            ->minValue(0.000001)
                            ->visible(fn (Get $get): bool => ($get('currency') ?: 'BYN') !== 'BYN')
                            ->required(fn (Get $get): bool => ($get('currency') ?: 'BYN') !== 'BYN'),
                        Select::make('price_tax_mode')
                            ->label('НДС в указанной цене')
                            ->options([
                                IntegrationSource::PRICE_TAX_INCLUSIVE => 'Цена уже с НДС',
                                IntegrationSource::PRICE_TAX_EXCLUSIVE => 'Цена без НДС — добавить НДС',
                            ])
                            ->default(IntegrationSource::PRICE_TAX_INCLUSIVE)
                            ->live()
                            ->required(),
                        TextInput::make('vat_rate')
                            ->label('Ставка НДС')
                            ->numeric()
                            ->suffix('%')
                            ->default(20)
                            ->minValue(0)
                            ->required(),
                        FileUpload::make('attachment_path')
                            ->label('Входящий счёт поставщика')
                            ->helperText('Необязательно. PDF, JPG, PNG или WEBP до 10 МБ. Файл хранится приватно.')
                            ->disk('local')
                            ->directory('supplier-price-evidence')
                            ->visibility('private')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(10240),
                        Textarea::make('notes')
                            ->label('Комментарий')
                            ->rows(2),
                    ])
                    ->modalSubmitActionLabel('Сохранить источник цены')
                    ->action(function (array $data): void {
                        $item = $this->record->items()->whereKey((int) $data['commercial_document_item_id'])->firstOrFail();
                        abort_unless($item->product_id && auth()->user(), 403);

                        $source = app(ConfirmedPurchasePriceService::class)->create($item->product, $data, auth()->user());
                        $this->record->refresh();

                        Notification::make()
                            ->success()
                            ->title('Источник закупочной цены сохранён')
                            ->body(new HtmlString(
                                e($source->documentLabel()).'<br>'.e($source->supplierDisplayName() ?: 'Поставщик не указан')
                                .' · <strong>'.number_format((float) $source->price_byn, 2, ',', ' ').' BYN с НДС</strong>'
                            ))
                            ->send();
                    }),
                Action::make('pdf')
                    ->label('Открыть PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->url(fn (): string => route('admin.commercial-documents.pdf', $this->record))
                    ->openUrlInNewTab(),
                Action::make('create_contract')
                    ->label('Создать договор')
                    ->icon('heroicon-o-document-text')
                    ->color('success')
                    ->visible(fn (): bool => $this->record->type === 'quote')
                    ->modalHeading('Создать договор по нашей форме')
                    ->modalDescription('Данные клиента и товары будут взяты из КП. Проверьте реквизиты и выберите условия договора.')
                    ->modalSubmitActionLabel('Создать DOCX и PDF')
                    ->fillForm(fn (): array => [
                        'contract_date' => today(),
                        'valid_until' => now()->endOfYear()->toDateString(),
                        'buyer_name' => $this->record->client_legal_name ?: ($this->record->client_company ?: $this->record->client_name),
                        'buyer_unp' => $this->record->client_unp,
                        'buyer_address' => $this->record->client_address,
                        'buyer_postal_address' => $this->record->client_address,
                        'buyer_representative' => $this->record->clientCounterparty?->authorized_representative ?: $this->record->client_name,
                        'buyer_position' => $this->record->clientCounterparty?->representative_position ?: 'Директор',
                        'buyer_authority_basis' => $this->record->clientCounterparty?->authority_basis ?: 'Устава',
                        'buyer_iban' => $this->record->clientCounterparty?->bank_iban,
                        'buyer_bank' => $this->record->clientCounterparty?->bank_name,
                        'buyer_bic' => $this->record->clientCounterparty?->bank_bic,
                        'buyer_phone' => $this->record->client_phone,
                        'buyer_email' => $this->record->client_email,
                        'purchase_purpose' => 'own_use',
                        'regulated_goods' => false,
                        'payment_type' => 'prepayment',
                        'prepayment_percent' => 100,
                        'payment_days' => 3,
                        'delivery_method' => 'pickup',
                        'delivery_days' => 10,
                        'delivery_day_type' => 'working',
                        'delivery_start_event' => 'prepayment',
                        'auto_renew' => true,
                        'save_buyer_requisites' => true,
                    ])
                    ->schema([
                        DatePicker::make('contract_date')->label('Дата договора')->required()->native(false),
                        DatePicker::make('valid_until')->label('Действует до')->required()->native(false),
                        TextInput::make('buyer_name')->label('Юридическое наименование покупателя')->required()->columnSpanFull(),
                        TextInput::make('buyer_unp')->label('УНП покупателя')->required(),
                        TextInput::make('buyer_representative')->label('ФИО подписанта')->required(),
                        TextInput::make('buyer_position')->label('Должность')->required(),
                        TextInput::make('buyer_authority_basis')->label('Действует на основании')->required(),
                        Textarea::make('buyer_address')->label('Юридический адрес')->required()->rows(2)->columnSpanFull(),
                        Textarea::make('buyer_postal_address')->label('Почтовый адрес')->rows(2)->columnSpanFull(),
                        TextInput::make('buyer_iban')->label('IBAN')->required(),
                        TextInput::make('buyer_bank')->label('Банк')->required(),
                        TextInput::make('buyer_bic')->label('BIC')->required(),
                        TextInput::make('buyer_phone')->label('Телефон'),
                        TextInput::make('buyer_email')->label('Email')->email(),
                        Select::make('purchase_purpose')
                            ->label('Цель приобретения')
                            ->options([
                                'own_use' => 'Собственное потребление',
                                'resale' => 'Последующая розничная продажа',
                                'repair' => 'Ремонт / благоустройство',
                                'mixed' => 'Продажа, ремонт и благоустройство',
                                'other' => 'Другая цель',
                            ])->required()->live(),
                        TextInput::make('purpose_custom')
                            ->label('Другая цель приобретения')
                            ->visible(fn (Get $get): bool => $get('purchase_purpose') === 'other')
                            ->required(fn (Get $get): bool => $get('purchase_purpose') === 'other'),
                        Toggle::make('regulated_goods')
                            ->label('Товар подпадает под регулирование цен')
                            ->helperText('Добавляет обязательство соблюдать применимые ограничения по цели приобретения.'),
                        Select::make('payment_type')
                            ->label('Порядок оплаты')
                            ->options([
                                'prepayment' => '100% предоплата',
                                'partial' => 'Частичная предоплата',
                                'on_delivery' => 'Оплата при передаче',
                                'deferred' => 'Отсрочка платежа',
                            ])->required()->live(),
                        TextInput::make('prepayment_percent')
                            ->label('Предоплата, %')
                            ->numeric()->minValue(1)->maxValue(100)
                            ->visible(fn (Get $get): bool => in_array($get('payment_type'), ['prepayment', 'partial'], true))
                            ->required(fn (Get $get): bool => in_array($get('payment_type'), ['prepayment', 'partial'], true)),
                        TextInput::make('payment_days')->label('Срок оплаты, дней')->numeric()->minValue(1)->required(),
                        Select::make('delivery_method')
                            ->label('Способ поставки')
                            ->options(['pickup' => 'Самовывоз', 'supplier' => 'Доставка Поставщиком'])
                            ->required(),
                        TextInput::make('delivery_days')->label('Срок поставки, дней')->numeric()->minValue(1)->required(),
                        Select::make('delivery_day_type')
                            ->label('Тип дней')
                            ->options(['working' => 'Рабочие', 'banking' => 'Банковские', 'calendar' => 'Календарные'])
                            ->required(),
                        Select::make('delivery_start_event')
                            ->label('Срок считается')
                            ->options([
                                'prepayment' => 'От поступления предоплаты',
                                'contract' => 'От подписания договора',
                                'readiness' => 'От уведомления о готовности',
                            ])->required(),
                        Toggle::make('auto_renew')->label('Автоматически продлевать договор'),
                        Toggle::make('save_buyer_requisites')
                            ->label('Сохранить реквизиты в карточке контрагента')
                            ->helperText('В следующем договоре эти поля будут заполнены автоматически.'),
                    ])
                    ->action(function (array $data): void {
                        try {
                            $contract = app(CommercialContractGenerator::class)->create($this->record, $data);
                            Notification::make()
                                ->success()
                                ->title('Договор создан')
                                ->body($contract->number.' · DOCX и PDF находятся в меню «Документы».')
                                ->send();
                            $this->record->refresh();
                        } catch (Throwable $exception) {
                            Notification::make()->danger()->title('Не удалось создать договор')->body($exception->getMessage())->send();
                        }
                    }),
                Action::make('create_client_contract')
                    ->label('Договор по форме клиента')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('success')
                    ->visible(fn (): bool => $this->record->type === 'quote')
                    ->modalHeading('Заполнить форму договора клиента')
                    ->modalDescription('Загрузите DOCX. Система сохранит оригинал и рабочую копию, заменит только реквизиты Поставщика и служебные метки. Юридический текст клиента не переписывается.')
                    ->modalSubmitActionLabel('Подготовить договор')
                    ->fillForm(fn (): array => [
                        'contract_date' => today(),
                        'valid_until' => null,
                        'auto_replace_supplier_column' => true,
                    ])
                    ->schema([
                        FileUpload::make('client_template_path')
                            ->label('Форма клиента DOC или DOCX')
                            ->helperText('Поддерживаются старые DOC и современные DOCX до 15 МБ. DOC будет автоматически преобразован. Если в форме нет меток, система найдёт таблицу с колонками «Поставщик/Исполнитель» и «Покупатель/Заказчик».')
                            ->disk('local')
                            ->directory('commercial-contracts/client-uploads')
                            ->visibility('private')
                            ->acceptedFileTypes([
                                'application/msword',
                                'application/vnd.ms-word',
                                'application/vnd.ms-office',
                                'application/x-tika-msoffice',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            ])
                            ->storeFileNamesIn('client_template_name')
                            ->maxSize(15360)
                            ->required()
                            ->columnSpanFull(),
                        DatePicker::make('contract_date')->label('Дата договора')->required()->native(false),
                        DatePicker::make('valid_until')->label('Действует до')->native(false),
                        Toggle::make('auto_replace_supplier_column')
                            ->label('Автоматически заменить колонку Поставщика')
                            ->helperText('Если меток нет, реквизиты в колонке «Поставщик» или «Исполнитель» будут заменены полностью. Оригинал всегда сохраняется.')
                            ->default(true)
                            ->columnSpanFull(),
                        Textarea::make('supported_markers')
                            ->label('Метки для точного заполнения')
                            ->default('{{SUPPLIER_NAME}}, {{SUPPLIER_UNP}}, {{SUPPLIER_ADDRESS}}, {{SUPPLIER_IBAN}}, {{SUPPLIER_BANK}}, {{SUPPLIER_BIC}}, {{SUPPLIER_PHONE}}, {{SUPPLIER_EMAIL}}, {{SUPPLIER_DIRECTOR}}, {{CONTRACT_NUMBER}}, {{CONTRACT_DATE}}')
                            ->disabled()
                            ->dehydrated(false)
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data): void {
                        try {
                            $contract = app(ClientContractTemplateGenerator::class)->create($this->record, $data);
                            $report = (array) data_get($contract->terms_snapshot, 'replacement_report', []);
                            $mode = ($report['supplier_column_replaced'] ?? false) ? 'заменена колонка Поставщика' : 'заполнены метки';
                            $warning = count((array) ($report['unresolved_tokens'] ?? [])) > 0 ? ' Есть незаполненные неизвестные метки — проверьте DOCX.' : '';

                            Notification::make()
                                ->success()
                                ->title('Форма клиента подготовлена')
                                ->body($contract->number.' · '.$mode.'. Оригинал сохранён.'.$warning)
                                ->persistent()
                                ->send();
                            $this->record->refresh();
                        } catch (Throwable $exception) {
                            Notification::make()->danger()->title('Не удалось заполнить форму клиента')->body($exception->getMessage())->persistent()->send();
                        }
                    }),
                Action::make('contract_pdf')
                    ->label('Открыть последний договор PDF')
                    ->icon('heroicon-o-document-check')
                    ->color('gray')
                    ->visible(fn (): bool => $this->record->contracts()->whereNotNull('generated_pdf_path')->exists())
                    ->url(fn (): string => route('admin.commercial-contracts.pdf', $this->record->contracts()->whereNotNull('generated_pdf_path')->firstOrFail()))
                    ->openUrlInNewTab(),
                Action::make('contract_docx')
                    ->label('Скачать последний договор DOCX')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (): bool => $this->record->contracts()->whereNotNull('generated_docx_path')->exists())
                    ->url(fn (): string => route('admin.commercial-contracts.docx', $this->record->contracts()->whereNotNull('generated_docx_path')->firstOrFail())),
            ])
                ->label('Документы')
                ->icon('heroicon-o-folder-open')
                ->color('gray')
                ->button(),
            Action::make('mail')
                ->label('Подготовить письмо')
                ->icon('heroicon-o-envelope')
                ->color('primary')
                ->action(function (): void {
                    $issues = app(CommercialDocumentReadiness::class)->issues($this->record);
                    if ($issues !== []) {
                        Notification::make()
                            ->warning()
                            ->title('Документ пока не готов к отправке')
                            ->body(implode("\n", array_map(fn (string $issue): string => '• '.$issue, $issues)))
                            ->send();

                        return;
                    }

                    try {
                        $thread = app(CommercialDocumentWorkflow::class)->createMailDraft($this->record, auth()->user());
                        Notification::make()->success()->title('Письмо с PDF готово')->body('Проверьте текст и отправьте письмо клиенту.')->send();
                        $this->redirect(CrmMailThreadResource::getUrl('view', ['record' => $thread]));
                    } catch (Throwable $exception) {
                        Notification::make()->danger()->title('Не удалось подготовить письмо')->body($exception->getMessage())->send();
                    }
                }),
            ActionGroup::make([
                Action::make('publish_online')
                    ->label('Онлайн-ссылка')
                    ->icon('heroicon-o-qr-code')
                    ->color('primary')
                    ->visible(fn (): bool => $this->record->type === 'quote'
                        && ! in_array($this->record->status, ['expired', 'cancelled'], true))
                    ->action(function (): void {
                        try {
                            $portal = app(CommercialDocumentPortal::class);
                            $link = $portal->publish($this->record, auth()->user());
                            Notification::make()
                                ->success()
                                ->title('Онлайн-предложение готово')
                                ->body($portal->url($link))
                                ->persistent()
                                ->send();
                        } catch (Throwable $exception) {
                            Notification::make()->danger()->title('Не удалось создать ссылку')->body($exception->getMessage())->send();
                        }
                    }),
                Action::make('preview_online')
                    ->label('Предпросмотр онлайн')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->visible(fn (): bool => $this->record->portalLinks()
                        ->whereNull('revoked_at')->where('expires_at', '>', now())->exists())
                    ->url(fn (): string => route('admin.commercial-documents.online-preview', $this->record))
                    ->openUrlInNewTab(),
                Action::make('refresh_online')
                    ->label('Обновить онлайн-КП')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn (): bool => $this->record->portalLinks()
                        ->whereNull('revoked_at')->where('expires_at', '>', now())->exists())
                    ->requiresConfirmation()
                    ->modalHeading('Обновить товары в онлайн-КП?')
                    ->modalDescription('Названия, бренд, описание, характеристики и изображения будут повторно взяты из актуального каталога. Ссылка клиента останется прежней.')
                    ->action(function (): void {
                        try {
                            app(CommercialDocumentPortal::class)->refreshSnapshot($this->record);
                            Notification::make()->success()->title('Онлайн-КП обновлено')->send();
                        } catch (Throwable $exception) {
                            Notification::make()->danger()->title('Не удалось обновить онлайн-КП')->body($exception->getMessage())->send();
                        }
                    }),
                Action::make('revoke_online')
                    ->label('Отозвать онлайн-доступ')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->visible(fn (): bool => $this->record->portalLinks()
                        ->whereNull('revoked_at')->where('expires_at', '>', now())->exists())
                    ->requiresConfirmation()
                    ->action(function (): void {
                        $link = $this->record->portalLinks()->whereNull('revoked_at')->latest('id')->firstOrFail();
                        app(CommercialDocumentPortal::class)->revoke($link);
                        Notification::make()->success()->title('Онлайн-доступ отозван')->send();
                    }),
                Action::make('revoke_portal_device')
                    ->label('Отключить устройство')
                    ->icon('heroicon-o-device-phone-mobile')
                    ->color('danger')
                    ->visible(fn (): bool => $this->record->portalLinks()
                        ->whereHas('sessions', fn ($query) => $query->whereNull('revoked_at')->where('expires_at', '>', now()))
                        ->exists())
                    ->schema([
                        Select::make('session_id')
                            ->label('Подтверждённое устройство')
                            ->options(function (): array {
                                $link = $this->record->portalLinks()
                                    ->whereHas('sessions', fn ($query) => $query->whereNull('revoked_at')->where('expires_at', '>', now()))
                                    ->latest('id')
                                    ->first();

                                return $link?->sessions()
                                    ->whereNull('revoked_at')
                                    ->where('expires_at', '>', now())
                                    ->latest('last_seen_at')
                                    ->get()
                                    ->mapWithKeys(fn ($session): array => [
                                        $session->id => ($session->device_label ?: 'Неизвестное устройство')
                                            .' · последнее посещение '.($session->last_seen_at?->format('d.m.Y H:i') ?: 'не было'),
                                    ])->all() ?? [];
                            })
                            ->required(),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Отключить выбранное устройство?')
                    ->modalDescription('На этом устройстве потребуется повторное подтверждение кодом из email.')
                    ->action(function (array $data): void {
                        $link = $this->record->portalLinks()->latest('id')->firstOrFail();
                        $session = $link->sessions()->findOrFail((int) $data['session_id']);
                        app(CommercialDocumentPortal::class)->revokeSession($session, auth()->user());
                        Notification::make()->success()->title('Устройство отключено')->send();
                    }),
            ])
                ->label('Онлайн-КП')
                ->icon('heroicon-o-globe-alt')
                ->color('gray')
                ->button(),
            Action::make('convert')
                ->label('Сделать счёт')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('success')
                ->visible(fn (): bool => $this->record->type === 'quote'
                    && ! in_array($this->record->status, ['rejected', 'expired', 'cancelled'], true))
                ->requiresConfirmation()
                ->modalHeading('Создать счёт из коммерческого предложения?')
                ->modalDescription('Будет создан отдельный счёт с теми же товарами, ценами и данными клиента. Исходное КП сохранится.')
                ->action(function (): void {
                    $invoice = app(CommercialDocumentWorkflow::class)->convertToInvoice($this->record, auth()->user());
                    Notification::make()->success()->title('Счёт создан')->body($invoice->number)->send();
                    $this->redirect(CommercialDocumentResource::getUrl('view', ['record' => $invoice]));
                }),
            Action::make('create_order')
                ->label('Создать заказ')
                ->icon('heroicon-o-shopping-cart')
                ->color('success')
                ->visible(fn (): bool => $this->record->type === 'quote'
                    && $this->record->status === 'accepted'
                    && ! $this->record->order()->exists())
                ->requiresConfirmation()
                ->modalHeading('Создать заказ из согласованного КП?')
                ->modalDescription('Клиент, реквизиты, товары, цены и условия будут перенесены в заказ. Заказ попадёт в обычный процесс обработки и обмена с 1С.')
                ->action(function (): void {
                    try {
                        $order = app(CommercialDocumentWorkflow::class)->convertToOrder($this->record, auth()->user());
                        Notification::make()->success()->title('Заказ создан')->body($order->number)->send();
                        $this->redirect(OrderResource::getUrl('view', ['record' => $order]));
                    } catch (Throwable $exception) {
                        Notification::make()->danger()->title('Не удалось создать заказ')->body($exception->getMessage())->send();
                    }
                }),
            Action::make('open_order')
                ->label('Открыть заказ')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('primary')
                ->visible(fn (): bool => $this->record->order()->exists())
                ->url(fn (): string => OrderResource::getUrl('view', ['record' => $this->record->order()->firstOrFail()])),
            Action::make('accept')
                ->label('Согласовано')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->record->type === 'quote' && $this->record->status === 'sent')
                ->requiresConfirmation()
                ->action(function (): void {
                    app(CommercialDocumentWorkflow::class)->accept($this->record);
                    $this->record->refresh();
                    Notification::make()->success()->title('КП согласовано клиентом')->send();
                }),
            Action::make('reject')
                ->label('Зафиксировать отказ')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => $this->record->type === 'quote' && in_array($this->record->status, ['sent', 'accepted'], true))
                ->schema([
                    Select::make('reason')->label('Причина')->options(CommercialDocument::OUTCOME_REASONS)->required(),
                    Textarea::make('note')->label('Комментарий менеджера')->rows(3),
                ])
                ->action(function (array $data): void {
                    app(CommercialDocumentWorkflow::class)->reject($this->record, $data['reason'], $data['note'] ?? null);
                    $this->record->refresh();
                    Notification::make()->success()->title('Отказ зафиксирован')->send();
                }),
            Action::make('request_revision')
                ->label('Нужна корректировка')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->visible(fn (): bool => $this->record->type === 'quote'
                    && in_array($this->record->status, ['sent', 'accepted'], true))
                ->schema([
                    Textarea::make('note')
                        ->label('Что просит изменить клиент')
                        ->placeholder('Например: заменить модель, пересчитать количество и срок поставки')
                        ->required()
                        ->rows(4),
                ])
                ->action(function (array $data): void {
                    app(CommercialDocumentWorkflow::class)->requestRevision($this->record, $data['note']);
                    $this->record->refresh();
                    Notification::make()
                        ->warning()
                        ->title('Корректировка зафиксирована')
                        ->body('Создайте новую редакцию — исходное КП останется в истории.')
                        ->send();
                }),
            Action::make('expire')
                ->label('Срок истёк')
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->visible(fn (): bool => $this->record->type === 'quote' && $this->record->status === 'sent')
                ->requiresConfirmation()
                ->action(function (): void {
                    app(CommercialDocumentWorkflow::class)->expire($this->record);
                    $this->record->refresh();
                    Notification::make()->success()->title('КП закрыто как просроченное')->send();
                }),
            Action::make('new_revision')
                ->label('Новая редакция')
                ->icon('heroicon-o-document-duplicate')
                ->color('warning')
                ->visible(fn (): bool => $this->record->type === 'quote' && $this->record->status !== 'draft' && ! $this->record->newerRevision())
                ->requiresConfirmation()
                ->modalDescription('Будет создан новый черновик с теми же клиентом, товарами и ценами. Отправленная редакция останется неизменной.')
                ->action(function (): void {
                    $revision = app(CommercialDocumentWorkflow::class)->createRevision($this->record, auth()->user());
                    Notification::make()->success()->title('Новая редакция создана')->body($revision->number)->send();
                    $this->redirect(CommercialDocumentResource::getUrl('edit', ['record' => $revision]));
                }),
            Action::make('mark_paid')
                ->label('Оплачено')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn (): bool => $this->record->type === 'invoice' && $this->record->status !== 'paid')
                ->requiresConfirmation()
                ->action(function (): void {
                    app(CommercialDocumentWorkflow::class)->markPaid($this->record);
                    $this->record->refresh();
                    Notification::make()->success()->title('Счёт отмечен оплаченным')->send();
                }),
        ];
    }
}

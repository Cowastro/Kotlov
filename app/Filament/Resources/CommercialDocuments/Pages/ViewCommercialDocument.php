<?php

namespace App\Filament\Resources\CommercialDocuments\Pages;

use App\Filament\Resources\CommercialDocuments\CommercialDocumentResource;
use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\CommercialDocument;
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

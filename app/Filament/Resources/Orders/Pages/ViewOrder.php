<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Actions\CreateCommercialDocumentFromOrderAction;
use App\Filament\Resources\Orders\Actions\FindOrderItemAnalogAction;
use App\Models\Order;
use App\Models\OrderAccountingRequest;
use App\Models\OrderItem;
use App\Models\Supplier;
use App\Models\SupplierOrderRequest;
use App\Services\CrmMail\CrmMailSender;
use App\Services\CrmMail\SupplierRequestMailDraftService;
use App\Services\Orders\OrderAvailabilityConfirmation;
use App\Services\Orders\OrderItemFulfillmentManager;
use App\Services\Orders\OrderPortal;
use App\Services\Orders\OrderSettlementManager;
use App\Services\Orders\OrderWorkflow;
use App\Services\Orders\SupplierOrderRequestBuilder;
use App\Services\Orders\SupplierOrderRequestWorkflow;
use App\Services\Orders\SupplierReservationMailService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected array $extraBodyAttributes = ['class' => 'order-view-page'];

    protected function resolveRecord(int|string $key): Order
    {
        return static::getResource()::getEloquentQuery()->with([
            'items.product.category',
            'items.product.supplierProducts.supplier',
            'items.product.integrationProducts.source.supplier',
            'items.integrationProduct.source.supplier',
            'items.fulfillmentConfirmedBy',
            'items.currentAvailabilityCheckedBy',
            'items.fulfillmentSupplier.crmCounterparty.contactPoints',
            'fulfillmentHistory.item',
            'fulfillmentHistory.user',
            'fulfillmentHistory.previousSupplier',
            'latestSettlement.lines.orderItem',
            'latestSettlement.lines.supplier',
            'latestSettlement.confirmer',
            'supplierOrderRequests.items',
            'supplierOrderRequests.mailDraft',
            'supplierOrderRequests.creator',
            'supplierOrderRequests.sentBy',
            'supplierOrderRequests.statusUpdatedBy',
            'accountingRequests.mailThread',
            'accountingRequests.requestedBy',
            'accountingRequests.completedBy',
            'statusHistory.user',
            'user',
            'manager',
            'correctedBy',
            'procurementSentBy',
            'dealByInbox',
            'integrationDeliveries.source',
        ])->findOrFail($key);
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                CreateCommercialDocumentFromOrderAction::make(),
                Action::make('printProcurement')
                ->label('Печать для закупок')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('admin.orders.procurement', ['order' => $this->record]))
                ->openUrlInNewTab(),
                Action::make('emailProcurement')
                ->label('Отправить в закупки')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->modalHeading('Отправить закупочный лист')
                ->modalDescription('Письмо уйдёт с order@kotlov.by. В защищённой ссылке всегда отображаются текущие поставщики, входные цены, контакты и статусы исполнения.')
                ->fillForm(fn (): array => [
                    'recipient' => $this->record->procurement_recipient ?: config('services.procurement.email'),
                    'note' => null,
                ])
                ->schema([
                    TextInput::make('recipient')
                        ->label('Email отдела закупок')
                        ->email()
                        ->required(),
                    Textarea::make('note')
                        ->label('Комментарий')
                        ->rows(3)
                        ->maxLength(2000),
                    Select::make('availability_status')
                        ->label('Решение по наличию всех позиций')
                        ->options([
                            'available' => 'Наличие подтверждено',
                            'reserved' => 'Всё зарезервировано',
                        ])
                        ->required(),
                    Checkbox::make('availability_confirmation')
                        ->label('Подтверждаю: поставщики выбраны, наличие или резерв проверены по всем позициям')
                        ->accepted()
                        ->required(),
                ])
                ->modalSubmitActionLabel('Отправить')
                ->action(function (array $data): void {
                    $user = auth()->user();
                    $this->confirmAvailabilityBeforeShare($data['availability_status'], $data['note'] ?? null);
                    $portal = app(OrderPortal::class);
                    $portalLink = $portal->publish($this->record, $user);
                    $thread = app(SupplierRequestMailDraftService::class)->createOrderProcurementDraft(
                        $this->record,
                        $user,
                        $data['recipient'],
                        'Закупка по заказу '.$this->record->number,
                        $this->procurementEmailBody($portal->url($portalLink), $data['note'] ?? null),
                    );
                    app(CrmMailSender::class)->sendOrderProcurementDraft($thread, $user);

                    $this->record->forceFill([
                        'procurement_sent_at' => now(),
                        'procurement_sent_by' => $user->id,
                        'procurement_recipient' => mb_strtolower(trim($data['recipient'])),
                    ])->save();
                    $this->reloadRecord();

                    Notification::make()
                        ->success()
                        ->title('Закупочный лист отправлен')
                        ->body('Получатель: '.$data['recipient'])
                        ->send();
                }),
                Action::make('sendAccountingPaymentRequest')
                    ->label('Передать счёт в бухгалтерию')
                    ->icon('heroicon-o-banknotes')
                    ->color('warning')
                    ->modalHeading('Поручение бухгалтерии на оплату')
                    ->modalDescription('Письмо и документ будут отправлены с order@kotlov.by и останутся связанными с заказом.')
                    ->fillForm(fn (): array => [
                        'recipient' => config('services.accounting.email', 'buh-sbg@yandex.ru'),
                        'amount' => $this->accountingSuggestedAmount(),
                        'due_date' => now()->addWeekdays(2)->toDateString(),
                        'comment' => null,
                    ])
                    ->schema([
                        TextInput::make('recipient')
                            ->label('Email бухгалтерии')
                            ->email()
                            ->required(),
                        TextInput::make('amount')
                            ->label('Сумма к оплате, BYN')
                            ->numeric()
                            ->minValue(0.01),
                        DatePicker::make('due_date')
                            ->label('Оплатить до')
                            ->native(false),
                        FileUpload::make('attachments')
                            ->label('Счёт или другой документ на оплату')
                            ->disk('local')
                            ->directory('crm-mail-attachments/accounting')
                            ->visibility('private')
                            ->multiple()
                            ->required()
                            ->maxFiles(5)
                            ->maxSize(15360)
                            ->acceptedFileTypes([
                                'application/pdf',
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/msword',
                            ])
                            ->storeFileNamesIn('attachment_file_names')
                            ->helperText('PDF, фото или Excel. До 5 файлов, каждый не более 15 МБ.'),
                        Textarea::make('comment')
                            ->label('Комментарий бухгалтеру')
                            ->placeholder('Например: оплатить после подтверждения резерва, назначение платежа…')
                            ->rows(3)
                            ->maxLength(2000),
                    ])
                    ->modalSubmitActionLabel('Отправить поручение')
                    ->action(function (array $data): void {
                        $user = auth()->user();
                        $recipient = mb_strtolower(trim((string) $data['recipient']));

                        try {
                            [$thread, $request] = DB::transaction(function () use ($data, $user, $recipient): array {
                                $thread = app(SupplierRequestMailDraftService::class)->createOrderAccountingDraft(
                                    $this->record,
                                    $user,
                                    $recipient,
                                    'Оплата поставщику — заказ '.$this->record->number,
                                    $this->accountingEmailBody($data),
                                    $data['attachments'] ?? [],
                                    $data['attachment_file_names'] ?? [],
                                );
                                $request = $this->record->accountingRequests()->create([
                                    'mail_thread_id' => $thread->id,
                                    'requested_by_user_id' => $user->id,
                                    'recipient_email' => $recipient,
                                    'amount' => filled($data['amount'] ?? null) ? (float) $data['amount'] : null,
                                    'due_date' => $data['due_date'] ?? null,
                                    'status' => 'draft',
                                    'comment' => $data['comment'] ?? null,
                                ]);

                                return [$thread, $request];
                            });
                        } catch (Throwable $exception) {
                            report($exception);
                            Notification::make()
                                ->danger()
                                ->title('Не удалось сохранить поручение')
                                ->body($exception->getMessage())
                                ->send();

                            return;
                        }

                        try {
                            app(CrmMailSender::class)->sendOrderAccountingDraft($thread, $user);
                            $request->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null]);
                            $this->record->statusHistory()->create([
                                'user_id' => $user->id,
                                'status_from' => $this->record->status,
                                'status_to' => $this->record->status,
                                'comment' => 'Счёт поставщика передан в бухгалтерию · поручение №'.$request->id
                                    .' · '.$recipient
                                    .(filled($data['amount'] ?? null) ? ' · '.number_format((float) $data['amount'], 2, ',', ' ').' BYN' : ''),
                            ]);
                            Notification::make()->success()->title('Поручение отправлено в бухгалтерию')->body($recipient)->send();
                        } catch (Throwable $exception) {
                            $request->update(['last_error' => $exception->getMessage()]);
                            $this->record->statusHistory()->create([
                                'user_id' => $user->id,
                                'status_from' => $this->record->status,
                                'status_to' => $this->record->status,
                                'comment' => 'Счёт поставщика прикреплён к заказу, но письмо в бухгалтерию не отправлено · поручение №'.$request->id,
                            ]);
                            Notification::make()->warning()->title('Поручение сохранено, но письмо не отправлено')->body($exception->getMessage())->send();
                        }

                        $this->reloadRecord();
                    }),
                Action::make('completeAccountingPaymentRequest')
                    ->label('Отметить оплату поставщику')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (): bool => $this->record->accountingRequests->where('status', 'sent')->isNotEmpty())
                    ->schema([
                        Select::make('accounting_request_id')
                            ->label('Поручение')
                            ->options(fn (): array => $this->record->accountingRequests
                                ->where('status', 'sent')
                                ->mapWithKeys(fn (OrderAccountingRequest $request): array => [
                                    $request->id => '№'.$request->id.' · '.($request->amount !== null ? number_format((float) $request->amount, 2, '.', ' ').' BYN' : 'сумма не указана').' · '.$request->recipient_email,
                                ])->all())
                            ->required(),
                        Textarea::make('comment')
                            ->label('Комментарий об оплате')
                            ->rows(2)
                            ->maxLength(1000),
                    ])
                    ->modalSubmitActionLabel('Подтвердить оплату')
                    ->action(function (array $data): void {
                        $request = $this->record->accountingRequests()->where('status', 'sent')->findOrFail($data['accounting_request_id']);
                        $request->update([
                            'status' => 'paid',
                            'completed_by_user_id' => auth()->id(),
                            'completed_at' => now(),
                            'comment' => collect([$request->comment, $data['comment'] ?? null])->filter()->implode("\n"),
                        ]);
                        $this->reloadRecord();
                        Notification::make()->success()->title('Оплата поставщику отмечена')->send();
                    }),
            ])
                ->label('Документы')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->button(),
            ActionGroup::make([
                Action::make('publishOrderOnline')
                    ->label('Создать ссылку и QR')
                    ->icon('heroicon-o-qr-code')
                    ->color('primary')
                    ->visible(fn (): bool => ! $this->orderPortalIsComplete())
                    ->modalHeading('Создать ссылку на подтверждённый заказ')
                    ->modalDescription('Перед публикацией менеджер обязан подтвердить поставщиков и наличие всех позиций.')
                    ->schema([
                        Select::make('availability_status')
                            ->label('Решение по наличию всех позиций')
                            ->options([
                                'available' => 'Наличие подтверждено',
                                'reserved' => 'Всё зарезервировано',
                            ])
                            ->required(),
                        Checkbox::make('availability_confirmation')
                            ->label('Подтверждаю: поставщики выбраны, наличие или резерв проверены по всем позициям')
                            ->accepted()
                            ->required(),
                    ])
                    ->modalSubmitActionLabel('Подтвердить и создать ссылку')
                    ->action(function (array $data): void {
                        try {
                            $this->confirmAvailabilityBeforeShare($data['availability_status']);
                            $portal = app(OrderPortal::class);
                            $link = $portal->publish($this->record, auth()->user());
                            Notification::make()
                                ->success()
                                ->title('Онлайн-заказ и QR готовы')
                                ->body($portal->url($link))
                                ->persistent()
                                ->send();
                        } catch (Throwable $exception) {
                            Notification::make()->danger()->title('Не удалось создать доступ')->body($exception->getMessage())->send();
                        }
                    }),
                Action::make('showOrderQr')
                    ->label('Показать QR и ссылку')
                    ->icon('heroicon-o-qr-code')
                    ->color('gray')
                    ->visible(fn (): bool => ! $this->orderPortalIsComplete()
                        && $this->record->portalLinks()->whereNull('revoked_at')->where('expires_at', '>', now())->exists())
                    ->modalHeading(fn (): string => 'Онлайн-заказ '.$this->record->number)
                    ->modalDescription('QR и ссылка ведут на одну защищённую страницу всего заказа, как в онлайн-КП.')
                    ->modalWidth(Width::Large)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Закрыть')
                    ->modalContent(function () {
                        $link = $this->record->portalLinks()
                            ->whereNull('revoked_at')->where('expires_at', '>', now())->latest('id')->firstOrFail();
                        $portal = app(OrderPortal::class);

                        return view('filament.resources.orders.online-access', [
                            'order' => $this->record,
                            'url' => $portal->url($link),
                            'qr' => $portal->qrDataUri($link),
                            'expiresAt' => $link->expires_at,
                        ]);
                    }),
                Action::make('previewOrderOnline')
                    ->label('Предпросмотр заказа')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->visible(fn (): bool => ! $this->orderPortalIsComplete()
                        && $this->record->portalLinks()->whereNull('revoked_at')->where('expires_at', '>', now())->exists())
                    ->url(fn (): string => route('admin.orders.online-preview', $this->record))
                    ->openUrlInNewTab(),
                Action::make('printOrderOnline')
                    ->label('Версия для печати')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->visible(fn (): bool => ! $this->orderPortalIsComplete()
                        && $this->record->portalLinks()->whereNull('revoked_at')->where('expires_at', '>', now())->exists())
                    ->url(function (): string {
                        $link = $this->record->portalLinks()
                            ->whereNull('revoked_at')->where('expires_at', '>', now())->latest('id')->firstOrFail();

                        return app(OrderPortal::class)->printUrl($link);
                    })
                    ->openUrlInNewTab(),
                Action::make('revokeOrderOnline')
                    ->label('Отозвать онлайн-доступ')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->visible(fn (): bool => $this->record->portalLinks()
                        ->whereNull('revoked_at')->where('expires_at', '>', now())->exists())
                    ->requiresConfirmation()
                    ->action(function (): void {
                        $link = $this->record->portalLinks()->whereNull('revoked_at')->latest('id')->firstOrFail();
                        app(OrderPortal::class)->revoke($link);
                        Notification::make()->success()->title('Онлайн-доступ к заказу отозван')->send();
                    }),
            ])
                ->label('Онлайн-заказ')
                ->icon('heroicon-o-globe-alt')
                ->color('gray')
                ->button(),
            Action::make('advanceWorkflow')
                ->label(fn (): string => app(OrderWorkflow::class)->step($this->record)['label'] ?? 'Следующий этап')
                ->icon(fn (): string => app(OrderWorkflow::class)->step($this->record)['icon'] ?? 'heroicon-o-arrow-right')
                ->color(fn (): string => app(OrderWorkflow::class)->step($this->record)['color'] ?? 'gray')
                ->extraAttributes(['class' => 'order-view-action order-view-action--primary'])
                ->visible(fn (): bool => app(OrderWorkflow::class)->step($this->record) !== null)
                ->disabled(fn (): bool => app(OrderWorkflow::class)->blocker($this->record) !== null)
                ->tooltip(fn (): ?string => app(OrderWorkflow::class)->blocker($this->record))
                ->requiresConfirmation()
                ->modalHeading(fn (): string => app(OrderWorkflow::class)->step($this->record)['heading'] ?? 'Перейти к следующему этапу?')
                ->modalDescription(fn (): string => app(OrderWorkflow::class)->step($this->record)['description'] ?? '')
                ->modalSubmitActionLabel(fn (): string => app(OrderWorkflow::class)->step($this->record)['submit'] ?? 'Продолжить')
                ->action(function (): void {
                    try {
                        $result = app(OrderWorkflow::class)->advance($this->record, auth()->user());
                        $this->reloadRecord();

                        Notification::make()
                            ->success()
                            ->title($result['title'])
                            ->body($result['body'])
                            ->send();
                    } catch (\Throwable $exception) {
                        $this->reloadRecord();

                        Notification::make()
                            ->danger()
                            ->title('Не удалось перейти к следующему этапу')
                            ->body($exception->getMessage())
                            ->send();
                    }
                }),
            FindOrderItemAnalogAction::make()
                ->extraAttributes(['class' => 'order-view-action order-view-action--primary']),
            ActionGroup::make([
                Action::make('confirmFulfillment')
                ->label('Подтвердить исполнение')
                ->icon('heroicon-o-check-badge')
                ->color('warning')
                ->extraAttributes(['class' => 'order-view-action order-view-action--primary'])
                ->disabled(fn (): bool => $this->record->items->isNotEmpty()
                    && $this->record->items->every(fn (OrderItem $item): bool => filled($item->fulfillment_confirmed_at)))
                ->tooltip(fn (): ?string => $this->record->items->isNotEmpty()
                    && $this->record->items->every(fn (OrderItem $item): bool => filled($item->fulfillment_confirmed_at))
                        ? 'Исполнение всех позиций уже подтверждено.'
                        : null)
                ->modalHeading('Подтверждение исполнителя позиции')
                ->modalDescription('Рекомендация системы останется в снимке заказа. Здесь фиксируется решение менеджера и его история.')
                ->schema([
                    Select::make('order_item_id')
                        ->label('Позиция заказа')
                        ->options(fn (): array => $this->record->items
                            ->filter(fn (OrderItem $item): bool => blank($item->fulfillment_confirmed_at))
                            ->mapWithKeys(fn (OrderItem $item): array => [
                                $item->id => $item->product_name.' · '.($item->product_sku ?: 'без артикула'),
                            ])->all())
                        ->searchable()
                        ->required(),
                    Select::make('fulfillment_route')
                        ->label('Как исполняем')
                        ->options(OrderItem::FULFILLMENT_ROUTES)
                        ->required(),
                    Select::make('supplier_id')
                        ->label('Поставщик')
                        ->options(fn (): array => Supplier::query()
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->required()
                        ->helperText('Обязателен для любой позиции, включая товар с нашего склада.'),
                    Select::make('availability_status')
                        ->label('Решение по наличию')
                        ->options(OrderItem::FULFILLMENT_AVAILABILITY_STATUSES)
                        ->required(),
                    TextInput::make('purchase_price')
                        ->label('Подтверждённая входная цена за единицу, BYN')
                        ->numeric()
                        ->minValue(0)
                        ->helperText('Если выбран рекомендованный поставщик, пустое поле наследует цену снимка заказа. Для другого поставщика цену нужно указать вручную.'),
                    Textarea::make('note')
                        ->label('Комментарий менеджера')
                        ->rows(3)
                        ->maxLength(2000),
                ])
                ->action(function (array $data): void {
                    $item = $this->record->items()->findOrFail($data['order_item_id']);
                    $supplier = filled($data['supplier_id'] ?? null)
                        ? Supplier::query()->findOrFail($data['supplier_id'])
                        : null;

                    app(OrderItemFulfillmentManager::class)->confirm(
                        $item,
                        $data['fulfillment_route'],
                        $supplier,
                        auth()->user(),
                        $data['note'] ?? null,
                        filled($data['purchase_price'] ?? null) ? (float) $data['purchase_price'] : null,
                        $data['availability_status'],
                    );

                    if ($this->record->status === 'new') {
                        $this->record->transitionTo('checking', 'Заказ принят автоматически при начале проверки исполнения.', $this->managerAttributes());
                    }

                    $this->reloadRecord();

                    Notification::make()
                        ->success()
                        ->title('Исполнитель позиции подтверждён')
                        ->body('Решение сохранено в истории заказа.')
                        ->send();
                }),
            Action::make('buildSupplierRequests')
                ->label('Сформировать заявки')
                ->icon('heroicon-o-document-duplicate')
                ->color('primary')
                ->extraAttributes(['class' => 'order-view-action order-view-action--primary'])
                ->visible(fn (): bool => $this->record->items->contains(
                    fn (OrderItem $item): bool => in_array($item->fulfillment_route, ['supplier_purchase', 'direct_supplier'], true)
                        && $item->fulfillment_supplier_id !== null,
                ))
                ->requiresConfirmation()
                ->modalHeading('Сформировать черновики заявок поставщикам?')
                ->modalDescription('Позиции будут разделены по подтверждённому поставщику и способу исполнения. Ничего не отправляется поставщикам автоматически.')
                ->modalSubmitActionLabel('Сформировать черновики')
                ->action(function (): void {
                    $requests = app(SupplierOrderRequestBuilder::class)
                        ->buildDrafts($this->record, auth()->user());

                    $this->record->refresh();

                    Notification::make()
                        ->success()
                        ->title('Черновики заявок сформированы')
                        ->body('Создано или обновлено заявок: '.$requests->count().'. Письма доступны в «Почта и лиды» → «Черновики». Автоматической отправки не было.')
                        ->send();
                }),
            Action::make('emailSupplierReserve')
                ->label('Запросить резерв')
                ->icon('heroicon-o-envelope')
                ->color('success')
                ->extraAttributes(['class' => 'order-view-action order-view-action--primary'])
                ->visible(fn (): bool => $this->supplierRequestChoices() !== [])
                ->modalHeading('Отправить поставщику запрос резерва')
                ->modalDescription('Письмо уйдёт с order@kotlov.by. В нём будут позиции этого поставщика, входные цены и остатки из снимка заказа. Ответ сохранится в связанной переписке.')
                ->fillForm(function (): array {
                    $key = array_key_first($this->supplierRequestChoices());

                    return [
                        'supplier_route' => $key,
                        'recipient' => $key ? $this->supplierEmailForRequestKey($key) : null,
                        'note' => 'Просим подтвердить резерв и сообщить возможную дату отгрузки.',
                    ];
                })
                ->schema([
                    Select::make('supplier_route')
                        ->label('Поставщик и маршрут')
                        ->options(fn (): array => $this->supplierRequestChoices())
                        ->live()
                        ->afterStateUpdated(fn (?string $state, Set $set) => $set(
                            'recipient',
                            $state ? $this->supplierEmailForRequestKey($state) : null,
                        ))
                        ->required(),
                    TextInput::make('recipient')
                        ->label('Email поставщика')
                        ->email()
                        ->required(),
                    Textarea::make('note')
                        ->label('Комментарий поставщику')
                        ->rows(3)
                        ->maxLength(2000),
                ])
                ->modalSubmitActionLabel('Отправить запрос')
                ->action(function (array $data): void {
                    [$supplierId, $route] = explode('|', (string) $data['supplier_route'], 2);
                    $requests = app(SupplierOrderRequestBuilder::class)
                        ->buildDrafts($this->record, auth()->user(), (int) $supplierId, $route);
                    $request = $requests->first(
                        fn (SupplierOrderRequest $candidate): bool => $candidate->supplier_id === (int) $supplierId
                            && $candidate->route === $route
                    );

                    if (! $request) {
                        throw new \RuntimeException('Не удалось сформировать заявку выбранному поставщику.');
                    }

                    $thread = app(SupplierReservationMailService::class)->sendOrderRequest(
                        $request,
                        auth()->user(),
                        $data['recipient'],
                        $data['note'] ?? null,
                    );

                    $this->reloadRecord();

                    Notification::make()
                        ->success()
                        ->title('Запрос резерва отправлен')
                        ->body('Письмо отправлено с order@kotlov.by. Переписка #'.$thread->id.' привязана к заявке и заказу.')
                        ->send();
                }),
            Action::make('confirmSettlement')
                ->label('Зафиксировать взаиморасчёт')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->extraAttributes(['class' => 'order-view-action order-view-action--primary'])
                ->modalHeading('Подтверждение экономики и взаиморасчётов')
                ->modalDescription('Будет создана неизменяемая версия расчёта. Если маршрут, поставщик, цена или комиссия изменятся, текущая версия останется в истории и потребуется новая.')
                ->schema([
                    TextInput::make('delivery_cost')
                        ->label('Фактический расход на доставку, BYN')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->required()
                        ->helperText('Введите 0 явно, если расхода нет.'),
                    TextInput::make('payment_fee')
                        ->label('Эквайринг / комиссия оплаты, BYN')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->required()
                        ->helperText('Введите 0 явно, если расхода нет.'),
                    TextInput::make('refund_total')
                        ->label('Возвраты и компенсации, BYN')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->required()
                        ->helperText('Введите 0 явно, если возвратов нет.'),
                    Textarea::make('note')
                        ->label('Основание / комментарий')
                        ->rows(3)
                        ->maxLength(2000),
                ])
                ->requiresConfirmation()
                ->modalSubmitActionLabel('Зафиксировать новую версию')
                ->action(function (array $data): void {
                    $settlement = app(OrderSettlementManager::class)->confirm(
                        $this->record,
                        (float) $data['delivery_cost'],
                        (float) $data['payment_fee'],
                        (float) $data['refund_total'],
                        auth()->user(),
                        $data['note'] ?? null,
                    );

                    $this->record->refresh()->load([
                        'items.fulfillmentSupplier',
                        'latestSettlement.lines.orderItem',
                        'latestSettlement.lines.supplier',
                        'latestSettlement.confirmer',
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Взаиморасчёт зафиксирован')
                        ->body('Версия '.$settlement->version.' · прибыль '.number_format((float) $settlement->net_profit, 2, '.', ' ').' BYN.')
                        ->send();
                }),
            Action::make('recordSupplierEmailResponse')
                ->label('Ответ поставщика')
                ->icon('heroicon-o-inbox-arrow-down')
                ->color('info')
                ->extraAttributes(['class' => 'order-view-action order-view-action--primary'])
                ->visible(fn (): bool => $this->record->supplierOrderRequests
                    ->contains(fn (SupplierOrderRequest $request): bool => $request->status === 'sent'
                        && $request->transfer_mode === 'email'))
                ->modalHeading('Зафиксировать ответ поставщика')
                ->modalDescription('Письмо остаётся в связанной переписке. Здесь фиксируется итог: резерв подтверждён или поставщик отказал.')
                ->schema([
                    Select::make('supplier_order_request_id')
                        ->label('Заявка')
                        ->options(fn (): array => $this->record->supplierOrderRequests
                            ->filter(fn (SupplierOrderRequest $request): bool => $request->status === 'sent'
                                && $request->transfer_mode === 'email')
                            ->mapWithKeys(fn (SupplierOrderRequest $request): array => [
                                $request->id => $request->number.' · '.$request->supplier_name,
                            ])->all())
                        ->required(),
                    Select::make('status')
                        ->label('Результат')
                        ->options([
                            'acknowledged' => 'Резерв подтверждён',
                            'rejected' => 'Поставщик отказал',
                        ])
                        ->required(),
                    Textarea::make('note')
                        ->label('Что подтвердил поставщик')
                        ->placeholder('Например: 2 шт. в резерве до 22.10, отгрузка после оплаты.')
                        ->rows(3)
                        ->required()
                        ->maxLength(2000),
                ])
                ->modalSubmitActionLabel('Сохранить ответ')
                ->action(function (array $data): void {
                    $request = $this->record->supplierOrderRequests()
                        ->where('status', 'sent')
                        ->where('transfer_mode', 'email')
                        ->findOrFail($data['supplier_order_request_id']);

                    app(SupplierOrderRequestWorkflow::class)->recordEmailResponse(
                        $request,
                        $data['status'],
                        auth()->user(),
                        $data['note'],
                    );
                    $this->reloadRecord();

                    Notification::make()
                        ->success()
                        ->title($data['status'] === 'acknowledged' ? 'Резерв подтверждён' : 'Отказ поставщика сохранён')
                        ->send();
                }),
            Action::make('publishSupplierRequest')
                ->label('Передать поставщику')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->extraAttributes(['class' => 'order-view-action order-view-action--primary'])
                ->visible(fn (): bool => $this->record->supplierOrderRequests->contains('status', 'draft'))
                ->modalHeading('Передать заявку в кабинет поставщика?')
                ->modalDescription('После подтверждения заявка станет видна только пользователям выбранного поставщика. Внешнее письмо или сообщение автоматически не отправляется.')
                ->schema([
                    Select::make('supplier_order_request_id')
                        ->label('Черновик заявки')
                        ->options(fn (): array => $this->record->supplierOrderRequests
                            ->where('status', 'draft')
                            ->mapWithKeys(fn (SupplierOrderRequest $request): array => [
                                $request->id => $request->number.' · '.$request->supplier_name.' · '.$request->item_count.' поз.',
                            ])->all())
                        ->required(),
                    Textarea::make('note')
                        ->label('Комментарий поставщику')
                        ->rows(3)
                        ->maxLength(2000),
                ])
                ->modalSubmitActionLabel('Подтвердить передачу')
                ->action(function (array $data): void {
                    $request = $this->record->supplierOrderRequests()
                        ->where('status', 'draft')
                        ->findOrFail($data['supplier_order_request_id']);

                    app(SupplierOrderRequestWorkflow::class)->publish(
                        $request,
                        auth()->user(),
                        $data['note'] ?? null,
                    );

                    $this->record->refresh()->load([
                        'supplierOrderRequests.items',
                        'supplierOrderRequests.creator',
                        'supplierOrderRequests.sentBy',
                        'supplierOrderRequests.statusUpdatedBy',
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Заявка передана поставщику')
                        ->body('Она опубликована в изолированном кабинете поставщика. Автоматического письма не отправлялось.')
                        ->send();
                }),
            ])
                ->label('Исполнение и закупки')
                ->icon('heroicon-o-truck')
                ->color('warning')
                ->button(),
            ActionGroup::make([
            Action::make('addManagerComment')
                ->label('Добавить комментарий')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('info')
                ->schema([
                    Textarea::make('comment')
                        ->label('Комментарий сотрудника')
                        ->rows(3)
                        ->minLength(2)
                        ->maxLength(2000)
                        ->required(),
                ])
                ->modalHeading('Комментарий к заказу')
                ->modalSubmitActionLabel('Добавить')
                ->action(function (array $data): void {
                    $user = auth()->user();
                    $timezone = config('app.display_timezone', 'Europe/Minsk');
                    $note = '['.now()->timezone($timezone)->format('d.m.Y H:i').'] '
                        .($user?->name ?: 'Сотрудник').' — Комментарий менеджера: '
                        .trim((string) $data['comment']);
                    $comment = filled($this->record->admin_comment)
                        ? rtrim((string) $this->record->admin_comment)."\n\n".$note
                        : $note;

                    $this->record->forceFill(['admin_comment' => $comment])->save();
                    $this->reloadRecord();

                    Notification::make()->success()->title('Комментарий добавлен')->send();
                }),
            Action::make('closeAsIrrelevant')
                ->label('Отказ / неактуален')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->extraAttributes(['class' => 'order-view-action order-view-action--danger'])
                ->visible(fn (): bool => $this->record->canBeClosedAsIrrelevant())
                ->modalHeading('Закрыть заказ без удаления?')
                ->modalDescription('Заказ получит статус «Отменён» и попадёт в архив. Причина, автор и время сохранятся в истории.')
                ->schema([
                    Select::make('reason_type')
                        ->label('Основание')
                        ->options(Order::CLOSURE_REASONS)
                        ->native(false)
                        ->required(),
                    Textarea::make('reason')
                        ->label('Конкретная причина')
                        ->placeholder('Например: клиент купил товар в другом месте.')
                        ->rows(3)
                        ->minLength(3)
                        ->maxLength(1000)
                        ->required(),
                ])
                ->modalSubmitActionLabel('Закрыть заказ')
                ->action(function (array $data): void {
                    $this->record->markIrrelevant($data['reason'], $data['reason_type']);
                    $this->reloadRecord();

                    Notification::make()
                        ->success()
                        ->title('Заказ закрыт и перемещён в архив')
                        ->send();
                }),
            Action::make('closeAfterDeliveryRefusal')
                ->label('Отказ при доставке')
                ->icon('heroicon-o-truck')
                ->color('danger')
                ->extraAttributes(['class' => 'order-view-action order-view-action--danger'])
                ->visible(fn (): bool => $this->record->canBeClosedAfterDeliveryRefusal())
                ->modalHeading('Клиент отказался принять заказ?')
                ->modalDescription('Заказ получит статус «Отменён» и попадёт в архив. Причина, сотрудник и время сохранятся в истории.')
                ->schema([
                    Textarea::make('reason')
                        ->label('Причина отказа при доставке')
                        ->placeholder('Например: клиент отказался принимать товар при получении.')
                        ->rows(3)
                        ->minLength(3)
                        ->maxLength(1000)
                        ->required(),
                ])
                ->requiresConfirmation()
                ->modalSubmitActionLabel('Зафиксировать отказ')
                ->action(function (array $data): void {
                    $this->record->markDeliveryRefused($data['reason']);
                    $this->reloadRecord();

                    Notification::make()
                        ->success()
                        ->title('Отказ при доставке сохранён')
                        ->body('Заказ отменён и перемещён в архив.')
                        ->send();
                }),
            EditAction::make('correctOrder')
                ->label('Корректировать заказ')
                ->icon('heroicon-o-pencil-square')
                ->visible(fn (): bool => in_array($this->record->status, ['new', 'checking'], true))
                ->extraAttributes(['class' => 'order-view-action order-view-action--edit']),
            EditAction::make()
                ->label('Редактировать заказ')
                ->icon('heroicon-o-pencil-square')
                ->visible(fn (): bool => ! in_array($this->record->status, ['new', 'checking'], true))
                ->extraAttributes(['class' => 'order-view-action order-view-action--edit']),
            ])
                ->label('Управление')
                ->icon('heroicon-o-ellipsis-horizontal')
                ->color('gray')
                ->button(),
        ];
    }

    /** @return array<string, mixed> */
    private function managerAttributes(): array
    {
        $user = auth()->user();

        return [
            'manager_id' => $this->record->manager_id ?: $user?->id,
            'assigned_to' => $this->record->assigned_to ?: '@'.($user?->telegram_username ?: $user?->name),
        ];
    }

    private function reloadRecord(): void
    {
        $this->record->refresh()->load([
            'items.fulfillmentConfirmedBy',
            'items.fulfillmentSupplier',
            'statusHistory.user',
            'manager',
            'correctedBy',
            'procurementSentBy',
            'supplierOrderRequests.items',
            'supplierOrderRequests.mailDraft',
            'accountingRequests.mailThread',
            'accountingRequests.requestedBy',
            'accountingRequests.completedBy',
            'integrationDeliveries.source',
            'integrationDeliveries.queuedBy',
        ]);
    }

    /** @return array<string, string> */
    private function supplierRequestChoices(): array
    {
        return $this->record->items
            ->filter(fn (OrderItem $item): bool => in_array($item->fulfillment_route, ['supplier_purchase', 'direct_supplier'], true)
                && $item->fulfillment_supplier_id !== null)
            ->groupBy(fn (OrderItem $item): string => $item->fulfillment_supplier_id.'|'.$item->fulfillment_route)
            ->map(function ($items, string $key): string {
                /** @var OrderItem $first */
                $first = $items->first();

                return ($first->fulfillment_supplier_name ?: $first->fulfillmentSupplier?->name ?: 'Поставщик')
                    .' · '.(OrderItem::FULFILLMENT_ROUTES[$first->fulfillment_route] ?? $first->fulfillment_route)
                    .' · '.$items->count().' поз.';
            })
            ->all();
    }

    private function supplierEmailForRequestKey(string $key): ?string
    {
        [$supplierId] = explode('|', $key, 2);
        $supplier = $this->record->items
            ->firstWhere('fulfillment_supplier_id', (int) $supplierId)
            ?->fulfillmentSupplier;

        return $supplier?->contactEmailAddresses()[0] ?? null;
    }

    private function confirmAvailabilityBeforeShare(string $availabilityStatus, ?string $note = null): void
    {
        $this->record->load('items.fulfillmentSupplier');

        $missing = $this->record->items->filter(function (OrderItem $item): bool {
            if (blank($item->fulfillment_route)) {
                return true;
            }

            return in_array($item->fulfillment_route, ['supplier_purchase', 'direct_supplier'], true)
                && $item->fulfillment_supplier_id === null;
        });

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'availability_confirmation' => 'Сначала назначьте фактического поставщика и маршрут для: '
                    .$missing->pluck('product_name')->implode(', ').'.',
            ]);
        }

        foreach ($this->record->items as $item) {
            app(OrderItemFulfillmentManager::class)->confirm(
                $item,
                $item->fulfillment_route,
                $item->fulfillmentSupplier,
                auth()->user(),
                collect(['Подтверждено перед отправкой заказа.', $note])->filter()->implode(' '),
                $item->fulfillment_purchase_price !== null ? (float) $item->fulfillment_purchase_price : null,
                $availabilityStatus,
            );
        }

        $this->record->refresh()->load('items');
        app(OrderAvailabilityConfirmation::class)
            ->confirmAndNotify($this->record, auth()->user());

        $this->reloadRecord();
    }

    private function procurementEmailBody(string $portalUrl, ?string $note = null): string
    {
        $this->record->loadMissing(['items.fulfillmentSupplier', 'items.supplySupplier', 'items.supplierOrderRequestItem.request', 'manager']);
        $lines = [
            'Заказ '.$this->record->number.' передан в отдел закупок.',
            'Статус: '.(Order::STATUSES[$this->record->status] ?? $this->record->status).'.',
            'Ответственный: '.($this->record->manager?->name ?? $this->record->assigned_to ?? 'не назначен').'.',
            '',
            'Позиции:',
        ];

        foreach ($this->record->items as $item) {
            $supplier = $item->fulfillmentSupplier ?: $item->supplySupplier;
            $supplierName = $item->fulfillment_supplier_name ?: $supplier?->name ?: $item->supply_supplier_name ?: 'не определён';
            $purchasePrice = $item->fulfillment_purchase_price ?? $item->supply_purchase_price;
            $request = $item->supplierOrderRequestItem?->request;
            $status = $request?->statusLabel()
                ?? ($item->fulfillment_confirmed_at ? ($item->fulfillmentRouteLabel() ?: 'подтверждено') : 'ожидает решения');
            $lines[] = '• '.$item->product_name.' — '.$item->quantity.' шт. · '.$supplierName
                .' · вход '.($purchasePrice !== null ? number_format((float) $purchasePrice, 2, ',', ' ').' BYN' : 'не указан')
                .' · '.$status;
        }

        if (filled($note)) {
            $lines[] = '';
            $lines[] = 'Комментарий: '.trim((string) $note);
        }

        $lines[] = '';
        $lines[] = 'Онлайн-заказ со всеми позициями, ценами, поставщиками, остатками и статусами:';
        $lines[] = $portalUrl;
        $lines[] = 'Ссылка открывается сразу и не привязана к email — её можно переслать ответственному сотруднику.';

        return implode("\n", $lines);
    }

    private function accountingSuggestedAmount(): ?float
    {
        $amount = $this->record->items->sum(function (OrderItem $item): float {
            $price = $item->fulfillment_purchase_price ?? $item->supply_purchase_price;

            return $price !== null ? (float) $price * (int) $item->quantity : 0;
        });

        return $amount > 0 ? round($amount, 2) : null;
    }

    /** @param array<string, mixed> $data */
    private function accountingEmailBody(array $data): string
    {
        $suppliers = $this->record->items
            ->map(fn (OrderItem $item): ?string => $item->fulfillment_supplier_name ?: $item->supply_supplier_name)
            ->filter()->unique()->implode(', ');

        return collect([
            'Просим оплатить документ поставщика по заказу '.$this->record->number.'.',
            $suppliers !== '' ? 'Поставщик: '.$suppliers.'.' : null,
            filled($data['amount'] ?? null) ? 'Сумма: '.number_format((float) $data['amount'], 2, ',', ' ').' BYN.' : null,
            filled($data['due_date'] ?? null) ? 'Желаемый срок оплаты: '.date('d.m.Y', strtotime((string) $data['due_date'])).'.' : null,
            '',
            filled($data['comment'] ?? null) ? 'Комментарий: '.trim((string) $data['comment']) : null,
            'Документ для оплаты приложен к письму.',
        ])->filter(fn ($line): bool => $line !== null)->implode("\n");
    }

    private function orderPortalIsComplete(): bool
    {
        return $this->record->status === 'completed'
            || $this->record->status === 'cancelled'
            || ($this->record->status === 'delivered' && $this->record->payment_status === 'paid');
    }
}

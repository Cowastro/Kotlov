<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Supplier;
use App\Models\SupplierOrderRequest;
use App\Services\Orders\OrderItemFulfillmentManager;
use App\Services\Orders\OrderSettlementManager;
use App\Services\Orders\SupplierOrderRequestBuilder;
use App\Services\Orders\SupplierOrderRequestWorkflow;
use App\Services\Integrations\OneCOrderQueue;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Throwable;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function resolveRecord(int|string $key): Order
    {
        return Order::with([
            'items.product.category',
            'items.product.supplierProducts.supplier',
            'items.product.integrationProducts.source.supplier',
            'items.integrationProduct.source.supplier',
            'items.fulfillmentConfirmedBy',
            'items.fulfillmentSupplier',
            'fulfillmentHistory.item',
            'fulfillmentHistory.user',
            'fulfillmentHistory.previousSupplier',
            'latestSettlement.lines.orderItem',
            'latestSettlement.lines.supplier',
            'latestSettlement.confirmer',
            'supplierOrderRequests.items',
            'supplierOrderRequests.creator',
            'supplierOrderRequests.sentBy',
            'supplierOrderRequests.statusUpdatedBy',
            'statusHistory.user',
            'user',
            'manager',
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
            Action::make('acceptOrder')
                ->label('Принять заказ')
                ->icon('heroicon-o-hand-raised')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === 'new')
                ->requiresConfirmation()
                ->modalHeading('Принять заказ в работу?')
                ->modalDescription('Вы станете ответственным менеджером. Действие сохранится в истории статусов.')
                ->action(fn () => $this->transitionOrder('confirmed', 'Заказ принят в работу.')),
            Action::make('startProcessing')
                ->label('Передать в обработку')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->visible(fn (): bool => $this->record->status === 'confirmed')
                ->disabled(fn (): bool => $this->record->items->contains(fn (OrderItem $item): bool => blank($item->fulfillment_confirmed_at)))
                ->tooltip(fn (): ?string => $this->record->items->contains(fn (OrderItem $item): bool => blank($item->fulfillment_confirmed_at))
                    ? 'Сначала подтвердите исполнение всех позиций.'
                    : null)
                ->requiresConfirmation()
                ->modalHeading('Передать заказ в очередь 1С?')
                ->modalDescription('Заказ будет доступен центральной 1С при следующем обмене. Повторная передача после получения 1С будет заблокирована.')
                ->modalSubmitActionLabel('Передать в 1С')
                ->action(fn () => $this->queueForOneC()),
            Action::make('markShipped')
                ->label('Передать в доставку')
                ->icon('heroicon-o-truck')
                ->color('primary')
                ->visible(fn (): bool => $this->record->status === 'processing')
                ->requiresConfirmation()
                ->action(fn () => $this->transitionOrder('shipped', 'Заказ передан в доставку.')),
            Action::make('markDelivered')
                ->label('Отметить доставленным')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === 'shipped')
                ->requiresConfirmation()
                ->action(fn () => $this->transitionOrder('delivered', 'Клиент получил заказ.')),
            Action::make('confirmFulfillment')
                ->label('Подтвердить исполнение')
                ->icon('heroicon-o-check-badge')
                ->color('warning')
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
                        ->helperText('Обязателен для закупки и прямой передачи. Для нашего склада можно не выбирать.'),
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
                    );

                    if ($this->record->status === 'new') {
                        $this->record->transitionTo('confirmed', 'Заказ принят автоматически при подтверждении исполнения.', $this->managerAttributes());
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
                        ->body('Создано или обновлено заявок: '.$requests->count().'. Автоматической отправки не было.')
                        ->send();
                }),
            Action::make('confirmSettlement')
                ->label('Зафиксировать взаиморасчёт')
                ->icon('heroicon-o-banknotes')
                ->color('success')
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
            Action::make('publishSupplierRequest')
                ->label('Передать поставщику')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
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
            EditAction::make(),
        ];
    }

    private function transitionOrder(string $status, string $comment): void
    {
        $this->record->transitionTo($status, $comment, $this->managerAttributes());
        $this->reloadRecord();

        Notification::make()
            ->success()
            ->title('Статус: '.(Order::STATUSES[$status] ?? $status))
            ->body($this->record->workflowNextStep())
            ->send();
    }

    private function queueForOneC(): void
    {
        try {
            app(OneCOrderQueue::class)->queue(
                $this->record,
                auth()->user(),
                $this->managerAttributes(),
            );
            $this->reloadRecord();

            Notification::make()
                ->success()
                ->title('Заказ поставлен в очередь 1С')
                ->body('При следующем обмене 1С создаст документ «Заказ клиента».')
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->danger()
                ->title('Не удалось передать заказ')
                ->body($exception->getMessage())
                ->send();
        }
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
            'supplierOrderRequests.items',
            'integrationDeliveries.source',
            'integrationDeliveries.queuedBy',
        ]);
    }
}

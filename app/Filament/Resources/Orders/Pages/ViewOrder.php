<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Supplier;
use App\Models\SupplierOrderRequest;
use App\Services\Orders\OrderItemFulfillmentManager;
use App\Services\Orders\SupplierOrderRequestBuilder;
use App\Services\Orders\SupplierOrderRequestWorkflow;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;

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
            'fulfillmentHistory.item',
            'fulfillmentHistory.user',
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
            Action::make('confirmFulfillment')
                ->label('Подтвердить исполнение')
                ->icon('heroicon-o-check-badge')
                ->color('warning')
                ->modalHeading('Подтверждение исполнителя позиции')
                ->modalDescription('Рекомендация системы останется в снимке заказа. Здесь фиксируется решение менеджера и его история.')
                ->schema([
                    Select::make('order_item_id')
                        ->label('Позиция заказа')
                        ->options(fn (): array => $this->record->items
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

                    $this->record->refresh();

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
}

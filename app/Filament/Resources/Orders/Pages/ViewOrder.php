<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Supplier;
use App\Services\Orders\OrderItemFulfillmentManager;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
                    );

                    $this->record->refresh();

                    Notification::make()
                        ->success()
                        ->title('Исполнитель позиции подтверждён')
                        ->body('Решение сохранено в истории заказа.')
                        ->send();
                }),
            EditAction::make(),
        ];
    }
}

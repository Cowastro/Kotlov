<?php

namespace App\Filament\Resources\Orders\Actions;

use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Orders\OrderItemAnalogReplacer;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Collection;

final class FindOrderItemAnalogAction
{
    public static function make(): Action
    {
        return Action::make('findAnalog')
            ->label('Подобрать аналог')
            ->icon('heroicon-o-magnifying-glass-circle')
            ->color('warning')
            ->button()
            ->visible(fn (Order $record): bool => self::problemItems($record)->isNotEmpty())
            ->modalHeading(fn (Order $record): string => 'Подобрать аналог · '.$record->number)
            ->modalDescription('Показываем товары той же категории, сначала доступные и с остатком. Менеджер должен проверить техническую совместимость перед заменой.')
            ->modalWidth('4xl')
            ->schema([
                Select::make('order_item_id')
                    ->label('Проблемная позиция')
                    ->options(fn (Order $record): array => self::problemItems($record)
                        ->mapWithKeys(fn (OrderItem $item): array => [$item->id => self::itemLabel($item)])
                        ->all())
                    ->default(fn (Order $record): ?int => self::problemItems($record)->first()?->id)
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('replacement_product_id', null))
                    ->required(),
                Select::make('replacement_product_id')
                    ->label('Предлагаемый аналог')
                    ->placeholder('Выберите из категории или начните поиск')
                    ->options(fn (Get $get): array => self::analogOptions((int) $get('order_item_id'), ''))
                    ->getSearchResultsUsing(fn (string $search, Get $get): array => self::analogOptions(
                        (int) $get('order_item_id'),
                        $search,
                    ))
                    ->getOptionLabelUsing(function (mixed $value): ?string {
                        $product = Product::query()->withSupplierChannels()->find($value);

                        return $product ? OrderForm::productOptionLabel($product) : null;
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText('В списке видны розничная цена и доступность. Исходное количество сохранится.'),
            ])
            ->modalSubmitActionLabel('Заменить на аналог')
            ->action(function (Order $record, array $data): void {
                $result = app(OrderItemAnalogReplacer::class)->replace(
                    $record,
                    (int) $data['order_item_id'],
                    (int) $data['replacement_product_id'],
                    auth()->user(),
                );

                Notification::make()
                    ->success()
                    ->title('Аналог установлен')
                    ->body('«'.$result['old_name'].'» заменён на «'.$result['new_name'].'». Подтвердите наличие новой позиции.')
                    ->send();
            });
    }

    /** @return Collection<int, OrderItem> */
    private static function problemItems(Order $order): Collection
    {
        $order->loadMissing('items.product.category');

        if (! in_array($order->status, ['new', 'checking'], true)
            || $order->archived_at !== null
            || $order->payment_status === 'paid') {
            return $order->items->take(0);
        }

        return $order->items->filter(fn (OrderItem $item): bool => $item->fulfillment_availability_status === 'unavailable'
            || $item->supply_is_available === false
            || (! $item->fulfillment_supplier_id && ! $item->supply_supplier_id)
        )->values();
    }

    private static function itemLabel(OrderItem $item): string
    {
        $reason = match (true) {
            $item->fulfillment_availability_status === 'unavailable', $item->supply_is_available === false => 'нет в наличии',
            ! $item->fulfillment_supplier_id && ! $item->supply_supplier_id => 'поставщик не определён',
            default => 'требует проверки',
        };

        return $item->product_name.' · '.$item->quantity.' шт. · '.$reason;
    }

    /** @return array<int, string> */
    private static function analogOptions(int $itemId, string $search): array
    {
        $item = OrderItem::query()->with('product.category')->find($itemId);
        if (! $item) {
            return [];
        }

        $options = OrderForm::searchProductOptions(
            $search,
            categoryId: $item->product?->category_id,
        );
        unset($options[$item->product_id]);

        return $options;
    }
}

<?php

namespace App\Filament\Exports;

use App\Models\Order;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class OrderExporter extends Exporter
{
    protected static ?string $model = Order::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('number')
                ->label('Номер заказа'),

            ExportColumn::make('created_at')
                ->label('Дата создания')
                ->formatStateUsing(fn($state) => $state?->format('d.m.Y H:i')),

            ExportColumn::make('status')
                ->label('Статус')
                ->formatStateUsing(fn($state) => Order::STATUSES[$state] ?? $state),

            ExportColumn::make('customer_name')
                ->label('Имя клиента'),

            ExportColumn::make('customer_phone')
                ->label('Телефон'),

            ExportColumn::make('customer_email')
                ->label('Email'),

            ExportColumn::make('delivery_type')
                ->label('Способ доставки')
                ->formatStateUsing(fn($state) => Order::DELIVERY_TYPES[$state] ?? $state),

            ExportColumn::make('delivery_region')
                ->label('Область'),

            ExportColumn::make('delivery_city')
                ->label('Город'),

            ExportColumn::make('delivery_address')
                ->label('Адрес доставки'),

            ExportColumn::make('delivery_price')
                ->label('Стоимость доставки')
                ->formatStateUsing(fn($state) => number_format((float) $state, 2, '.', ' ')),

            ExportColumn::make('payment_type')
                ->label('Способ оплаты')
                ->formatStateUsing(fn($state) => Order::PAYMENT_TYPES[$state] ?? $state),

            ExportColumn::make('payment_status')
                ->label('Статус оплаты')
                ->formatStateUsing(fn($state) => match($state) {
                    'paid'     => 'Оплачен',
                    'pending'  => 'Ожидает оплаты',
                    'failed'   => 'Ошибка оплаты',
                    'refunded' => 'Возврат',
                    default    => $state,
                }),

            ExportColumn::make('subtotal')
                ->label('Сумма товаров')
                ->formatStateUsing(fn($state) => number_format((float) $state, 2, '.', ' ')),

            ExportColumn::make('discount')
                ->label('Скидка')
                ->formatStateUsing(fn($state) => number_format((float) $state, 2, '.', ' ')),

            ExportColumn::make('total')
                ->label('Итого')
                ->formatStateUsing(fn($state) => number_format((float) $state, 2, '.', ' ')),

            ExportColumn::make('items_list')
                ->label('Товары')
                ->getStateUsing(fn(Order $record) => $record->items
                    ->map(fn($item) => "{$item->product_name} (арт. {$item->product_sku}) × {$item->quantity} = {$item->total} BYN")
                    ->join(' | ')
                ),

            ExportColumn::make('comment')
                ->label('Комментарий клиента'),

            ExportColumn::make('admin_comment')
                ->label('Комментарий менеджера'),

            ExportColumn::make('company_name')
                ->label('Организация'),

            ExportColumn::make('company_unp')
                ->label('УНП'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $count = $export->successful_rows;
        return "Экспорт завершён. Выгружено {$count} " . match(true) {
            $count % 100 >= 11 && $count % 100 <= 19 => 'заказов',
            $count % 10 === 1 => 'заказ',
            $count % 10 >= 2 && $count % 10 <= 4 => 'заказа',
            default => 'заказов',
        } . '.';
    }
}

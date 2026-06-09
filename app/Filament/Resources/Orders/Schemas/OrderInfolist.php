<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $paymentNames  = collect(config('shop.payment_methods',  []))->mapWithKeys(fn($m, $k) => [$k => $m['name']])->toArray();
        $deliveryNames = collect(config('shop.delivery_methods', []))->mapWithKeys(fn($m, $k) => [$k => $m['name']])->toArray();

        $byn = fn($state) => number_format((float) $state, 2, '.', ' ') . ' BYN';

        $statusColor = fn(?string $state) => match($state) {
            'new'        => 'info',
            'confirmed'  => 'warning',
            'processing' => 'warning',
            'shipped'    => 'primary',
            'delivered'  => 'success',
            'completed'  => 'success',
            'cancelled'  => 'danger',
            default      => 'gray',
        };

        $paymentStatusColor = fn(?string $state) => match($state) {
            'paid'     => 'success',
            'pending'  => 'warning',
            'failed'   => 'danger',
            'refunded' => 'info',
            default    => 'gray',
        };

        $paymentStatusLabel = fn(?string $state) => match($state) {
            'paid'     => 'Оплачен',
            'pending'  => 'Ожидает оплаты',
            'failed'   => 'Ошибка оплаты',
            'refunded' => 'Возврат',
            default    => $state,
        };

        return $schema
            ->columns(3)
            ->components([

                // ── Шапка: полная ширина ──────────────────────────────────────
                Section::make()
                    ->columnSpanFull()
                    ->columns(7)
                    ->compact()
                    ->schema([
                        TextEntry::make('number')
                            ->label('Номер заказа')
                            ->copyable()
                            ->weight('bold'),

                        TextEntry::make('status')
                            ->label('Статус')
                            ->badge()
                            ->color($statusColor)
                            ->formatStateUsing(fn(string $state) => Order::STATUSES[$state] ?? $state),

                        TextEntry::make('created_at')
                            ->label('Создан')
                            ->dateTime('d.m.Y H:i'),

                        TextEntry::make('updated_at')
                            ->label('Обновлён')
                            ->dateTime('d.m.Y H:i'),

                        TextEntry::make('items_count')
                            ->label('Позиций')
                            ->getStateUsing(fn($record) => $record->items->count() . ' шт.'),

                        TextEntry::make('total')
                            ->label('Итого')
                            ->weight('bold')
                            ->color('primary')
                            ->formatStateUsing($byn),

                        TextEntry::make('payment_status')
                            ->label('Статус оплаты')
                            ->badge()
                            ->color($paymentStatusColor)
                            ->formatStateUsing($paymentStatusLabel),
                    ]),

                // ── Клиент (1 из 3) ──────────────────────────────────────────
                Section::make('Клиент')
                    ->icon('heroicon-o-user')
                    ->compact()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('customer_name')
                            ->label('Имя'),

                        TextEntry::make('customer_phone')
                            ->label('Телефон')
                            ->copyable()
                            ->url(fn($state) => $state ? 'tel:' . preg_replace('/\s+/', '', $state) : null),

                        TextEntry::make('customer_email')
                            ->label('Email')
                            ->copyable()
                            ->placeholder('—')
                            ->url(fn($state) => $state ? 'mailto:' . $state : null),

                        TextEntry::make('user.name')
                            ->label('Аккаунт')
                            ->placeholder('Гость'),

                        TextEntry::make('company_name')
                            ->label('Организация')
                            ->placeholder('—')
                            ->visible(fn($record) => (bool) $record->company_name),

                        TextEntry::make('company_unp')
                            ->label('УНП')
                            ->placeholder('—')
                            ->visible(fn($record) => (bool) $record->company_unp),
                    ]),

                // ── Доставка (2 из 3) ─────────────────────────────────────────
                Section::make('Доставка')
                    ->icon('heroicon-o-truck')
                    ->compact()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('delivery_type')
                            ->label('Способ')
                            ->formatStateUsing(fn(string $state) => Order::DELIVERY_TYPES[$state] ?? $deliveryNames[$state] ?? $state),

                        TextEntry::make('delivery_price')
                            ->label('Стоимость')
                            ->formatStateUsing(function($state, $record) use ($byn) {
                                if ($record->delivery_type === 'kit' && (float)$state === 0.0) {
                                    return 'Уточняется';
                                }
                                return (float)$state === 0.0 ? 'Бесплатно' : $byn($state);
                            }),

                        TextEntry::make('delivery_region')
                            ->label('Область')
                            ->placeholder('—'),

                        TextEntry::make('delivery_city')
                            ->label('Город')
                            ->placeholder('—'),

                        TextEntry::make('delivery_address')
                            ->label('Адрес')
                            ->placeholder('—')
                            ->url(fn($state, $record) => $state
                                ? 'https://maps.google.com/?q=' . urlencode(implode(', ', array_filter([
                                    $record->delivery_city,
                                    $record->delivery_address,
                                ])))
                                : null)
                            ->openUrlInNewTab(),
                    ]),

                // ── Оплата (3 из 3) ──────────────────────────────────────────
                Section::make('Оплата')
                    ->icon('heroicon-o-credit-card')
                    ->compact()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('payment_type')
                            ->label('Способ')
                            ->formatStateUsing(fn(string $state) => Order::PAYMENT_TYPES[$state] ?? $paymentNames[$state] ?? $state),

                        TextEntry::make('payment_status')
                            ->label('Статус')
                            ->badge()
                            ->color($paymentStatusColor)
                            ->formatStateUsing($paymentStatusLabel),

                        TextEntry::make('coupon_code')
                            ->label('Промокод')
                            ->placeholder('—'),

                        TextEntry::make('discount')
                            ->label('Скидка')
                            ->formatStateUsing(fn($state) => (float) $state > 0 ? $byn($state) : '—'),

                        TextEntry::make('subtotal')
                            ->label('Товары')
                            ->formatStateUsing($byn),

                        TextEntry::make('total')
                            ->label('Итого')
                            ->weight('bold')
                            ->color('primary')
                            ->formatStateUsing($byn),
                    ]),

                // ── Товары заказа: полная ширина ──────────────────────────────
                Section::make('Товары заказа')
                    ->icon('heroicon-o-shopping-bag')
                    ->columnSpanFull()
                    ->compact()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->columns(6)
                            ->schema([
                                TextEntry::make('product_name')
                                    ->label('Товар')
                                    ->columnSpan(2)
                                    ->url(fn($state, $record) => $record->product?->category
                                        ? url('/' . $record->product->category->slug . '/' . $record->product->slug)
                                        : null)
                                    ->openUrlInNewTab(),

                                TextEntry::make('product_sku')
                                    ->label('Артикул')
                                    ->placeholder('—')
                                    ->fontFamily('mono')
                                    ->copyable(),

                                TextEntry::make('quantity')
                                    ->label('Кол-во')
                                    ->suffix(' шт.'),

                                TextEntry::make('price')
                                    ->label('Цена')
                                    ->formatStateUsing($byn),

                                TextEntry::make('total')
                                    ->label('Сумма')
                                    ->weight('bold')
                                    ->color('primary')
                                    ->formatStateUsing($byn),
                            ]),
                    ]),

                // ── Комментарии: полная ширина ────────────────────────────────
                Section::make('Комментарии')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->columnSpanFull()
                    ->columns(2)
                    ->compact()
                    ->schema([
                        TextEntry::make('comment')
                            ->label('Комментарий клиента')
                            ->placeholder('Комментарий отсутствует'),

                        TextEntry::make('admin_comment')
                            ->label('Комментарий менеджера')
                            ->placeholder('Комментарий отсутствует'),
                    ]),

                // ── История: полная ширина ────────────────────────────────────
                Section::make('История изменений статуса')
                    ->icon('heroicon-o-clock')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('statusHistory')
                            ->hiddenLabel()
                            ->columns(5)
                            ->contained(false)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Дата')
                                    ->dateTime('d.m.Y H:i'),

                                TextEntry::make('user.name')
                                    ->label('Кто изменил')
                                    ->placeholder('Система'),

                                TextEntry::make('status_from')
                                    ->label('Было')
                                    ->badge()
                                    ->color($statusColor)
                                    ->formatStateUsing(fn(?string $state) => $state ? (Order::STATUSES[$state] ?? $state) : '—'),

                                TextEntry::make('status_to')
                                    ->label('Стало')
                                    ->badge()
                                    ->color($statusColor)
                                    ->formatStateUsing(fn(?string $state) => $state ? (Order::STATUSES[$state] ?? $state) : '—'),

                                TextEntry::make('comment')
                                    ->label('Комментарий')
                                    ->placeholder('—'),
                            ]),
                    ]),

            ]);
    }
}

<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $paymentNames  = collect(config('shop.payment_methods',  []))->mapWithKeys(fn($m, $k) => [$k => $m['name']])->toArray();
        $deliveryNames = collect(config('shop.delivery_methods', []))->mapWithKeys(fn($m, $k) => [$k => $m['name']])->toArray();

        $byn = fn($state) => number_format((float) $state, 2, '.', ' ') . ' BYN';

        return $schema->components([

            Section::make('Основная информация')
                ->icon('heroicon-o-document-text')
                ->columns(4)
                ->schema([
                    TextEntry::make('number')
                        ->label('Номер заказа')
                        ->copyable(),

                    TextEntry::make('status')
                        ->label('Статус')
                        ->badge()
                        ->color(fn(string $state) => match($state) {
                            'new'        => 'info',
                            'confirmed'  => 'warning',
                            'processing' => 'warning',
                            'shipped'    => 'primary',
                            'delivered'  => 'success',
                            'cancelled'  => 'danger',
                            default      => 'gray',
                        })
                        ->formatStateUsing(fn(string $state) => \App\Models\Order::STATUSES[$state] ?? $state),

                    TextEntry::make('created_at')
                        ->label('Дата заказа')
                        ->dateTime('d.m.Y H:i'),

                    TextEntry::make('user.name')
                        ->label('Аккаунт')
                        ->placeholder('Гость'),
                ]),

            Section::make('Клиент')
                ->icon('heroicon-o-user')
                ->columns(3)
                ->schema([
                    TextEntry::make('customer_name')
                        ->label('Имя'),

                    TextEntry::make('customer_phone')
                        ->label('Телефон')
                        ->copyable(),

                    TextEntry::make('customer_email')
                        ->label('Email')
                        ->copyable()
                        ->placeholder('—'),
                ]),

            Section::make('Доставка')
                ->icon('heroicon-o-truck')
                ->columns(3)
                ->schema([
                    TextEntry::make('delivery_type')
                        ->label('Способ доставки')
                        ->formatStateUsing(fn(string $state) => $deliveryNames[$state] ?? $state),

                    TextEntry::make('delivery_price')
                        ->label('Стоимость доставки')
                        ->formatStateUsing(fn($state) => (float) $state === 0.0 ? 'Бесплатно' : $byn($state)),

                    TextEntry::make('delivery_region')
                        ->label('Регион')
                        ->placeholder('—'),

                    TextEntry::make('delivery_city')
                        ->label('Город')
                        ->placeholder('—'),

                    TextEntry::make('delivery_address')
                        ->label('Адрес')
                        ->placeholder('—')
                        ->columnSpan(2),
                ]),

            Section::make('Оплата')
                ->icon('heroicon-o-credit-card')
                ->columns(4)
                ->schema([
                    TextEntry::make('payment_type')
                        ->label('Способ оплаты')
                        ->formatStateUsing(fn(string $state) => $paymentNames[$state] ?? $state),

                    TextEntry::make('payment_status')
                        ->label('Статус оплаты')
                        ->badge()
                        ->color(fn(string $state) => match($state) {
                            'paid'    => 'success',
                            'pending' => 'warning',
                            default   => 'gray',
                        })
                        ->formatStateUsing(fn(string $state) => match($state) {
                            'paid'    => 'Оплачен',
                            'pending' => 'Ожидает оплаты',
                            default   => $state,
                        }),

                    TextEntry::make('coupon_code')
                        ->label('Промокод')
                        ->placeholder('—'),

                    TextEntry::make('discount')
                        ->label('Скидка')
                        ->formatStateUsing(fn($state) => (float) $state > 0 ? $byn($state) : '—'),
                ]),

            Section::make('Суммы')
                ->icon('heroicon-o-calculator')
                ->columns(4)
                ->schema([
                    TextEntry::make('subtotal')
                        ->label('Сумма товаров')
                        ->formatStateUsing($byn),

                    TextEntry::make('delivery_price')
                        ->label('Доставка')
                        ->formatStateUsing(fn($state) => (float) $state === 0.0 ? 'Бесплатно' : $byn($state)),

                    TextEntry::make('discount')
                        ->label('Скидка')
                        ->formatStateUsing(fn($state) => (float) $state > 0 ? $byn($state) : '—'),

                    TextEntry::make('total')
                        ->label('Итого')
                        ->weight('bold')
                        ->color('primary')
                        ->formatStateUsing($byn),
                ]),

            Section::make('Товары заказа')
                ->icon('heroicon-o-shopping-bag')
                ->schema([
                    RepeatableEntry::make('items')
                        ->label('')
                        ->columns(5)
                        ->schema([
                            TextEntry::make('product_name')
                                ->label('Товар')
                                ->columnSpan(2),

                            TextEntry::make('product_sku')
                                ->label('Артикул')
                                ->placeholder('—'),

                            TextEntry::make('quantity')
                                ->label('Кол-во')
                                ->suffix(' шт.'),

                            TextEntry::make('price')
                                ->label('Цена')
                                ->formatStateUsing($byn),

                            TextEntry::make('total')
                                ->label('Сумма')
                                ->weight('bold')
                                ->formatStateUsing($byn),
                        ]),
                ]),

            Section::make('Комментарии')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->columns(2)
                ->schema([
                    TextEntry::make('comment')
                        ->label('Комментарий клиента')
                        ->placeholder('—'),

                    TextEntry::make('admin_comment')
                        ->label('Комментарий менеджера')
                        ->placeholder('—'),
                ]),

        ]);
    }
}

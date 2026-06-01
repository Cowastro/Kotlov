<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Клиент')
                    ->columns(2)
                    ->schema([
                        Select::make('user_id')
                            ->label('Пользователь')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->placeholder('— Гость —'),

                        TextInput::make('number')
                            ->label('Номер заказа')
                            ->required()
                            ->default(fn() => \App\Models\Order::generateNumber()),

                        TextInput::make('customer_name')
                            ->label('Имя клиента')
                            ->required(),

                        TextInput::make('customer_phone')
                            ->label('Телефон')
                            ->tel()
                            ->required(),

                        TextInput::make('customer_email')
                            ->label('Email')
                            ->email(),
                    ]),

                Section::make('Статус')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->label('Статус заказа')
                            ->options([
                                'new'        => 'Новый',
                                'confirmed'  => 'Подтверждён',
                                'processing' => 'В обработке',
                                'shipped'    => 'Отправлен',
                                'delivered'  => 'Доставлен',
                                'cancelled'  => 'Отменён',
                            ])
                            ->default('new')
                            ->required(),

                        Select::make('payment_status')
                            ->label('Статус оплаты')
                            ->options([
                                'pending' => 'Ожидает оплаты',
                                'paid'    => 'Оплачен',
                            ])
                            ->default('pending')
                            ->required(),
                    ]),

                Section::make('Доставка')
                    ->columns(2)
                    ->schema([
                        Select::make('delivery_type')
                            ->label('Тип доставки')
                            ->options([
                                'pickup'    => 'Самовывоз',
                                'courier'   => 'Курьером',
                                'transport' => 'Транспортной компанией',
                            ])
                            ->default('courier')
                            ->required(),

                        TextInput::make('delivery_price')
                            ->label('Стоимость доставки')
                            ->numeric()
                            ->prefix('BYN')
                            ->default(0),

                        TextInput::make('delivery_region')
                            ->label('Регион'),

                        TextInput::make('delivery_city')
                            ->label('Город'),

                        TextInput::make('delivery_address')
                            ->label('Адрес')
                            ->columnSpanFull(),
                    ]),

                Section::make('Оплата и скидки')
                    ->columns(2)
                    ->schema([
                        Select::make('payment_type')
                            ->label('Способ оплаты')
                            ->options([
                                'cash'    => 'Наличными',
                                'card'    => 'Картой',
                                'invoice' => 'По счёту',
                            ])
                            ->default('cash')
                            ->required(),

                        TextInput::make('coupon_code')
                            ->label('Промокод'),

                        TextInput::make('discount')
                            ->label('Скидка')
                            ->numeric()
                            ->prefix('BYN')
                            ->default(0),

                        TextInput::make('subtotal')
                            ->label('Сумма товаров')
                            ->numeric()
                            ->prefix('BYN')
                            ->default(0),

                        TextInput::make('total')
                            ->label('Итого')
                            ->numeric()
                            ->prefix('BYN')
                            ->default(0),
                    ]),

                Section::make('Комментарии')
                    ->schema([
                        Textarea::make('comment')
                            ->label('Комментарий клиента')
                            ->rows(3),

                        Textarea::make('admin_comment')
                            ->label('Комментарий менеджера')
                            ->rows(3),
                    ]),
            ]);
    }
}
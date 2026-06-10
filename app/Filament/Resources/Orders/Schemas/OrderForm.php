<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
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

                // ── 1. Быстрое управление ──────────────────────────────────────
                Section::make('Быстрое управление')
                    ->description('Основные поля для управления заказом')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->label('Статус заказа')
                            ->options(Order::STATUSES)
                            ->required()
                            ->native(false),

                        Select::make('payment_status')
                            ->label('Статус оплаты')
                            ->options([
                                'pending'  => 'Ожидает оплаты',
                                'paid'     => 'Оплачен',
                                'failed'   => 'Ошибка оплаты',
                                'refunded' => 'Возврат',
                            ])
                            ->required()
                            ->native(false),

                        Select::make('payment_type')
                            ->label('Способ оплаты')
                            ->options(Order::PAYMENT_TYPES)
                            ->required()
                            ->native(false),

                        Select::make('delivery_type')
                            ->label('Тип доставки')
                            ->options(Order::DELIVERY_TYPES)
                            ->required()
                            ->native(false),

                        Textarea::make('admin_comment')
                            ->label('Комментарий менеджера')
                            ->rows(3)
                            ->columnSpanFull()
                            ->required(fn ($get) => $get('status') === 'cancelled')
                            ->helperText(fn ($get) => $get('status') === 'cancelled'
                                ? '⚠️ При отмене заказа необходимо указать причину.'
                                : null),
                    ]),

                // ── 2. Клиент ─────────────────────────────────────────────────
                Section::make('Клиент')
                    ->columns(2)
                    ->schema([
                        TextInput::make('customer_name')
                            ->label('Имя клиента')
                            ->required(),

                        TextInput::make('customer_phone')
                            ->label('Телефон')
                            ->tel()
                            ->required(),

                        TextInput::make('customer_email')
                            ->label('Email')
                            ->email()
                            ->columnSpanFull(),
                    ]),

                // ── 3. Юридическое лицо ───────────────────────────────────────
                Section::make('Юридическое лицо')
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        TextInput::make('company_name')
                            ->label('Название организации'),

                        TextInput::make('company_unp')
                            ->label('УНП / ИНН'),

                        TextInput::make('company_address')
                            ->label('Адрес компании')
                            ->columnSpanFull(),

                        TextInput::make('company_email')
                            ->label('Email компании')
                            ->email()
                            ->columnSpanFull(),
                    ]),

                // ── 4. Доставка ───────────────────────────────────────────────
                Section::make('Доставка')
                    ->columns(2)
                    ->schema([
                        TextInput::make('delivery_region')
                            ->label('Область / регион'),

                        TextInput::make('delivery_city')
                            ->label('Город'),

                        TextInput::make('delivery_address')
                            ->label('Адрес доставки')
                            ->columnSpanFull(),

                        TextInput::make('delivery_price')
                            ->label('Стоимость доставки')
                            ->numeric()
                            ->prefix('BYN')
                            ->helperText(
                                fn ($get) => $get('delivery_type') === 'kit'
                                    ? 'Для ТК КИТ цена 0 означает "Стоимость уточняется", а не бесплатную доставку.'
                                    : null
                            )
                            ->default(0),
                    ]),

                // ── 5. Суммы ──────────────────────────────────────────────────
                Section::make('Суммы')
                    ->description('Изменение сумм влияет только на заказ в админке и не пересчитывает товары автоматически.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('subtotal')
                            ->label('Сумма товаров')
                            ->numeric()
                            ->prefix('BYN')
                            ->default(0),

                        TextInput::make('discount')
                            ->label('Скидка')
                            ->numeric()
                            ->prefix('BYN')
                            ->default(0),

                        TextInput::make('total')
                            ->label('Итого')
                            ->numeric()
                            ->prefix('BYN')
                            ->default(0),
                    ]),

                // ── 6. Комментарии ────────────────────────────────────────────
                Section::make('Комментарий клиента')
                    ->schema([
                        Textarea::make('comment')
                            ->label('Комментарий клиента')
                            ->rows(3)
                            ->disabled()
                            ->dehydrated(false),
                    ]),

                // ── 7. Техническая информация ─────────────────────────────────
                Section::make('Техническая информация')
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        TextInput::make('number')
                            ->label('Номер заказа')
                            ->required()
                            ->default(fn () => Order::generateNumber()),

                        Select::make('user_id')
                            ->label('Аккаунт пользователя')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->placeholder('— Гость —'),

                        TextInput::make('coupon_code')
                            ->label('Промокод'),
                    ]),

            ]);
    }
}

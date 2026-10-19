<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use App\Models\Product;
use App\Services\StorefrontProductSearch;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Управление заказом')
                    ->description('Этап меняется отдельными кнопками, чтобы в истории сохранялись причина и сотрудник.')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->columnSpanFull()
                    ->columns(4)
                    ->schema([
                        Select::make('status')
                            ->label('Текущий этап')
                            ->options(Order::STATUSES)
                            ->disabled()
                            ->dehydrated(false)
                            ->native(false),
                        Select::make('payment_status')
                            ->label('Статус оплаты')
                            ->options([
                                'pending' => 'Ожидает оплаты',
                                'paid' => 'Оплачен',
                                'failed' => 'Ошибка оплаты',
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
                            ->helperText('Внутренняя заметка. Для отказа или неактуального заказа используйте красную кнопку сверху.'),
                    ]),

                Section::make('Клиент')
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->schema([
                        TextInput::make('customer_name')->label('Имя клиента')->required(),
                        TextInput::make('customer_phone')->label('Телефон')->tel()->required(),
                        TextInput::make('customer_email')->label('Email')->email()->columnSpanFull(),
                    ]),

                Section::make('Состав заказа')
                    ->description('Чтобы заменить товар, удалите старую строку и добавьте новую. Название, артикул и текущая цена подставятся из каталога, но их можно уточнить вручную.')
                    ->icon('heroicon-o-shopping-cart')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->label('Позиции')
                            ->addActionLabel('Добавить позицию')
                            ->minItems(1)
                            ->columns(12)
                            ->schema([
                                Select::make('product_id')
                                    ->label('Товар из каталога')
                                    ->searchable()
                                    ->getSearchResultsUsing(fn (string $search): array => self::searchProductOptions($search))
                                    ->getOptionLabelUsing(fn (mixed $value): ?string => self::selectedProductOptionLabel($value))
                                    ->preload(false)
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $get, callable $set): void {
                                        $product = $state ? Product::query()->find($state) : null;

                                        if (! $product) {
                                            return;
                                        }

                                        $set('product_name', $product->name);
                                        $set('product_sku', $product->sku);
                                        $set('price', (float) $product->price);
                                        $set('total', round(
                                            max(1, (int) $get('quantity')) * max(0, (float) $product->price),
                                            2,
                                        ));
                                    })
                                    ->columnSpan(5),
                                TextInput::make('product_name')
                                    ->label('Наименование')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(5),
                                TextInput::make('product_sku')
                                    ->label('Артикул')
                                    ->maxLength(100)
                                    ->columnSpan(2),
                                TextInput::make('quantity')
                                    ->label('Количество')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, callable $get, callable $set) => self::updateItemTotal($get, $set))
                                    ->columnSpan(3),
                                TextInput::make('price')
                                    ->label('Цена, BYN')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, callable $get, callable $set) => self::updateItemTotal($get, $set))
                                    ->columnSpan(3),
                                TextInput::make('total')
                                    ->label('Сумма, BYN')
                                    ->numeric()
                                    ->readOnly()
                                    ->dehydrated()
                                    ->default(0)
                                    ->columnSpan(3),
                            ]),
                    ]),

                Section::make('Доставка')
                    ->icon('heroicon-o-truck')
                    ->columns(2)
                    ->schema([
                        TextInput::make('delivery_region')->label('Область / регион'),
                        TextInput::make('delivery_city')->label('Город'),
                        TextInput::make('delivery_address')->label('Адрес доставки')->columnSpanFull(),
                        TextInput::make('delivery_price')
                            ->label('Стоимость доставки')
                            ->numeric()
                            ->prefix('BYN')
                            ->helperText(fn ($get) => $get('delivery_type') === 'kit'
                                ? 'Для ТК КИТ цена 0 означает «Стоимость уточняется», а не бесплатную доставку.'
                                : null)
                            ->default(0),
                    ]),

                Section::make('Суммы')
                    ->icon('heroicon-o-banknotes')
                    ->description('Сумма товаров и итог пересчитываются автоматически после сохранения состава заказа.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('subtotal')->label('Сумма товаров')->numeric()->prefix('BYN')->readOnly()->default(0),
                        TextInput::make('discount')->label('Скидка')->numeric()->prefix('BYN')->default(0),
                        TextInput::make('total')->label('Итого')->numeric()->prefix('BYN')->readOnly()->default(0)->columnSpanFull(),
                    ]),

                Section::make('Юридическое лицо')
                    ->icon('heroicon-o-building-office')
                    ->columnSpanFull()
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        TextInput::make('company_name')->label('Название организации'),
                        TextInput::make('company_unp')->label('УНП / ИНН'),
                        TextInput::make('company_address')->label('Адрес компании')->columnSpanFull(),
                        TextInput::make('company_email')->label('Email компании')->email()->columnSpanFull(),
                    ]),

                Section::make('Комментарий клиента')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('comment')
                            ->label('Комментарий клиента')
                            ->rows(3)
                            ->disabled()
                            ->dehydrated(false),
                    ]),

                Section::make('Техническая информация')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->columnSpanFull()
                    ->collapsed()
                    ->columns(3)
                    ->schema([
                        TextInput::make('number')->label('Номер заказа')->required()->default(fn () => Order::generateNumber()),
                        Select::make('user_id')
                            ->label('Аккаунт пользователя')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->placeholder('— Гость —'),
                        TextInput::make('coupon_code')->label('Промокод'),
                    ]),
            ]);
    }

    private static function updateItemTotal(callable $get, callable $set): void
    {
        $quantity = max(1, (int) $get('quantity'));
        $price = max(0, (float) $get('price'));

        $set('total', round($quantity * $price, 2));
    }

    /** @return array<int, string> */
    public static function searchProductOptions(string $search): array
    {
        $search = mb_substr(trim($search), 0, 100);

        if (mb_strlen($search) < 1) {
            return [];
        }

        $query = Product::query()
            ->active()
            ->notArchived()
            ->withSupplierChannels()
            ->with(['brand:id,name', 'category:id,name']);

        app(StorefrontProductSearch::class)->apply($query, $search, true);

        return $query
            ->orderByDesc('is_featured')
            ->orderByDesc('rating')
            ->orderByDesc('views_count')
            ->limit(30)
            ->get()
            ->mapWithKeys(fn (Product $product): array => [
                $product->id => self::productOptionLabel($product),
            ])
            ->all();
    }

    private static function selectedProductOptionLabel(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        $product = Product::query()
            ->withSupplierChannels()
            ->find($value);

        return $product ? self::productOptionLabel($product) : null;
    }

    public static function productOptionLabel(Product $product): string
    {
        $price = (float) $product->price > 0
            ? number_format((float) $product->price, 2, ',', ' ').' BYN'
            : 'Цена по запросу';

        return implode(' · ', array_filter([
            $product->name,
            filled($product->sku) ? 'арт. '.$product->sku : null,
            'розница '.$price,
            $product->publicAvailabilityLabel(),
        ]));
    }
}

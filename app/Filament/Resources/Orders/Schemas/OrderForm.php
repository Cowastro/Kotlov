<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\OrderItem;
use App\Services\Orders\OrderItemSupplyContextResolver;
use App\Services\StorefrontProductSearch;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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
                    ->description('Аналог: выберите другой товар в существующей строке. Добавление: нажмите «Добавить позицию». Удаление: нажмите значок корзины у ненужной строки. После корректировки сохраните заказ и заново подтвердите изменённые позиции.')
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
                                Select::make('catalog_supplier_id')
                                    ->label('Поставщик / склад')
                                    ->placeholder('Все поставщики')
                                    ->options(fn (): array => self::supplierOptions())
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->dehydrated(false)
                                    ->afterStateUpdated(function (Set $set): void {
                                        $set('catalog_category_id', null);
                                        self::clearSelectedProduct($set);
                                    })
                                    ->helperText('Оставьте пустым, чтобы искать по всему каталогу.')
                                    ->columnSpan(6),
                                Select::make('catalog_category_id')
                                    ->label('Категория')
                                    ->placeholder('Все категории')
                                    ->options(fn (Get $get): array => self::categoryOptions(self::filterId($get('catalog_supplier_id'))))
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->dehydrated(false)
                                    ->afterStateUpdated(function (Set $set): void {
                                        self::clearSelectedProduct($set);
                                    })
                                    ->helperText('Список категорий учитывает выбранного поставщика.')
                                    ->columnSpan(6),
                                Select::make('product_id')
                                    ->label('Найти и выбрать товар из каталога')
                                    ->placeholder('Введите название, модель или артикул')
                                    ->searchable()
                                    ->getSearchResultsUsing(fn (string $search, Get $get): array => self::searchProductOptions(
                                        $search,
                                        self::filterId($get('catalog_supplier_id')),
                                        self::filterId($get('catalog_category_id')),
                                    ))
                                    ->getOptionLabelUsing(fn (mixed $value, Get $get): ?string => self::selectedProductOptionLabel(
                                        $value,
                                        self::filterId($get('catalog_supplier_id')),
                                    ))
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $get, callable $set): void {
                                        $product = $state ? Product::query()->find($state) : null;

                                        if (! $product) {
                                            return;
                                        }

                                        $set('product_name', $product->name);
                                        $set('product_sku', $product->sku);
                                        $set('price', (float) $product->price);
                                        self::setSuggestedSupplier(
                                            $product,
                                            self::filterId($get('catalog_supplier_id')),
                                            $set,
                                        );
                                        $set('total', round(
                                            max(1, (int) $get('quantity')) * max(0, (float) $product->price),
                                            2,
                                        ));
                                    })
                                    ->helperText('Сначала ищем в выбранной категории. Если там пусто — покажем подходящие товары этого же поставщика из других категорий с пометкой.')
                                    ->columnSpanFull(),
                                Select::make('fulfillment_supplier_id')
                                    ->label('Поставщик исполнения')
                                    ->options(fn (): array => Supplier::query()
                                        ->where('is_active', true)
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all())
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateHydrated(function ($state, ?OrderItem $record, callable $set): void {
                                        if (filled($state) || ! $record?->product) {
                                            return;
                                        }

                                        self::setSuggestedSupplier(
                                            $record->product,
                                            $record->fulfillment_supplier_id
                                                ?: data_get($record->current_availability, 'supplier_id')
                                                ?: $record->supply_supplier_id,
                                            $set,
                                        );
                                    })
                                    ->afterStateUpdated(function ($state, callable $set): void {
                                        $supplier = $state ? Supplier::query()->find($state) : null;
                                        $set('fulfillment_supplier_name', $supplier?->name);
                                        $set('fulfillment_supplier_contact', $supplier?->contactSummary());
                                        $set('fulfillment_route', $supplier?->code === 'sanbusinessgroup'
                                            ? 'own_stock'
                                            : 'supplier_purchase');
                                    })
                                    ->helperText('Если поставщик уже понятен, выберите его сейчас. Окончательно способ исполнения и поставщик фиксируются при подтверждении позиции.')
                                    ->columnSpanFull(),
                                Hidden::make('fulfillment_supplier_name'),
                                Hidden::make('fulfillment_supplier_contact'),
                                Hidden::make('fulfillment_route'),
                                TextInput::make('product_name')
                                    ->label('Название выбранного товара')
                                    ->required()
                                    ->maxLength(255)
                                    ->readOnly()
                                    ->helperText('Заполняется автоматически из выбранной карточки товара.')
                                    ->columnSpan(8),
                                TextInput::make('product_sku')
                                    ->label('Артикул')
                                    ->maxLength(100)
                                    ->columnSpan(4),
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

    private static function clearSelectedProduct(Set $set): void
    {
        $set('product_id', null);
        $set('product_name', null);
        $set('product_sku', null);
        $set('fulfillment_supplier_id', null);
        $set('fulfillment_supplier_name', null);
        $set('fulfillment_supplier_contact', null);
        $set('fulfillment_route', null);
    }

    private static function setSuggestedSupplier(Product $product, ?int $filteredSupplierId, callable $set): void
    {
        $supplierId = $filteredSupplierId;

        if (! $supplierId) {
            $item = new OrderItem(['product_id' => $product->id]);
            $item->setRelation('product', $product);
            $supplierId = (int) (app(OrderItemSupplyContextResolver::class)->resolveCurrent($item)['supplier_id'] ?? 0);
        }

        if (! $supplierId && $product->manual_stock_management) {
            $supplierId = (int) (Supplier::query()
                ->where('code', 'sanbusinessgroup')
                ->orWhere('name', 'like', '%СанБизнесГруп%')
                ->value('id') ?? 0);
        }

        $supplier = $supplierId > 0 ? Supplier::query()->find($supplierId) : null;
        $set('fulfillment_supplier_id', $supplier?->id);
        $set('fulfillment_supplier_name', $supplier?->name);
        $set('fulfillment_supplier_contact', $supplier?->contactSummary());
        $set('fulfillment_route', $supplier?->code === 'sanbusinessgroup'
            ? 'own_stock'
            : ($supplier ? 'supplier_purchase' : null));
    }

    /** @return array<int, string> */
    public static function searchProductOptions(
        string $search,
        ?int $supplierId = null,
        ?int $categoryId = null,
    ): array
    {
        $search = mb_substr(trim($search), 0, 100);

        if (mb_strlen($search) < 1 && ! $supplierId && ! $categoryId) {
            return [];
        }

        $query = Product::query()
            ->active()
            ->notArchived()
            ->withSupplierChannels()
            ->with(['brand:id,name', 'category:id,name']);

        self::applyPickerFilters($query, $supplierId, $categoryId);

        if ($search !== '') {
            app(StorefrontProductSearch::class)->apply($query, $search, true);
        }

        $products = $query
            ->orderByDesc('in_stock')
            ->orderByDesc('stock_qty')
            ->orderByDesc('is_featured')
            ->orderByDesc('rating')
            ->orderByDesc('views_count')
            ->limit(50)
            ->get();

        $outsideSelectedCategory = false;

        if ($products->isEmpty() && $search !== '' && $categoryId) {
            $fallbackQuery = Product::query()
                ->active()
                ->notArchived()
                ->withSupplierChannels()
                ->with(['brand:id,name', 'category:id,name']);

            self::applySupplierFilter($fallbackQuery, $supplierId);
            app(StorefrontProductSearch::class)->apply($fallbackQuery, $search, true);

            $products = $fallbackQuery
                ->orderByDesc('in_stock')
                ->orderByDesc('stock_qty')
                ->orderByDesc('is_featured')
                ->orderByDesc('rating')
                ->orderByDesc('views_count')
                ->limit(50)
                ->get();
            $outsideSelectedCategory = $products->isNotEmpty();
        }

        return $products
            ->mapWithKeys(fn (Product $product): array => [
                $product->id => self::productOptionLabel($product, $supplierId, $outsideSelectedCategory),
            ])
            ->all();
    }

    private static function selectedProductOptionLabel(mixed $value, ?int $supplierId = null): ?string
    {
        if (! $value) {
            return null;
        }

        $product = Product::query()
            ->withSupplierChannels()
            ->find($value);

        return $product ? self::productOptionLabel($product, $supplierId) : null;
    }

    public static function productOptionLabel(
        Product $product,
        ?int $supplierId = null,
        bool $outsideSelectedCategory = false,
    ): string
    {
        $price = (float) $product->price > 0
            ? number_format((float) $product->price, 2, ',', ' ').' BYN'
            : 'Цена по запросу';

        return implode(' · ', array_filter([
            $product->name,
            filled($product->sku) ? 'арт. '.$product->sku : null,
            $outsideSelectedCategory ? 'другая категория: '.($product->category?->name ?: 'не указана') : null,
            $supplierId ? self::supplierOfferLabel($product, $supplierId) : null,
            'розница '.$price,
            $supplierId ? null : $product->publicAvailabilityLabel(),
        ]));
    }

    /** @return array<int, string> */
    public static function supplierOptions(): array
    {
        return Supplier::query()
            ->where('is_active', true)
            ->where(function (Builder $query): void {
                $query
                    ->whereHas('supplierProducts.product', fn (Builder $product): Builder => $product->active()->notArchived())
                    ->orWhereHas('integrationSources', fn (Builder $source): Builder => $source
                        ->where('is_active', true)
                        ->whereHas('products.product', fn (Builder $product): Builder => $product->active()->notArchived()));
            })
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array<int, string> */
    public static function categoryOptions(?int $supplierId = null): array
    {
        $productCategoryIds = Product::query()
            ->active()
            ->notArchived()
            ->whereNotNull('category_id')
            ->tap(fn (Builder $query) => self::applySupplierFilter($query, $supplierId))
            ->distinct()
            ->pluck('category_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        if ($productCategoryIds === []) {
            return [];
        }

        $categories = Category::query()
            ->where('is_active', true)
            ->get(['id', 'parent_id', 'name'])
            ->keyBy('id');
        $visibleIds = collect($productCategoryIds)->flip();

        foreach ($productCategoryIds as $categoryId) {
            $parentId = (int) ($categories->get($categoryId)?->parent_id ?? 0);
            $guard = 0;

            while ($parentId > 0 && $guard++ < 12) {
                $visibleIds->put($parentId, true);
                $parentId = (int) ($categories->get($parentId)?->parent_id ?? 0);
            }
        }

        return $categories
            ->filter(fn (Category $category): bool => $visibleIds->has($category->id))
            ->sortBy(fn (Category $category): string => self::categoryPath($category, $categories))
            ->mapWithKeys(fn (Category $category): array => [
                $category->id => self::categoryPath($category, $categories),
            ])
            ->all();
    }

    public static function filterId(mixed $value): ?int
    {
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    private static function applyPickerFilters(Builder $query, ?int $supplierId, ?int $categoryId): void
    {
        self::applySupplierFilter($query, $supplierId);

        if ($categoryId) {
            $query->whereIn('category_id', self::categoryAndDescendantIds($categoryId));
        }
    }

    private static function applySupplierFilter(Builder $query, ?int $supplierId): void
    {
        if (! $supplierId) {
            return;
        }

        $query->where(function (Builder $query) use ($supplierId): void {
            $query
                ->whereHas('supplierProducts', fn (Builder $offer): Builder => $offer->where('supplier_id', $supplierId))
                ->orWhereHas('integrationProducts', fn (Builder $offer): Builder => $offer
                    ->where('match_status', 'matched')
                    ->whereHas('source', fn (Builder $source): Builder => $source
                        ->where('supplier_id', $supplierId)
                        ->where('is_active', true)));
        });
    }

    /** @return list<int> */
    private static function categoryAndDescendantIds(int $categoryId): array
    {
        $categories = Category::query()->where('is_active', true)->get(['id', 'parent_id']);
        $ids = [$categoryId];

        do {
            $children = $categories
                ->whereIn('parent_id', $ids)
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->diff($ids)
                ->values()
                ->all();
            $before = count($ids);
            $ids = array_values(array_unique([...$ids, ...$children]));
        } while (count($ids) > $before);

        return $ids;
    }

    /** @param Collection<int, Category> $categories */
    private static function categoryPath(Category $category, Collection $categories): string
    {
        $parts = [$category->name];
        $parentId = (int) $category->parent_id;
        $guard = 0;

        while ($parentId > 0 && $guard++ < 12) {
            $parent = $categories->get($parentId);
            if (! $parent) {
                break;
            }

            array_unshift($parts, $parent->name);
            $parentId = (int) $parent->parent_id;
        }

        return implode(' → ', $parts);
    }

    private static function supplierOfferLabel(Product $product, int $supplierId): string
    {
        $legacy = $product->supplierProducts->where('supplier_id', $supplierId);
        $integrated = $product->integrationProducts->filter(
            fn ($offer): bool => (int) $offer->source?->supplier_id === $supplierId
                && $offer->match_status === 'matched'
                && (bool) $offer->source?->is_active,
        );
        $supplierName = $legacy->first()?->supplier?->name
            ?? $integrated->first()?->source?->supplier?->name
            ?? 'Выбранный поставщик';
        $quantities = $legacy->pluck('stock_quantity')
            ->merge($integrated->pluck('stock_quantity'))
            ->filter(fn (mixed $value): bool => $value !== null)
            ->map(fn (mixed $value): float => (float) $value);
        $available = $quantities->sum() > 0
            || $legacy->contains(fn ($offer): bool => (bool) $offer->in_stock);
        $stock = $quantities->isNotEmpty()
            ? 'склад '.number_format($quantities->sum(), 0, ',', ' ').' шт.'
            : ($available ? 'есть на складе' : 'остаток не указан');
        $purchasePrices = $legacy->pluck('price_byn')
            ->merge($integrated->map(fn ($offer) => $offer->normalizedPriceByn()))
            ->filter(fn (mixed $value): bool => (float) $value > 0)
            ->map(fn (mixed $value): float => (float) $value);
        $purchase = $purchasePrices->isNotEmpty()
            ? 'закупка '.number_format($purchasePrices->min(), 2, ',', ' ').' BYN'
            : 'закупка не указана';

        return $supplierName.' · '.$stock.' · '.$purchase;
    }
}

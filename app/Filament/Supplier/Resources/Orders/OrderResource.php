<?php

namespace App\Filament\Supplier\Resources\Orders;

use App\Filament\Supplier\Resources\Orders\Pages\ListOrders;
use App\Filament\Supplier\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?string $navigationLabel = 'Заказы';

    protected static ?string $modelLabel = 'заказ';

    protected static ?string $pluralModelLabel = 'Заказы';

    protected static ?string $recordTitleAttribute = 'number';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return 'Продажи';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->whereIn('status', ['new', 'confirmed', 'processing'])->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $money = fn ($state): string => number_format((float) $state, 2, ',', ' ').' BYN';

        return $schema
            ->columns(4)
            ->components([
                Section::make('Заказ')
                    ->columnSpanFull()
                    ->columns(6)
                    ->compact()
                    ->schema([
                        TextEntry::make('number')
                            ->label('Номер')
                            ->copyable()
                            ->weight('bold'),
                        TextEntry::make('created_at')
                            ->label('Создан')
                            ->dateTime('d.m.Y H:i', 'Europe/Minsk'),
                        TextEntry::make('status')
                            ->label('Статус заказа')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => Order::STATUSES[$state] ?? $state)
                            ->color(fn (string $state): string => static::statusColor($state)),
                        TextEntry::make('payment_status')
                            ->label('Оплата')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => static::paymentStatusLabel($state))
                            ->color(fn (string $state): string => $state === 'paid' ? 'success' : 'warning'),
                        TextEntry::make('supplier_items_count')
                            ->label('Ваших позиций')
                            ->suffix(' шт.'),
                        TextEntry::make('supplier_subtotal')
                            ->label('Сумма ваших товаров')
                            ->formatStateUsing($money)
                            ->weight('bold')
                            ->color('primary'),
                    ]),

                Section::make('Ваши товары в заказе')
                    ->description('Показаны только позиции назначенных вам поставщиков. Чужие товары и общий итог заказа скрыты.')
                    ->columnSpanFull()
                    ->compact()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->columns(7)
                            ->schema([
                                TextEntry::make('integrationProduct.source.name')
                                    ->label('Источник')
                                    ->badge(),
                                TextEntry::make('product_name')
                                    ->label('Товар')
                                    ->columnSpan(2)
                                    ->weight('medium'),
                                TextEntry::make('product_sku')
                                    ->label('Артикул')
                                    ->placeholder('—')
                                    ->copyable(),
                                TextEntry::make('price')
                                    ->label('Цена')
                                    ->formatStateUsing($money)
                                    ->alignRight(),
                                TextEntry::make('quantity')
                                    ->label('Кол-во')
                                    ->alignRight(),
                                TextEntry::make('total')
                                    ->label('Сумма')
                                    ->formatStateUsing($money)
                                    ->weight('bold')
                                    ->alignRight(),
                            ]),
                    ]),

                Section::make('Доставка')
                    ->description('Контактные данные покупателя остаются у KOTLOV до назначения поставщику отдельного сценария исполнения.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->compact()
                    ->schema([
                        TextEntry::make('delivery_type')
                            ->label('Способ')
                            ->formatStateUsing(fn (string $state): string => Order::DELIVERY_TYPES[$state] ?? $state),
                        TextEntry::make('delivery_city')
                            ->label('Город')
                            ->placeholder('Не указан'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label('Заказ')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime('d.m.Y H:i', 'Europe/Minsk')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Order::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => static::statusColor($state)),
                TextColumn::make('payment_status')
                    ->label('Оплата')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => static::paymentStatusLabel($state))
                    ->color(fn (string $state): string => $state === 'paid' ? 'success' : 'warning'),
                TextColumn::make('items.integrationProduct.source.name')
                    ->label('Источник')
                    ->badge()
                    ->listWithLineBreaks()
                    ->limitList(2),
                TextColumn::make('supplier_items_count')
                    ->label('Ваших позиций')
                    ->numeric()
                    ->alignRight(),
                TextColumn::make('supplier_subtotal')
                    ->label('Ваша сумма')
                    ->money('BYN')
                    ->alignRight()
                    ->weight('bold'),
                TextColumn::make('delivery_city')
                    ->label('Город')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус заказа')
                    ->options(Order::STATUSES),
                SelectFilter::make('payment_status')
                    ->label('Оплата')
                    ->options([
                        'pending' => 'Ожидает оплаты',
                        'paid' => 'Оплачен',
                    ]),
            ])
            ->recordActions([
                ViewAction::make()->label('Открыть'),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }

    public static function getEloquentQuery(): Builder
    {
        $supplierIds = static::supplierIds();
        $supplierItems = fn ($query) => $query
            ->whereHas('integrationProduct.source', fn (Builder $query): Builder => $query
                ->whereIn('supplier_id', $supplierIds));

        return parent::getEloquentQuery()
            ->whereHas('items', $supplierItems)
            ->with([
                'items' => fn ($query) => $supplierItems($query)
                    ->with(['integrationProduct.source']),
            ])
            ->withCount(['items as supplier_items_count' => $supplierItems])
            ->withSum(['items as supplier_subtotal' => $supplierItems], 'total');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }

    private static function supplierIds(): array
    {
        return auth()->user()?->suppliers()
            ->pluck('suppliers.id')
            ->map(fn ($id): int => (int) $id)
            ->all() ?? [];
    }

    private static function statusColor(string $status): string
    {
        return match ($status) {
            'new' => 'info',
            'confirmed', 'processing' => 'warning',
            'shipped' => 'primary',
            'delivered' => 'success',
            'cancelled' => 'danger',
            default => 'gray',
        };
    }

    private static function paymentStatusLabel(string $status): string
    {
        return match ($status) {
            'paid' => 'Оплачен',
            'pending' => 'Ожидает оплаты',
            default => $status,
        };
    }
}

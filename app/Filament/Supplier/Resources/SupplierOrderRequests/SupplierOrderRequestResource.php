<?php

namespace App\Filament\Supplier\Resources\SupplierOrderRequests;

use App\Filament\Supplier\Resources\SupplierOrderRequests\Pages\ListSupplierOrderRequests;
use App\Filament\Supplier\Resources\SupplierOrderRequests\Pages\ViewSupplierOrderRequest;
use App\Models\OrderItem;
use App\Models\SupplierOrderRequest;
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

class SupplierOrderRequestResource extends Resource
{
    protected static ?string $model = SupplierOrderRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Заявки KOTLOV';

    protected static ?string $modelLabel = 'заявка KOTLOV';

    protected static ?string $pluralModelLabel = 'Заявки KOTLOV';

    protected static ?string $recordTitleAttribute = 'number';

    protected static ?string $slug = 'requests';

    protected static ?int $navigationSort = 0;

    public static function getNavigationGroup(): ?string
    {
        return 'Продажи';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', 'sent')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
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
                Section::make('Заявка')
                    ->description('Эта заявка опубликована KOTLOV именно вашему поставщику. Данные покупателей здесь не раскрываются.')
                    ->columnSpanFull()
                    ->compact()
                    ->columns(6)
                    ->schema([
                        TextEntry::make('number')->label('Номер')->copyable()->weight('bold')->columnSpan(2),
                        TextEntry::make('order.number')->label('Заказ KOTLOV')->copyable(),
                        TextEntry::make('route')
                            ->label('Маршрут')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => OrderItem::FULFILLMENT_ROUTES[$state] ?? $state),
                        TextEntry::make('status')
                            ->label('Статус')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => SupplierOrderRequest::STATUSES[$state] ?? $state)
                            ->color(fn (string $state): string => static::statusColor($state)),
                        TextEntry::make('sent_at')->label('Передана')->dateTime('d.m.Y H:i:s', 'Europe/Minsk'),
                        TextEntry::make('item_count')->label('Позиций')->suffix(' шт.'),
                        TextEntry::make('purchase_total')
                            ->label('Сумма закупки')
                            ->formatStateUsing(fn ($state): string => $state === null ? 'Цена требует уточнения' : $money($state))
                            ->color(fn ($state): string => $state === null ? 'warning' : 'primary'),
                        TextEntry::make('note')->label('Комментарий KOTLOV')->placeholder('—')->columnSpan(2),
                        TextEntry::make('supplier_response_note')->label('Ваш ответ')->placeholder('—')->columnSpan(2),
                    ]),

                Section::make('Позиции заявки')
                    ->columnSpanFull()
                    ->compact()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->columns(6)
                            ->schema([
                                TextEntry::make('product_name')->label('Товар')->weight('medium')->columnSpan(2),
                                TextEntry::make('product_sku')->label('Артикул')->placeholder('—')->copyable(),
                                TextEntry::make('quantity')->label('Кол-во')->suffix(' шт.'),
                                TextEntry::make('purchase_price')
                                    ->label('Цена / шт.')
                                    ->formatStateUsing(fn ($state): string => $state === null ? 'Требует уточнения' : $money($state)),
                                TextEntry::make('purchase_total')
                                    ->label('Сумма')
                                    ->formatStateUsing(fn ($state): string => $state === null ? 'Не рассчитана' : $money($state)),
                            ]),
                    ]),

                Section::make('История статусов')
                    ->columnSpanFull()
                    ->compact()
                    ->visible(fn (SupplierOrderRequest $record): bool => $record->statusHistories->isNotEmpty())
                    ->schema([
                        RepeatableEntry::make('statusHistories')
                            ->hiddenLabel()
                            ->columns(5)
                            ->schema([
                                TextEntry::make('created_at')->label('Дата')->dateTime('d.m.Y H:i:s', 'Europe/Minsk'),
                                TextEntry::make('actor_name')->label('Кто')->placeholder('Система'),
                                TextEntry::make('actor_scope')
                                    ->label('Сторона')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => $state === 'supplier' ? 'Поставщик' : 'KOTLOV'),
                                TextEntry::make('status_to')
                                    ->label('Новый статус')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => SupplierOrderRequest::STATUSES[$state] ?? $state)
                                    ->color(fn (string $state): string => static::statusColor($state)),
                                TextEntry::make('note')->label('Комментарий')->placeholder('—'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label('Заявка')->searchable()->copyable()->weight('bold'),
                TextColumn::make('order.number')->label('Заказ KOTLOV')->searchable()->copyable(),
                TextColumn::make('sent_at')->label('Передана')->dateTime('d.m.Y H:i', 'Europe/Minsk')->sortable(),
                TextColumn::make('route')
                    ->label('Маршрут')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => OrderItem::FULFILLMENT_ROUTES[$state] ?? $state),
                TextColumn::make('item_count')->label('Позиций')->suffix(' шт.')->alignRight(),
                TextColumn::make('purchase_total')
                    ->label('Сумма закупки')
                    ->money('BYN')
                    ->placeholder('Требует уточнения')
                    ->alignRight(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => SupplierOrderRequest::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => static::statusColor($state)),
                TextColumn::make('status_updated_at')
                    ->label('Обновлена')
                    ->dateTime('d.m.Y H:i', 'Europe/Minsk')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(collect(SupplierOrderRequest::STATUSES)->except(['draft', 'cancelled'])->all()),
            ])
            ->recordActions([
                ViewAction::make()->label('Открыть'),
            ])
            ->defaultSort('sent_at', 'desc')
            ->poll('30s');
    }

    public static function getEloquentQuery(): Builder
    {
        $supplierIds = auth()->user()?->suppliers()->pluck('suppliers.id')->all() ?? [];

        return parent::getEloquentQuery()
            ->whereIn('supplier_id', $supplierIds)
            ->whereIn('status', ['sent', 'acknowledged', 'rejected', 'fulfilled'])
            ->with(['order:id,number', 'items', 'statusHistories']);
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
            'index' => ListSupplierOrderRequests::route('/'),
            'view' => ViewSupplierOrderRequest::route('/{record}'),
        ];
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'acknowledged' => 'info',
            'fulfilled' => 'success',
            'rejected' => 'danger',
            default => 'warning',
        };
    }
}

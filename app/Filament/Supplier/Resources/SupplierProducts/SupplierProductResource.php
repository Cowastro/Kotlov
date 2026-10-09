<?php

namespace App\Filament\Supplier\Resources\SupplierProducts;

use App\Filament\Supplier\Resources\SupplierProducts\Pages\ListSupplierProducts;
use App\Models\SupplierProduct;
use App\Services\SupplierProductHealth;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SupplierProductResource extends Resource
{
    protected static ?string $model = SupplierProduct::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $navigationLabel = 'Мои товары';

    protected static ?string $modelLabel = 'товар поставщика';

    protected static ?string $pluralModelLabel = 'Мои товары';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('supplier.name')
                    ->label('Поставщик')
                    ->badge()
                    ->sortable(),
                TextColumn::make('supplier_name')
                    ->label('Наименование')
                    ->searchable()
                    ->wrap()
                    ->description(fn (SupplierProduct $record): ?string => $record->supplier_article
                        ? 'Арт. '.$record->supplier_article
                        : null),
                TextColumn::make('product.name')
                    ->label('Карточка KOTLOV')
                    ->placeholder('Не привязан')
                    ->wrap()
                    ->url(fn (SupplierProduct $record): ?string => self::productUrl($record))
                    ->openUrlInNewTab(),
                TextColumn::make('operational_status')
                    ->label('Состояние')
                    ->state(fn (SupplierProduct $record): string => app(SupplierProductHealth::class)->describe($record)['label'])
                    ->description(fn (SupplierProduct $record): string => app(SupplierProductHealth::class)->describe($record)['description'])
                    ->badge()
                    ->color(fn (SupplierProduct $record): string => app(SupplierProductHealth::class)->describe($record)['color'])
                    ->wrap(),
                TextColumn::make('price_byn')
                    ->label('Цена, BYN')
                    ->money('BYN')
                    ->alignRight()
                    ->sortable()
                    ->description(fn (SupplierProduct $record): string => $record->currency === 'BYN'
                        ? 'Исходная цена'
                        : number_format((float) $record->price, 2, ',', ' ').' '.$record->currency),
                TextColumn::make('stock_quantity')
                    ->label('Остаток')
                    ->numeric()
                    ->alignRight()
                    ->sortable()
                    ->description(fn (SupplierProduct $record): ?string => $record->warehouse_name),
                IconColumn::make('in_stock')
                    ->label('В наличии')
                    ->boolean(),
                TextColumn::make('last_synced_at')
                    ->label('Синхронизирован')
                    ->dateTime('d.m.Y H:i', 'Europe/Minsk')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('supplier_id')
                    ->label('Поставщик')
                    ->options(fn (): array => auth()->user()?->suppliers()
                        ->orderBy('name')
                        ->pluck('name', 'suppliers.id')
                        ->all() ?? []),
                TernaryFilter::make('in_stock')
                    ->label('Наличие'),
                TernaryFilter::make('product_id')
                    ->label('Привязка к сайту')
                    ->nullable(),
            ])
            ->defaultSort('last_synced_at', 'desc')
            ->poll('60s');
    }

    public static function getEloquentQuery(): Builder
    {
        $supplierIds = auth()->user()?->suppliers()->pluck('suppliers.id') ?? collect();

        return parent::getEloquentQuery()
            ->with(['supplier', 'product.category'])
            ->forSupplierIds($supplierIds);
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
            'index' => ListSupplierProducts::route('/'),
        ];
    }

    private static function productUrl(SupplierProduct $record): ?string
    {
        $categorySlug = $record->product?->category?->slug;
        $productSlug = $record->product?->slug;

        if (! $categorySlug || ! $productSlug) {
            return null;
        }

        return url('/'.$categorySlug.'/'.$productSlug);
    }
}

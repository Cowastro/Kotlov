<?php

namespace App\Filament\Supplier\Resources\SupplierSyncChanges;

use App\Filament\Supplier\Resources\SupplierSyncChanges\Pages\ListSupplierSyncChanges;
use App\Models\SupplierSyncChange;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SupplierSyncChangeResource extends Resource
{
    protected static ?string $model = SupplierSyncChange::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'История изменений';

    protected static ?string $modelLabel = 'изменение каталога';

    protected static ?string $pluralModelLabel = 'История изменений';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'Каталог';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Когда')
                    ->dateTime('d.m.Y H:i', 'Europe/Minsk')
                    ->since()
                    ->sortable(),
                TextColumn::make('supplier.name')
                    ->label('Поставщик')
                    ->badge()
                    ->sortable(),
                TextColumn::make('product_name')
                    ->label('Товар')
                    ->placeholder('Карточка удалена или ещё не привязана')
                    ->description(fn (SupplierSyncChange $record): string => collect([
                        $record->supplier_article ? 'Арт. '.$record->supplier_article : null,
                        $record->product_sku ? 'SKU '.$record->product_sku : null,
                    ])->filter()->implode(' · '))
                    ->searchable()
                    ->wrap(),
                TextColumn::make('change_flags')
                    ->label('Что изменилось')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::flagLabel($state))
                    ->separator(','),
                TextColumn::make('supplier_price_after')
                    ->label('Цена поставщика')
                    ->money('BYN')
                    ->placeholder('—')
                    ->description(fn (SupplierSyncChange $record): ?string => $record->supplier_price_before === null
                        ? null
                        : 'было '.number_format((float) $record->supplier_price_before, 2, ',', ' ').' BYN')
                    ->alignRight(),
                TextColumn::make('stock_quantity_after')
                    ->label('Остаток')
                    ->numeric()
                    ->placeholder('—')
                    ->description(fn (SupplierSyncChange $record): ?string => $record->stock_quantity_before === null
                        ? null
                        : 'было '.number_format((int) $record->stock_quantity_before, 0, ',', ' '))
                    ->alignRight(),
                TextColumn::make('run.status')
                    ->label('Результат')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'success' => 'Успешно',
                        'running' => 'Выполняется',
                        'failed' => 'Ошибка',
                        'journal_error' => 'Ошибка журнала',
                        default => $state ?: 'Неизвестно',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'success' => 'success',
                        'running' => 'info',
                        default => 'danger',
                    }),
            ])
            ->filters([
                SelectFilter::make('supplier_id')
                    ->label('Поставщик')
                    ->options(fn (): array => auth()->user()?->suppliers()
                        ->orderBy('name')
                        ->pluck('name', 'suppliers.id')
                        ->all() ?? []),
                SelectFilter::make('change_type')
                    ->label('Тип изменения')
                    ->options([
                        'supplier_price' => 'Цена поставщика',
                        'retail_price' => 'Розничная цена',
                        'stock_quantity' => 'Количество',
                        'in_stock' => 'Наличие',
                        'product_link' => 'Привязка к карточке',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereJsonContains('change_flags', $data['value'])
                        : $query),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('60s');
    }

    public static function getEloquentQuery(): Builder
    {
        $supplierIds = auth()->user()?->suppliers()->pluck('suppliers.id') ?? collect();

        return parent::getEloquentQuery()
            ->with(['run', 'supplier'])
            ->whereIn('supplier_id', $supplierIds);
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
            'index' => ListSupplierSyncChanges::route('/'),
        ];
    }

    private static function flagLabel(string $flag): string
    {
        return match ($flag) {
            'supplier_price' => 'Цена поставщика',
            'retail_price' => 'Розничная цена',
            'in_stock' => 'Наличие',
            'stock_quantity' => 'Количество',
            'stock_status' => 'Статус остатка',
            'availability_status' => 'Доступность на сайте',
            'product_link' => 'Привязка к карточке',
            'link_created' => 'Новая позиция',
            'link_removed' => 'Позиция удалена',
            default => $flag,
        };
    }
}

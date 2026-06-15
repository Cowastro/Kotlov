<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SupplierProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'supplierProducts';

    protected static ?string $title = 'Данные поставщика';
    protected static ?string $label = 'Связка поставщика';
    protected static ?string $pluralLabel = 'Данные поставщиков';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->with(['supplier', 'supplierSync', 'product']))
            ->columns([
                TextColumn::make('supplier.name')
                    ->label('Поставщик')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('supplier_article')
                    ->label('Артикул поставщика')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('supplier_article_normalized')
                    ->label('Ключ артикула')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('product_sku')
                    ->label('SKU KOTLOV')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('price')
                    ->label('Закупка поставщика')
                    ->formatStateUsing(fn($state, $record): string => $state === null
                        ? '—'
                        : number_format((float) $state, 2, ',', ' ') . ' ' . ($record->currency ?: 'BYN'))
                    ->description(fn($record) => $record->currency && $record->currency !== 'BYN'
                        ? 'курс ' . rtrim(rtrim(number_format((float) $record->currency_rate, 4, '.', ''), '0'), '.')
                        : null)
                    ->sortable(),

                TextColumn::make('price_byn')
                    ->label('Закупка, BYN')
                    ->formatStateUsing(fn($state): string => $state === null
                        ? '—'
                        : number_format((float) $state, 2, ',', ' ') . ' BYN')
                    ->sortable(),

                TextColumn::make('product.price')
                    ->label('Розница сайта')
                    ->formatStateUsing(fn($state, $record): string => $state === null || (float) $state <= 0
                        ? '—'
                        : number_format((float) $state, 2, ',', ' ') . ' ' . ($record->product?->currency ?: 'BYN'))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('margin')
                    ->label('Маржа')
                    ->getStateUsing(function ($record): string {
                        $cost = $record->price_byn !== null ? (float) $record->price_byn : 0.0;
                        $retail = $record->product?->price !== null ? (float) $record->product->price : 0.0;

                        if ($cost <= 0 || $retail <= 0) {
                            return '—';
                        }

                        $margin = $retail - $cost;
                        $percent = $retail > 0 ? ($margin / $retail) * 100 : 0;

                        return number_format($margin, 2, ',', ' ') . ' BYN / ' . number_format($percent, 1, ',', ' ') . '%';
                    })
                    ->badge()
                    ->color(function ($record): string {
                        $cost = $record->price_byn !== null ? (float) $record->price_byn : 0.0;
                        $retail = $record->product?->price !== null ? (float) $record->product->price : 0.0;

                        if ($cost <= 0 || $retail <= 0) {
                            return 'gray';
                        }

                        $percent = (($retail - $cost) / $retail) * 100;

                        return match (true) {
                            $percent <= 0 => 'danger',
                            $percent < 10 => 'warning',
                            default => 'success',
                        };
                    }),

                TextColumn::make('price_source')
                    ->label('Источник закупки')
                    ->getStateUsing(function ($record): string {
                        $raw = is_array($record->raw) ? $record->raw : [];
                        $isBania = mb_strtolower((string) ($record->supplier?->code ?? '')) === 'bania';

                        if ($isBania && isset($raw['google_price_list'])) {
                            return 'Прайс BANIA';
                        }

                        if ($isBania) {
                            return 'Уточнить';
                        }

                        return 'Импорт';
                    })
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Прайс BANIA' => 'success',
                        'Уточнить' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('stock_quantity')
                    ->label('Кол-во, шт')
                    ->formatStateUsing(fn($state): string => $state !== null ? (string) $state : '—')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('stock_status')
                    ->label('Статус склада')
                    ->badge()
                    ->color(fn(?string $state): string => match($state) {
                        'in_stock'     => 'success',
                        'low_stock'    => 'warning',
                        'preorder'     => 'info',
                        'out_of_stock' => 'danger',
                        'discontinued' => 'gray',
                        default        => 'gray',
                    })
                    ->formatStateUsing(fn(?string $state): string => match($state) {
                        'in_stock'     => 'В наличии',
                        'low_stock'    => 'Мало',
                        'preorder'     => 'Под заказ',
                        'out_of_stock' => 'Нет',
                        'discontinued' => 'Снято',
                        default        => $state ?? '—',
                    })
                    ->placeholder('—'),

                TextColumn::make('warehouse_name')
                    ->label('Склад')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('in_stock')
                    ->label('Наличие')
                    ->boolean(),

                TextColumn::make('match_status')
                    ->label('Статус сопоставления')
                    ->badge()
                    ->color(fn(?string $state): string => match ($state) {
                        'matched' => 'success',
                        'unmatched' => 'warning',
                        'manual' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('source_url')
                    ->label('Источник')
                    ->limit(36)
                    ->tooltip(fn($state) => $state)
                    ->url(fn($state) => $state)
                    ->openUrlInNewTab(),

                TextColumn::make('last_stock_synced_at')
                    ->label('Остатки обновлены')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('last_synced_at')
                    ->label('Синхронизирован')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('source')
                    ->label('Открыть источник')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn($record) => $record->source_url)
                    ->openUrlInNewTab()
                    ->visible(fn($record) => filled($record->source_url)),
            ])
            ->emptyStateHeading('Данные поставщика не привязаны')
            ->emptyStateDescription('После синхронизации здесь появятся артикул поставщика, источник и статус сопоставления.');
    }
}

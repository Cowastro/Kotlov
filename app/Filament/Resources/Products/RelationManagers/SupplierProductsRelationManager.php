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
            ->modifyQueryUsing(fn($query) => $query->with(['supplier', 'supplierSync']))
            ->columns([
                TextColumn::make('supplier.name')
                    ->label('Поставщик')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('supplier_article')
                    ->label('Артикул поставщика')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('product_sku')
                    ->label('SKU KOTLOV')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('price')
                    ->label('Цена')
                    ->formatStateUsing(fn($state, $record): string => $state === null
                        ? '—'
                        : number_format((float) $state, 2, ',', ' ') . ' ' . ($record->currency ?: 'BYN'))
                    ->sortable(),

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

                TextColumn::make('last_synced_at')
                    ->label('Обновлено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->placeholder('—'),
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

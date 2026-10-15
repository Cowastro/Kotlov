<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class PurchasePriceSourcesRelationManager extends RelationManager
{
    protected static string $relationship = 'purchasePriceSources';

    protected static ?string $title = 'Подтверждённые закупочные цены';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['supplier', 'creator'])->latest('confirmed_at'))
            ->columns([
                TextColumn::make('document_number')
                    ->label('Счёт')
                    ->formatStateUsing(fn ($state, $record): string => $record->documentLabel())
                    ->weight('bold'),
                TextColumn::make('supplier_display')
                    ->label('Поставщик')
                    ->state(fn ($record): ?string => $record->supplierDisplayName())
                    ->placeholder('—'),
                TextColumn::make('source_price')
                    ->label('Цена в документе')
                    ->formatStateUsing(fn ($state, $record): string => number_format((float) $state, 2, ',', ' ').' '.$record->currency),
                TextColumn::make('price_byn')
                    ->label('Закупка с НДС')
                    ->money('BYN')
                    ->weight('bold'),
                TextColumn::make('valid_until')
                    ->label('Действует до')
                    ->date('d.m.Y')
                    ->placeholder('Без ограничения'),
                IconColumn::make('attachment_path')
                    ->label('Файл')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => filled($record->attachment_path)),
                ToggleColumn::make('is_active')
                    ->label('Использовать'),
                TextColumn::make('creator.name')
                    ->label('Подтвердил')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('confirmed_at')
                    ->label('Подтверждено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Открыть счёт')
                    ->icon('heroicon-o-document-arrow-down')
                    ->url(fn ($record): string => route('admin.purchase-price-sources.attachment', $record))
                    ->openUrlInNewTab()
                    ->visible(fn ($record): bool => filled($record->attachment_path)),
            ])
            ->emptyStateHeading('Подтверждённых счетов пока нет')
            ->emptyStateDescription('Добавьте входящий счёт из коммерческого предложения — его цена будет использоваться для проверки маржи.');
    }
}

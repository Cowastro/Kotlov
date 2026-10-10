<?php

namespace App\Filament\Resources\IntegrationSources\RelationManagers;

use App\Models\IntegrationSourcePricingChange;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PricingChangesRelationManager extends RelationManager
{
    protected static string $relationship = 'pricingChanges';

    protected static ?string $title = 'История правил цен и НДС';

    protected static ?string $label = 'Изменение правил цены';

    protected static ?string $pluralLabel = 'История правил цен и НДС';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('user')->latest('changed_at'))
            ->columns([
                TextColumn::make('changed_at')
                    ->label('Когда')
                    ->dateTime('d.m.Y H:i:s', 'Europe/Minsk')
                    ->sortable(),
                TextColumn::make('changes')
                    ->label('Что изменилось')
                    ->state(fn (IntegrationSourcePricingChange $record): string => $record->changeSummary())
                    ->wrap(),
                TextColumn::make('before')
                    ->label('Было')
                    ->state(fn (IntegrationSourcePricingChange $record): string => $record->valuesSummary('before_values'))
                    ->wrap(),
                TextColumn::make('after')
                    ->label('Стало')
                    ->state(fn (IntegrationSourcePricingChange $record): string => $record->valuesSummary('after_values'))
                    ->wrap(),
                TextColumn::make('actor')
                    ->label('Кто изменил')
                    ->state(fn (IntegrationSourcePricingChange $record): string => $record->actor_name
                        ?: $record->user?->name
                        ?: 'Система')
                    ->badge(),
            ])
            ->defaultSort('changed_at', 'desc')
            ->emptyStateHeading('Правила цены ещё не изменялись')
            ->emptyStateDescription('После изменения режима НДС, ставки или публикации партнёрских цен здесь появится неизменяемая запись.');
    }
}

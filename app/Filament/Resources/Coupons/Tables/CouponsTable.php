<?php

namespace App\Filament\Resources\Coupons\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CouponsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Код')
                    ->searchable()
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn($state) => match($state) {
                        'percent' => '% Процент',
                        'fixed'   => 'BYN Фиксированная',
                        default   => $state,
                    }),

                TextColumn::make('value')
                    ->label('Скидка')
                    ->formatStateUsing(fn($state, $record) =>
                        $record->type === 'percent'
                            ? $state . '%'
                            : $state . ' BYN'
                    ),

                TextColumn::make('min_order_amount')
                    ->label('От суммы')
                    ->formatStateUsing(fn($state) => $state > 0 ? $state . ' BYN' : '—'),

                TextColumn::make('uses_count')
                    ->label('Использований')
                    ->formatStateUsing(fn($state, $record) =>
                        $state . ($record->uses_limit ? ' / ' . $record->uses_limit : '')
                    ),

                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),

                TextColumn::make('expires_at')
                    ->label('Истекает')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->placeholder('Бессрочно'),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Активность'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
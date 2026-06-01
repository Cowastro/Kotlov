<?php

namespace App\Filament\Resources\Faqs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class FaqsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('question')
                    ->label('Вопрос')
                    ->searchable()
                    ->weight('bold')
                    ->limit(60),

                TextColumn::make('category')
                    ->label('Категория')
                    ->badge()
                    ->formatStateUsing(fn($state) => match($state) {
                        'delivery' => 'Доставка',
                        'payment'  => 'Оплата',
                        'products' => 'Товары',
                        'install'  => 'Монтаж',
                        'warranty' => 'Гарантия',
                        'other'    => 'Прочее',
                        default    => $state,
                    }),

                TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('category')
                    ->label('Категория')
                    ->options([
                        'delivery' => 'Доставка',
                        'payment'  => 'Оплата',
                        'products' => 'Товары',
                        'install'  => 'Монтаж',
                        'warranty' => 'Гарантия',
                        'other'    => 'Прочее',
                    ]),

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
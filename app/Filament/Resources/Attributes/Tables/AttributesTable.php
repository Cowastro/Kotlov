<?php

namespace App\Filament\Resources\Attributes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AttributesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('category.name')
                    ->label('Категория')
                    ->sortable()
                    ->badge(),

                TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->color(fn($state) => match($state) {
                        'select' => 'info',
                        'value'  => 'success',
                        'check'  => 'warning',
                        default  => 'gray',
                    })
                    ->formatStateUsing(fn($state) => match($state) {
                        'select' => 'Выбор',
                        'value'  => 'Значение',
                        'check'  => 'Да/Нет',
                        default  => $state,
                    }),

                TextColumn::make('suffix')
                    ->label('Ед. изм.')
                    ->placeholder('—'),

                TextColumn::make('options_count')
                    ->label('Вариантов')
                    ->counts('options')
                    ->sortable(),

                IconColumn::make('in_product')
                    ->label('Товар')
                    ->boolean(),

                IconColumn::make('in_filter')
                    ->label('Фильтр')
                    ->boolean(),

                IconColumn::make('in_brief')
                    ->label('Карточка')
                    ->boolean(),

                IconColumn::make('is_comparable')
                    ->label('Сравнение')
                    ->boolean(),

                TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Категория')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('type')
                    ->label('Тип')
                    ->options([
                        'value'  => 'Значение',
                        'select' => 'Выбор',
                        'check'  => 'Да/Нет',
                    ]),

                TernaryFilter::make('in_filter')
                    ->label('В фильтрах'),

                TernaryFilter::make('in_product')
                    ->label('На странице товара'),
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
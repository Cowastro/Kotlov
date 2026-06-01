<?php

namespace App\Filament\Resources\Banners\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class BannersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('Изображение'),

                TextColumn::make('title')
                    ->label('Заголовок')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('position')
                    ->label('Позиция')
                    ->badge()
                    ->formatStateUsing(fn($state) => match($state) {
                        'hero'    => 'Главный слайдер',
                        'sidebar' => 'Сайдбар',
                        'promo'   => 'Промо блок',
                        default   => $state,
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
                SelectFilter::make('position')
                    ->label('Позиция')
                    ->options([
                        'hero'    => 'Главный слайдер',
                        'sidebar' => 'Сайдбар',
                        'promo'   => 'Промо блок',
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
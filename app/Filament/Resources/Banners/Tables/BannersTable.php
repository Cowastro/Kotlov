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
                    ->color(fn($state) => match($state) {
                        'hero'          => 'primary',
                        'promo_kotly'   => 'warning',
                        'promo_nasosy'  => 'info',
                        'promo_kaminy'  => 'danger',
                        'promo_akcii'   => 'success',
                        'partners'      => 'gray',
                        default         => 'gray',
                    })
                    ->formatStateUsing(fn($state) => match($state) {
                        'hero'          => 'Hero слайдер',
                        'promo_kotly'   => 'Промо: Котлы',
                        'promo_nasosy'  => 'Промо: Насосы',
                        'promo_kaminy'  => 'Промо: Камины',
                        'promo_akcii'   => 'Промо: Акции',
                        'partners'      => 'Партнёры',
                        default         => $state,
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
                        'hero'         => 'Hero слайдер',
                        'promo_kotly'  => 'Промо: Котлы',
                        'promo_nasosy' => 'Промо: Насосы',
                        'promo_kaminy' => 'Промо: Камины',
                        'promo_akcii'  => 'Промо: Акции',
                        'partners'     => 'Партнёры',
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
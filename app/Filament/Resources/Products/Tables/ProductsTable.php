<?php

namespace App\Filament\Resources\Products\Tables;

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

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('images')
                    ->label('Фото')
                    ->getStateUsing(fn($record) => $record->images[0] ?? null)
                    ->circular(),

                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(40),

                TextColumn::make('sku')
                    ->label('Артикул')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('category.name')
                    ->label('Категория')
                    ->sortable()
                    ->badge(),

                TextColumn::make('brand.name')
                    ->label('Бренд')
                    ->sortable(),

                TextColumn::make('price')
                    ->label('Цена')
                    ->sortable()
                    ->formatStateUsing(fn($state) => number_format($state, 2) . ' BYN'),

                TextColumn::make('price_old')
                    ->label('Старая цена')
                    ->sortable()
                    ->formatStateUsing(fn($state) => $state ? number_format($state, 2) . ' BYN' : '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('in_stock')
                    ->label('Наличие')
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),

                IconColumn::make('is_featured')
                    ->label('Хит')
                    ->boolean(),

                IconColumn::make('is_new')
                    ->label('Новинка')
                    ->boolean(),

                IconColumn::make('is_sale')
                    ->label('Акция')
                    ->boolean(),

                TextColumn::make('rating')
                    ->label('Рейтинг')
                    ->sortable()
                    ->formatStateUsing(fn($state) => $state > 0 ? '★ ' . $state : '—'),

                TextColumn::make('views_count')
                    ->label('Просмотры')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Обновлён')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Активность'),

                TernaryFilter::make('in_stock')
                    ->label('Наличие'),

                TernaryFilter::make('is_featured')
                    ->label('Хит продаж'),

                TernaryFilter::make('is_new')
                    ->label('Новинки'),

                TernaryFilter::make('is_sale')
                    ->label('Акции'),

                SelectFilter::make('category_id')
                    ->label('Категория')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('brand_id')
                    ->label('Бренд')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),
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
<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\BulkAction;
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
use Illuminate\Database\Eloquent\Collection;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('images')
                    ->label('Фото')
                    ->getStateUsing(fn($record) => $record->imageUrl(0))
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
                    BulkAction::make('activate')
                        ->label('Активировать')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn(Collection $records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('deactivate')
                        ->label('Деактивировать')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->action(fn(Collection $records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('set_in_stock')
                        ->label('В наличии')
                        ->icon('heroicon-o-archive-box')
                        ->color('success')
                        ->action(fn(Collection $records) => $records->each->update(['in_stock' => true]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('set_out_of_stock')
                        ->label('Нет в наличии')
                        ->icon('heroicon-o-archive-box-x-mark')
                        ->color('danger')
                        ->action(fn(Collection $records) => $records->each->update(['in_stock' => false]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('mark_featured')
                        ->label('Отметить хитом')
                        ->icon('heroicon-o-fire')
                        ->color('warning')
                        ->action(fn(Collection $records) => $records->each->update(['is_featured' => true]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('unmark_featured')
                        ->label('Снять хит')
                        ->icon('heroicon-o-fire')
                        ->color('gray')
                        ->action(fn(Collection $records) => $records->each->update(['is_featured' => false]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('mark_new')
                        ->label('Отметить новинкой')
                        ->icon('heroicon-o-sparkles')
                        ->color('info')
                        ->action(fn(Collection $records) => $records->each->update(['is_new' => true]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('unmark_new')
                        ->label('Снять новинку')
                        ->icon('heroicon-o-sparkles')
                        ->color('gray')
                        ->action(fn(Collection $records) => $records->each->update(['is_new' => false]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('mark_sale')
                        ->label('Отметить акцией')
                        ->icon('heroicon-o-tag')
                        ->color('danger')
                        ->action(fn(Collection $records) => $records->each->update(['is_sale' => true]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('unmark_sale')
                        ->label('Снять акцию')
                        ->icon('heroicon-o-tag')
                        ->color('gray')
                        ->action(fn(Collection $records) => $records->each->update(['is_sale' => false]))
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
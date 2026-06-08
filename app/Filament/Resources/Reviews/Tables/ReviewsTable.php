<?php

namespace App\Filament\Resources\Reviews\Tables;

use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->with('reviewable'))
            ->columns([
                // Фото товара
                ImageColumn::make('product_image')
                    ->label('Фото')
                    ->getStateUsing(function ($record) {
                        if ($record->reviewable_type !== 'App\Models\Product') return null;
                        $product = $record->reviewable;
                        if (!$product) return null;
                        return url($product->imageUrl(0));
                    })
                    ->circular()
                    ->defaultImageUrl(url('/img/products/product-placeholder.jpg')),

                // Название товара
                TextColumn::make('product_name')
                    ->label('Товар')
                    ->getStateUsing(function ($record) {
                        if ($record->reviewable_type !== 'App\Models\Product') {
                            return match($record->reviewable_type) {
                                'App\Models\InstallerProfile' => 'Монтажник',
                                default => '—',
                            };
                        }
                        $product = $record->reviewable;
                        return $product?->name ?? 'ID: ' . $record->reviewable_id;
                    })
                    ->limit(40)
                    ->searchable(false)
                    ->description(function ($record) {
                        if ($record->reviewable_type !== 'App\Models\Product') return null;
                        return $record->reviewable?->sku;
                    }),

                // Автор + контакт
                TextColumn::make('author_name')
                    ->label('Автор')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn($record) => $record->author_phone ?: $record->author_email),

                TextColumn::make('rating')
                    ->label('Рейтинг')
                    ->sortable()
                    ->formatStateUsing(fn($state) => str_repeat('★', (int)$state)),

                TextColumn::make('text')
                    ->label('Отзыв')
                    ->limit(60),

                IconColumn::make('is_approved')
                    ->label('Одобрен')
                    ->boolean(),

                IconColumn::make('has_reply')
                    ->label('Ответ')
                    ->boolean()
                    ->getStateUsing(fn($record) => !empty($record->reply)),

                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('reviewable_type')
                    ->label('Тип')
                    ->options([
                        'App\Models\Product'           => 'Товары',
                        'App\Models\InstallerProfile'  => 'Монтажники',
                    ]),

                TernaryFilter::make('is_approved')
                    ->label('Модерация'),

                SelectFilter::make('rating')
                    ->label('Рейтинг')
                    ->options([
                        1 => '★ 1',
                        2 => '★★ 2',
                        3 => '★★★ 3',
                        4 => '★★★★ 4',
                        5 => '★★★★★ 5',
                    ]),
            ])
            ->recordActions([
                Action::make('view_on_site')
                    ->label('На сайте')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(function ($record) {
                        if ($record->reviewable_type !== 'App\Models\Product') return null;
                        $product = $record->reviewable;
                        if (!$product) return null;
                        $product->load('category');
                        if (!$product->category) return null;
                        return url('/' . $product->category->slug . '/' . $product->slug);
                    })
                    ->openUrlInNewTab()
                    ->visible(fn($record) => $record->reviewable_type === 'App\Models\Product'),

                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve')
                        ->label('Одобрить')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function (Collection $records) {
                            $records->each(fn($r) => $r->update(['is_approved' => true]));
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('reject')
                        ->label('Отклонить')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->action(function (Collection $records) {
                            $records->each(fn($r) => $r->update(['is_approved' => false]));
                        })
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

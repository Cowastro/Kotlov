<?php

namespace App\Filament\Resources\Reviews\Tables;

use App\Models\Product;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class ReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Фото товара
                ImageColumn::make('product_image')
                    ->label('Фото')
                    ->getStateUsing(function ($record) {
                        if ($record->reviewable_type !== 'App\Models\Product') return null;
                        $product = Product::find($record->reviewable_id);
                        return $product ? url($product->imageUrl(0)) : null;
                    })
                    ->circular()
                    ->defaultImageUrl(url('/img/products/product-placeholder.jpg')),

                // Название товара со ссылкой на сайт
                TextColumn::make('product_name')
                    ->label('Товар')
                    ->getStateUsing(function ($record) {
                        if ($record->reviewable_type !== 'App\Models\Product') return '—';
                        $product = Product::find($record->reviewable_id);
                        return $product?->name ?? 'ID: ' . $record->reviewable_id;
                    })
                    ->limit(40)
                    ->searchable(false)
                    ->description(function ($record) {
                        if ($record->reviewable_type !== 'App\Models\Product') return null;
                        $product = Product::find($record->reviewable_id);
                        return $product ? $product->sku : null;
                    }),

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

                IconColumn::make('reply')
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
                TernaryFilter::make('is_approved')
                    ->label('Модерация'),

                TernaryFilter::make('reviewable_type')
                    ->label('Только товары')
                    ->queries(
                        true: fn($q) => $q->where('reviewable_type', 'App\Models\Product'),
                        false: fn($q) => $q->where('reviewable_type', '!=', 'App\Models\Product'),
                        blank: fn($q) => $q,
                    ),

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
                        $product = Product::find($record->reviewable_id);
                        if (!$product?->category) return null;
                        return url('/' . $product->category->slug . '/' . $product->slug . '');
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

<?php

namespace App\Filament\Resources\BlogPosts\Tables;

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

class BlogPostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover_image')
                    ->label('Обложка'),

                TextColumn::make('title')
                    ->label('Заголовок')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(50),

                TextColumn::make('category.name')
                    ->label('Категория')
                    ->badge()
                    ->sortable(),

                TextColumn::make('author.name')
                    ->label('Автор')
                    ->sortable(),

                TextColumn::make('city.name')->label('Регион')->badge()->placeholder('Общая статья')->sortable(),

                IconColumn::make('is_published')
                    ->label('Опубликовано')
                    ->boolean(),

                TextColumn::make('published_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y')
                    ->sortable(),

                TextColumn::make('views_count')
                    ->label('Просмотры')
                    ->sortable(),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                TernaryFilter::make('is_published')
                    ->label('Опубликовано'),

                SelectFilter::make('category_id')
                    ->label('Категория')
                    ->relationship('category', 'name'),
                SelectFilter::make('city_id')->label('Регион')->relationship('city', 'name'),
            ])
            ->recordActions([
                Action::make('public_page')->label('На сайте')->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (\App\Models\BlogPost $record) => $record->publicUrl())->openUrlInNewTab()
                    ->visible(fn (\App\Models\BlogPost $record) => $record->is_published && $record->published_at?->isPast()),
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

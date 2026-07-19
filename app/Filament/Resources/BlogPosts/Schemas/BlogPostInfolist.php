<?php

namespace App\Filament\Resources\BlogPosts\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class BlogPostInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('category.name')
                    ->label('Категория')
                    ->placeholder('-'),
                TextEntry::make('author.name')
                    ->label('Автор')
                    ->placeholder('-'),
                TextEntry::make('title')
                    ->label('Заголовок'),
                TextEntry::make('slug')
                    ->label('URL (slug)'),
                TextEntry::make('excerpt')
                    ->label('Анонс')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('content')
                    ->label('Содержание')
                    ->html()
                    ->columnSpanFull(),
                ImageEntry::make('cover_image')
                    ->label('Обложка')
                    ->placeholder('-'),
                IconEntry::make('is_published')
                    ->label('Опубликовано')
                    ->boolean(),
                TextEntry::make('published_at')
                    ->label('Дата публикации')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('views_count')
                    ->label('Просмотры')
                    ->numeric(),
                TextEntry::make('meta_title')
                    ->label('Meta Title')
                    ->placeholder('-'),
                TextEntry::make('meta_description')
                    ->label('Meta Description')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->label('Создано')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->label('Обновлено')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}

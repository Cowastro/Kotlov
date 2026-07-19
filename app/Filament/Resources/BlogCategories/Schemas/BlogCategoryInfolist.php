<?php

namespace App\Filament\Resources\BlogCategories\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class BlogCategoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label('Название'),
                TextEntry::make('slug')
                    ->label('URL (slug)'),
                TextEntry::make('sort_order')
                    ->label('Сортировка')
                    ->numeric(),
                IconEntry::make('is_active')
                    ->label('Активна')
                    ->boolean(),
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

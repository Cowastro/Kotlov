<?php

namespace App\Filament\Resources\Banners\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class BannerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('title')
                    ->placeholder('-'),
                TextEntry::make('subtitle')
                    ->placeholder('-'),
                ImageEntry::make('image'),
                TextEntry::make('link')
                    ->placeholder('-'),
                TextEntry::make('button_text')
                    ->placeholder('-'),
                TextEntry::make('position'),
                TextEntry::make('sort_order')
                    ->numeric(),
                IconEntry::make('is_active')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}

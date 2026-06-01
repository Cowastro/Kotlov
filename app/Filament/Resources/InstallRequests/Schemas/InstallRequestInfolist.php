<?php

namespace App\Filament\Resources\InstallRequests\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class InstallRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('client.name')
                    ->label('Client')
                    ->placeholder('-'),
                TextEntry::make('installer.name')
                    ->label('Installer')
                    ->placeholder('-'),
                TextEntry::make('product.name')
                    ->label('Product')
                    ->placeholder('-'),
                TextEntry::make('customer_name'),
                TextEntry::make('customer_phone'),
                TextEntry::make('description')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('region')
                    ->placeholder('-'),
                TextEntry::make('city')
                    ->placeholder('-'),
                TextEntry::make('preferred_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('price_agreed')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}

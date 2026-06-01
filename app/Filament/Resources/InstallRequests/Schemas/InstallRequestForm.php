<?php

namespace App\Filament\Resources\InstallRequests\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class InstallRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('client_id')
                    ->relationship('client', 'name'),
                Select::make('installer_id')
                    ->relationship('installer', 'name'),
                Select::make('product_id')
                    ->relationship('product', 'name'),
                TextInput::make('customer_name')
                    ->required(),
                TextInput::make('customer_phone')
                    ->tel()
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('region'),
                TextInput::make('city'),
                DatePicker::make('preferred_date'),
                Select::make('status')
                    ->options([
            'new' => 'New',
            'accepted' => 'Accepted',
            'in_progress' => 'In progress',
            'done' => 'Done',
            'cancelled' => 'Cancelled',
        ])
                    ->default('new')
                    ->required(),
                TextInput::make('price_agreed')
                    ->numeric(),
            ]);
    }
}

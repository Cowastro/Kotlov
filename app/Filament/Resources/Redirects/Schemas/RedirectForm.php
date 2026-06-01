<?php

namespace App\Filament\Resources\Redirects\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RedirectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('from_url')
                    ->url()
                    ->required(),
                TextInput::make('to_url')
                    ->url()
                    ->required(),
                TextInput::make('status_code')
                    ->required()
                    ->numeric()
                    ->default(301),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}

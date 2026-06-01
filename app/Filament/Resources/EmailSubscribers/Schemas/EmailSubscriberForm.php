<?php

namespace App\Filament\Resources\EmailSubscribers\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class EmailSubscriberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('name'),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('token'),
                DateTimePicker::make('confirmed_at'),
            ]);
    }
}

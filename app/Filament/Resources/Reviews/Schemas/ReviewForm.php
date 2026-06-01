<?php

namespace App\Filament\Resources\Reviews\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ReviewForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name'),
                TextInput::make('author_name')
                    ->required(),
                TextInput::make('author_email')
                    ->email(),
                TextInput::make('reviewable_type')
                    ->required(),
                TextInput::make('reviewable_id')
                    ->required()
                    ->numeric(),
                TextInput::make('rating')
                    ->required()
                    ->numeric(),
                Textarea::make('text')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('photos'),
                Toggle::make('is_approved')
                    ->required(),
            ]);
    }
}

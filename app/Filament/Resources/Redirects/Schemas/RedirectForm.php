<?php

namespace App\Filament\Resources\Redirects\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RedirectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Редирект')
                    ->columns(2)
                    ->schema([
                        TextInput::make('from_url')
                            ->label('Откуда (старый URL)')
                            ->required()
                            ->placeholder('/old-page')
                            ->unique(ignoreRecord: true),

                        TextInput::make('to_url')
                            ->label('Куда (новый URL)')
                            ->required()
                            ->placeholder('/new-page'),

                        Select::make('status_code')
                            ->label('Тип редиректа')
                            ->options([
                                301 => '301 — Постоянный',
                                302 => '302 — Временный',
                            ])
                            ->default(301)
                            ->required(),

                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true),
                    ]),
            ]);
    }
}
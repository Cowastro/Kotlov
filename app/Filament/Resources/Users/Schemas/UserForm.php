<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основное')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Имя')
                            ->required(),

                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true),

                        TextInput::make('phone')
                            ->label('Телефон')
                            ->tel(),

                        TextInput::make('telegram_username')
                            ->label('Telegram username')
                            ->placeholder('username (без @)')
                            ->prefix('@')
                            ->helperText('Заполните для привязки кнопки "Взять заказ" в Telegram к этому аккаунту')
                            ->unique(ignoreRecord: true),

                        Select::make('role')
                            ->label('Роль')
                            ->options([
                                'admin'     => 'Администратор',
                                'supplier'  => 'Поставщик',
                                'installer' => 'Монтажник',
                                'client'    => 'Клиент',
                            ])
                            ->default('client')
                            ->required(),

                        FileUpload::make('avatar')
                            ->label('Аватар')
                            ->image()
                            ->directory('avatars')
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true),
                    ]),

                Section::make('Пароль')
                    ->schema([
                        TextInput::make('password')
                            ->label('Пароль')
                            ->password()
                            ->revealable()
                            ->required(fn($record) => $record === null) // обязателен только при создании
                            ->dehydrateStateUsing(fn($state) => filled($state) ? bcrypt($state) : null)
                            ->dehydrated(fn($state) => filled($state))
                            ->minLength(8),

                        TextInput::make('password_confirmation')
                            ->label('Подтверждение пароля')
                            ->password()
                            ->revealable()
                            ->same('password')
                            ->required(fn($record) => $record === null)
                            ->dehydrated(false),
                    ]),
            ]);
    }
}
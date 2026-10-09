<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use App\Enums\ClientType;

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

                Section::make('B2B-доступ')
                    ->description('Оптовые цены и складские остатки видны только одобренным партнёрам после входа.')
                    ->columns(2)
                    ->schema([
                        Select::make('client_type')
                            ->label('Тип клиента')
                            ->options(collect(ClientType::cases())->mapWithKeys(
                                fn (ClientType $type): array => [$type->value => $type->label()]
                            )->all())
                            ->default(ClientType::Retail->value)
                            ->required(),

                        Toggle::make('b2b_approved')
                            ->label('B2B-доступ одобрен')
                            ->helperText('После включения пользователь увидит оптовые цены и остатки 1С.'),

                        TextInput::make('company_name')
                            ->label('Компания')
                            ->maxLength(255),

                        TextInput::make('company_inn')
                            ->label('УНП / ИНН')
                            ->maxLength(50),
                    ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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
                        TextInput::make('first_name')
                            ->label('Имя')
                            ->autocomplete('given-name')
                            ->maxLength(100)
                            ->required(),

                        TextInput::make('last_name')
                            ->label('Фамилия')
                            ->autocomplete('family-name')
                            ->maxLength(100)
                            ->required(),

                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->autocomplete('off')
                            ->required()
                            ->unique(ignoreRecord: true),

                        Select::make('role')
                            ->label('Роль')
                            ->options(User::ROLES)
                            ->default('client')
                            ->required(),

                        Toggle::make('is_crm_user')
                            ->label('Сотрудник CRM')
                            ->helperText('Включает служебную карточку сотрудника. Доступ в админку определяется ролью.')
                            ->default(false)
                            ->live(),

                        FileUpload::make('avatar')
                            ->label('Аватар')
                            ->image()
                            ->directory('avatars')
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true),
                    ]),

                Section::make('Карточка сотрудника CRM')
                    ->description('Служебные данные для заказов, телефонии и персональной подписи писем из общего ящика.')
                    ->visible(fn (Get $get): bool => (bool) $get('is_crm_user'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('job_title')
                            ->label('Должность')
                            ->placeholder('Например: менеджер по продажам')
                            ->maxLength(255),

                        TextInput::make('department')
                            ->label('Отдел')
                            ->placeholder('Например: отдел продаж')
                            ->maxLength(255),

                        TextInput::make('phone')
                            ->label('Рабочий телефон')
                            ->tel()
                            ->autocomplete('off')
                            ->helperText('Указывается в персональной подписи исходящих писем.'),

                        Toggle::make('configure_telegram')
                            ->label('Привязать Telegram')
                            ->helperText('Включите только если сотруднику действительно нужен Telegram для заказов.')
                            ->default(fn ($record): bool => filled($record?->telegram_username))
                            ->live()
                            ->dehydrated(false),

                        TextInput::make('telegram_username')
                            ->label('Telegram username')
                            ->placeholder('username (без @)')
                            ->prefix('@')
                            ->autocomplete('off')
                            ->extraInputAttributes([
                                'data-1p-ignore' => 'true',
                                'data-lpignore' => 'true',
                            ])
                            ->visible(fn (Get $get): bool => (bool) $get('configure_telegram'))
                            ->helperText('Необязательно. Нужен только для назначения заказов через Telegram.')
                            ->unique(ignoreRecord: true),
                    ]),

                Section::make('Пароль')
                    ->description('При редактировании оставьте поля пустыми, если пароль менять не нужно.')
                    ->schema([
                        Toggle::make('change_password')
                            ->label('Изменить пароль')
                            ->helperText('Поля нового пароля появятся только после включения.')
                            ->default(fn ($record): bool => $record === null)
                            ->live()
                            ->dehydrated(false),

                        TextInput::make('password')
                            ->label('Пароль')
                            ->password()
                            ->revealable()
                            ->autocomplete('off')
                            ->extraInputAttributes([
                                'data-1p-ignore' => 'true',
                                'data-lpignore' => 'true',
                            ])
                            ->afterStateHydrated(fn (TextInput $component): TextInput => $component->state(null))
                            ->visible(fn (Get $get): bool => (bool) $get('change_password'))
                            ->required(fn (Get $get): bool => (bool) $get('change_password'))
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->minLength(8),

                        TextInput::make('password_confirmation')
                            ->label('Подтверждение пароля')
                            ->password()
                            ->revealable()
                            ->autocomplete('off')
                            ->extraInputAttributes([
                                'data-1p-ignore' => 'true',
                                'data-lpignore' => 'true',
                            ])
                            ->afterStateHydrated(fn (TextInput $component): TextInput => $component->state(null))
                            ->visible(fn (Get $get): bool => (bool) $get('change_password'))
                            ->same('password')
                            ->required(fn (Get $get): bool => (bool) $get('change_password'))
                            ->dehydrated(false),
                    ]),

            ]);
    }
}

<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EditProfile extends BaseEditProfile
{
    public static function getLabel(): string
    {
        return 'Мой профиль';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Данные сотрудника')
                ->description('Эти данные используются в персональной подписи писем, отправленных через общий ящик компании.')
                ->columns(2)
                ->schema([
                    $this->getNameFormComponent()->label('Имя и фамилия'),
                    $this->getEmailFormComponent()->label('Email для входа'),
                    TextInput::make('job_title')
                        ->label('Должность')
                        ->placeholder('Например: менеджер по продажам')
                        ->maxLength(255),
                    TextInput::make('phone')
                        ->label('Рабочий телефон')
                        ->tel()
                        ->maxLength(255),
                ]),
            Section::make('Безопасность')
                ->description('Оставьте пароль пустым, если менять его не нужно.')
                ->schema([
                    $this->getPasswordFormComponent(),
                    $this->getPasswordConfirmationFormComponent(),
                    $this->getCurrentPasswordFormComponent(),
                ]),
        ]);
    }
}

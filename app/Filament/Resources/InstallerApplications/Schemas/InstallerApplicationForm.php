<?php

namespace App\Filament\Resources\InstallerApplications\Schemas;

use App\Models\InstallerApplication;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class InstallerApplicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('contact_name')->label('Имя')->required(),
            TextInput::make('phone')->label('Телефон')->required(),
            TextInput::make('email')->label('Email')->email(),
            TextInput::make('city')->label('Город'),
            TextInput::make('company_name')->label('Компания'),
            TextInput::make('experience_years')->label('Опыт, лет')->numeric(),
            Select::make('specializations')
                ->label('Специализации')
                ->multiple()
                ->options(InstallerApplication::$specializationLabels),
            Textarea::make('message')->label('Сообщение')->rows(3),
            Select::make('status')
                ->label('Статус')
                ->options(InstallerApplication::$statuses)
                ->required(),
            Textarea::make('admin_notes')->label('Заметки админа')->rows(2),
        ]);
    }
}

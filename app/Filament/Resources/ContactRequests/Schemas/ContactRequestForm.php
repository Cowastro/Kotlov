<?php

namespace App\Filament\Resources\ContactRequests\Schemas;

use App\Models\ContactRequest;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ContactRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Имя')->required(),
            TextInput::make('phone')->label('Телефон')->required(),
            TextInput::make('email')->label('Email')->email(),
            Textarea::make('message')->label('Сообщение')->rows(4)->required(),
            Select::make('status')->label('Статус')->options(ContactRequest::$statuses)->required(),
            Textarea::make('admin_notes')->label('Заметки')->rows(2),
        ]);
    }
}

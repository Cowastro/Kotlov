<?php

namespace App\Filament\Resources\SupplierApplications\Schemas;

use App\Models\SupplierApplication;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SupplierApplicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('company_name')->label('Компания')->required(),
            TextInput::make('contact_name')->label('Контактное лицо')->required(),
            TextInput::make('phone')->label('Телефон')->required(),
            TextInput::make('email')->label('Email')->email(),
            TextInput::make('website')->label('Сайт')->url(),
            Select::make('product_categories')
                ->label('Категории товаров')
                ->multiple()
                ->options(SupplierApplication::$categoryLabels),
            Textarea::make('message')->label('Сообщение')->rows(3),
            Select::make('status')
                ->label('Статус')
                ->options(SupplierApplication::$statuses)
                ->required(),
            Textarea::make('admin_notes')->label('Заметки админа')->rows(2),
        ]);
    }
}

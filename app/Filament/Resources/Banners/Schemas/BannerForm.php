<?php

namespace App\Filament\Resources\Banners\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BannerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Контент')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('Заголовок'),

                        TextInput::make('subtitle')
                            ->label('Подзаголовок'),

                        FileUpload::make('image')
                            ->label('Изображение')
                            ->image()
                            ->required()
                            ->directory('banners')
                            ->columnSpanFull(),

                        TextInput::make('link')
                            ->label('Ссылка')
                            ->url(),

                        TextInput::make('button_text')
                            ->label('Текст кнопки'),
                    ]),

                Section::make('Настройки')
                    ->columns(3)
                    ->schema([
                        Select::make('position')
                            ->label('Позиция')
                            ->options([
                                'hero'    => 'Главный слайдер',
                                'sidebar' => 'Сайдбар',
                                'promo'   => 'Промо блок',
                            ])
                            ->default('hero')
                            ->required(),

                        TextInput::make('sort_order')
                            ->label('Сортировка')
                            ->numeric()
                            ->default(0),

                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true),
                    ]),
            ]);
    }
}
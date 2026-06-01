<?php

namespace App\Filament\Resources\Faqs\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FaqForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Вопрос и ответ')
                    ->schema([
                        TextInput::make('question')
                            ->label('Вопрос')
                            ->required(),

                        RichEditor::make('answer')
                            ->label('Ответ')
                            ->required()
                            ->toolbarButtons([
                                'bold', 'italic', 'bulletList',
                                'orderedList', 'link',
                            ]),
                    ]),

                Section::make('Настройки')
                    ->columns(3)
                    ->schema([
                        Select::make('category')
                            ->label('Категория')
                            ->options([
                                'delivery'  => 'Доставка',
                                'payment'   => 'Оплата',
                                'products'  => 'Товары',
                                'install'   => 'Монтаж',
                                'warranty'  => 'Гарантия',
                                'other'     => 'Прочее',
                            ]),

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
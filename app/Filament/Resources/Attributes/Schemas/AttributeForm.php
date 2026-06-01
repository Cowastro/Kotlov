<?php

namespace App\Filament\Resources\Attributes\Schemas;

use App\Models\Category;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AttributeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Основное')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Название атрибута')
                        ->required()
                        ->placeholder('Мощность'),

                    Select::make('type')
                        ->label('Тип')
                        ->options([
                            'value'  => 'Значение (число/текст)',
                            'select' => 'Выбор из вариантов',
                            'check'  => 'Да / Нет',
                        ])
                        ->default('value')
                        ->required(),

                    TextInput::make('suffix')
                        ->label('Единица измерения')
                        ->placeholder('кВт, л, °C, %'),

                    TextInput::make('sort_order')
                        ->label('Сортировка')
                        ->numeric()
                        ->default(0),

                    Select::make('category_id')
                        ->label('Категория')
                        ->options(
                            Category::where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                        )
                        ->searchable()
                        ->required()
                        ->columnSpanFull(),
                ]),

            Section::make('Отображение')
                ->columns(2)
                ->description('Где показывать этот атрибут')
                ->schema([
                    Toggle::make('in_product')
                        ->label('На странице товара')
                        ->default(true),

                    Toggle::make('in_filter')
                        ->label('В фильтрах каталога')
                        ->default(false),

                    Toggle::make('in_brief')
                        ->label('В карточке товара')
                        ->default(false),

                    Toggle::make('in_sort')
                        ->label('Для сортировки')
                        ->default(false),

                    Toggle::make('is_comparable')
                        ->label('При сравнении товаров')
                        ->default(false),
                ]),
        ]);
    }
}
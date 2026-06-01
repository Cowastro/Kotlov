<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основное')
                    ->columns(2)
                    ->schema([
                        Select::make('parent_id')
                            ->label('Родительская категория')
                            ->options(Category::where('is_active', true)->pluck('name', 'id'))
                            ->placeholder('— Корневая категория —')
                            ->default(0),

                        Select::make('type')
                            ->label('Тип')
                            ->options(['main' => 'Основная', 'child' => 'Дочерняя'])
                            ->default('main')
                            ->required(),

                        TextInput::make('name')
                            ->label('Название')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, $set) {
                                $set('slug', Str::slug($state));
                            }),

                        TextInput::make('slug')
                            ->label('URL (slug)')
                            ->required()
                            ->unique(ignoreRecord: true),

                        TextInput::make('h1')
                            ->label('Заголовок H1')
                            ->columnSpanFull(),

                        Textarea::make('content')
                            ->label('SEO текст')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),

                Section::make('Отображение')
                    ->columns(2)
                    ->schema([
                        FileUpload::make('image')
                            ->label('Изображение категории')
                            ->image()
                            ->directory('categories')
                            ->columnSpanFull(),

                        TextInput::make('icon')
                            ->label('Иконка'),

                        TextInput::make('sort_order')
                            ->label('Сортировка')
                            ->numeric()
                            ->default(0),

                        Toggle::make('is_active')
                            ->label('Активна')
                            ->default(true),
                    ]),

                Section::make('SEO')
                    ->columns(1)
                    ->collapsed()
                    ->schema([
                        TextInput::make('meta_title')
                            ->label('Meta Title'),

                        Textarea::make('meta_keywords')
                            ->label('Meta Keywords')
                            ->rows(2),

                        Textarea::make('meta_description')
                            ->label('Meta Description')
                            ->rows(3),
                    ]),
            ]);
    }
}
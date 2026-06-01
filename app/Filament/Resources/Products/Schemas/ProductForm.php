<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основное')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Название товара')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, $set) {
                                $set('slug', Str::slug($state));
                            })
                            ->columnSpanFull(),

                        TextInput::make('slug')
                            ->label('URL (slug)')
                            ->required()
                            ->unique(ignoreRecord: true),

                        TextInput::make('h1')
                            ->label('Заголовок H1'),

                        TextInput::make('sku')
                            ->label('Артикул (SKU)'),

                        Select::make('category_id')
                            ->label('Категория')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('brand_id')
                            ->label('Бренд')
                            ->relationship('brand', 'name')
                            ->searchable()
                            ->preload(),

                        Select::make('supplier_id')
                            ->label('Поставщик')
                            ->relationship('supplier', 'name')
                            ->searchable(),
                    ]),

                Section::make('Цены и наличие')
                    ->columns(3)
                    ->schema([
                        TextInput::make('price')
                            ->label('Цена')
                            ->required()
                            ->numeric()
                            ->prefix('BYN')
                            ->default(0),

                        TextInput::make('price_old')
                            ->label('Старая цена')
                            ->numeric()
                            ->prefix('BYN'),

                        TextInput::make('currency')
                            ->label('Валюта')
                            ->default('BYN')
                            ->required(),

                        Toggle::make('in_stock')
                            ->label('В наличии')
                            ->default(true),

                        TextInput::make('stock_qty')
                            ->label('Количество на складе')
                            ->numeric(),

                        TextInput::make('unit')
                            ->label('Единица измерения')
                            ->default('шт'),
                    ]),

                Section::make('Описание')
                    ->schema([
                        Textarea::make('short_description')
                            ->label('Краткое описание')
                            ->rows(3),

                        RichEditor::make('content')
                            ->label('Полное описание')
                            ->toolbarButtons([
                                'bold', 'italic', 'underline',
                                'bulletList', 'orderedList',
                                'h2', 'h3', 'link', 'blockquote',
                            ]),
                    ]),

                Section::make('Фотографии')
                    ->schema([
                        FileUpload::make('images')
                            ->label('Фотографии товара')
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->directory('products')
                            ->maxFiles(10),
                    ]),

                Section::make('Характеристики')
                    ->schema([
                        Repeater::make('specs')
                            ->label('Технические характеристики')
                            ->schema([
                                TextInput::make('key')
                                    ->label('Название')
                                    ->required(),
                                TextInput::make('value')
                                    ->label('Значение')
                                    ->required(),
                                TextInput::make('unit')
                                    ->label('Единица (кВт, м², шт)'),
                            ])
                            ->columns(3)
                            ->addActionLabel('Добавить характеристику')
                            ->collapsible(),
                    ]),

                Section::make('Дополнительно')
                    ->columns(3)
                    ->schema([
                        TextInput::make('weight')
                            ->label('Вес (кг)')
                            ->numeric(),

                        TextInput::make('warranty')
                            ->label('Гарантия'),

                        TextInput::make('video_url')
                            ->label('Ссылка на видео')
                            ->url(),
                    ]),

                Section::make('Статусы и сортировка')
                    ->columns(3)
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true),

                        Toggle::make('is_featured')
                            ->label('Хит продаж'),

                        Toggle::make('is_new')
                            ->label('Новинка'),

                        Toggle::make('is_sale')
                            ->label('Акция'),

                        TextInput::make('sort_order')
                            ->label('Сортировка')
                            ->numeric()
                            ->default(0),
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
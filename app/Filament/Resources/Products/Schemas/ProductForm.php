<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([

                // ── Левая часть: основные поля (2/3 ширины) ────────────────
                Section::make('Основное')
                    ->columnSpan(2)
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
                            ->relationship('supplier', 'name', fn($query) => $query->where('role', 'supplier'))
                            ->searchable()
                            ->columnSpanFull(),
                    ]),

                // ── Правая часть: статусы + цены (1/3 ширины) ──────────────
                Grid::make(1)
                    ->columnSpan(1)
                    ->schema([
                        Section::make('Статусы')
                            ->schema([
                                Toggle::make('is_active')
                                    ->label('Активен')
                                    ->default(true),

                                Toggle::make('in_stock')
                                    ->label('В наличии')
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

                        Section::make('Цены')
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

                                Select::make('currency')
                                    ->label('Валюта')
                                    ->options(['BYN' => 'BYN', 'USD' => 'USD', 'EUR' => 'EUR', 'RUB' => 'RUB'])
                                    ->default('BYN')
                                    ->required(),

                                TextInput::make('stock_qty')
                                    ->label('Кол-во на складе')
                                    ->numeric(),

                                TextInput::make('unit')
                                    ->label('Ед. измерения')
                                    ->default('шт'),

                                TextInput::make('warranty')
                                    ->label('Гарантия'),
                            ]),
                    ]),

                // ── Описание ─────────────────────────────────────────────────
                Section::make('Описание')
                    ->columnSpanFull()
                    ->schema([
                        RichEditor::make('short_description')
                            ->label('Краткое описание')
                            ->toolbarButtons([
                                'bold', 'italic', 'underline',
                                'bulletList', 'orderedList',
                                'link',
                                'undo', 'redo',
                            ]),

                        RichEditor::make('content')
                            ->label('Полное описание')
                            ->toolbarButtons([
                                'bold', 'italic', 'underline',
                                'bulletList', 'orderedList',
                                'h2', 'h3', 'link', 'blockquote',
                            ]),
                    ]),

                // ── Фотографии ────────────────────────────────────────────────
                Section::make('Фотографии')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('existing_images')
                            ->label('Текущие фото (удалите строку — фото удалится)')
                            ->schema([
                                Placeholder::make('img_preview')
                                    ->label('')
                                    ->content(function ($get, $record): HtmlString {
                                        $path = (string) ($get('path') ?? '');
                                        if (!$path) {
                                            return new HtmlString('<span class="text-gray-400 text-xs">нет пути</span>');
                                        }
                                        $url = self::resolveImageUrl($path, $record);
                                        return new HtmlString(
                                            '<img src="' . e($url) . '" style="max-height:72px;border-radius:4px;object-fit:cover;" '
                                            . 'onerror="this.style.opacity=0.3">'
                                        );
                                    }),

                                TextInput::make('path')
                                    ->label('Путь')
                                    ->readOnly()
                                    ->dehydrated()
                                    ->columnSpan(3),
                            ])
                            ->columns(4)
                            ->addable(false)
                            ->reorderable(true)
                            ->collapsible()
                            ->defaultItems(0),

                        FileUpload::make('images')
                            ->label('Загрузить новые фото')
                            ->helperText('Новые файлы добавятся к существующим')
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->directory('products')
                            ->maxFiles(20),
                    ]),

                // ── Характеристики ────────────────────────────────────────────
                Section::make('Характеристики')
                    ->columnSpanFull()
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
                                    ->label('Единица (кВт, м²)'),
                            ])
                            ->columns(3)
                            ->addActionLabel('Добавить характеристику')
                            ->collapsible()
                            ->defaultItems(0),
                    ]),

                // ── Дополнительно ─────────────────────────────────────────────
                Section::make('Дополнительно')
                    ->columnSpanFull()
                    ->columns(3)
                    ->collapsed()
                    ->schema([
                        TextInput::make('weight')
                            ->label('Вес (кг)')
                            ->numeric(),

                        TextInput::make('video_url')
                            ->label('Ссылка на видео')
                            ->url(),
                    ]),

                // ── SEO ───────────────────────────────────────────────────────
                Section::make('SEO')
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        TextInput::make('meta_title')
                            ->label('Meta Title')
                            ->columnSpanFull(),

                        Textarea::make('meta_keywords')
                            ->label('Meta Keywords')
                            ->rows(2)
                            ->columnSpanFull(),

                        Textarea::make('meta_description')
                            ->label('Meta Description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

            ]);
    }

    /**
     * Строит URL для предпросмотра изображения в форме.
     * Поддерживает пути из kotlov.by и локально загруженные файлы.
     */
    private static function resolveImageUrl(string $path, ?object $record = null): string
    {
        // Загружено через FileUpload → путь в storage
        if (str_starts_with($path, 'products/')) {
            return \Storage::url($path);
        }

        // product/000/000065/file.jpg
        if (str_starts_with($path, 'product/')) {
            return '/proxy-image/' . $path;
        }

        // 000/000065/file.jpg (два слеша)
        if (substr_count($path, '/') >= 2) {
            return '/proxy-image/product/' . $path;
        }

        // Просто имя файла — используем Product::imageUrl(), потому что legacy путь
        // зависит от SKU/ID и не должен собираться здесь наугад.
        if ($record && method_exists($record, 'imageUrl')) {
            $images = $record->images;
            if (is_string($images)) {
                $images = json_decode($images, true) ?: [];
            }

            $index = array_search($path, (array) $images, true);
            return $record->imageUrl($index === false ? 0 : $index);
        }

        return asset('img/products/product-placeholder.jpg');
    }
}

<?php

namespace App\Filament\Resources\Brands\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class BrandForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основное')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Название бренда')
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
                            ->label('Заголовок H1'),

                        TextInput::make('country')
                            ->label('Страна производителя'),

                        FileUpload::make('logo')
                            ->label('Логотип бренда')
                            ->image()
                            ->directory('brands')
                            ->columnSpanFull(),

                        RichEditor::make('producer')
                            ->label('О производителе')
                            ->toolbarButtons([
                                'bold', 'italic', 'underline',
                                'bulletList', 'orderedList',
                                'link', 'blockquote',
                                'undo', 'redo',
                            ])
                            ->columnSpanFull(),

                        RichEditor::make('content')
                            ->label('SEO текст')
                            ->toolbarButtons([
                                'bold', 'italic', 'underline',
                                'h2', 'h3', 'bulletList', 'orderedList',
                                'link', 'blockquote',
                                'attachFiles',
                                'undo', 'redo',
                            ])
                            ->fileAttachmentsDirectory('editor-uploads')
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsVisibility('public')
                            ->columnSpanFull(),
                    ]),

                Section::make('Настройки')
                    ->columns(3)
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true),

                        Toggle::make('is_featured')
                            ->label('Показывать на главной')
                            ->default(false),

                        TextInput::make('sort_order')
                            ->label('Сортировка')
                            ->numeric()
                            ->default(0),
                    ]),

                Section::make('SEO')
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
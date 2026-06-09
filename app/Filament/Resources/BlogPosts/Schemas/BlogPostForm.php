<?php

namespace App\Filament\Resources\BlogPosts\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class BlogPostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основное')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('Заголовок')
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

                        Select::make('category_id')
                            ->label('Категория')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload(),

                        Select::make('author_id')
                            ->label('Автор')
                            ->relationship('author', 'name')
                            ->searchable()
                            ->preload(),

                        RichEditor::make('excerpt')
                            ->label('Анонс')
                            ->toolbarButtons([
                                'bold', 'italic', 'underline',
                                'link',
                                'undo', 'redo',
                            ])
                            ->columnSpanFull(),
                    ]),

                Section::make('Контент')
                    ->schema([
                        RichEditor::make('content')
                            ->label('Содержание статьи')
                            ->required()
                            ->toolbarButtons([
                                'bold', 'italic', 'underline',
                                'bulletList', 'orderedList',
                                'h2', 'h3', 'link',
                                'blockquote', 'codeBlock',
                            ]),
                    ]),

                Section::make('Медиа')
                    ->columns(2)
                    ->schema([
                        FileUpload::make('cover_image')
                            ->label('Обложка статьи')
                            ->image()
                            ->directory('blog')
                            ->columnSpanFull(),

                        TagsInput::make('tags')
                            ->label('Теги')
                            ->placeholder('Добавить тег'),
                    ]),

                Section::make('Публикация')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_published')
                            ->label('Опубликовано')
                            ->default(false),

                        DateTimePicker::make('published_at')
                            ->label('Дата публикации')
                            ->default(now()),
                    ]),

                Section::make('SEO')
                    ->collapsed()
                    ->schema([
                        TextInput::make('meta_title')
                            ->label('Meta Title'),

                        Textarea::make('meta_description')
                            ->label('Meta Description')
                            ->rows(3),
                    ]),
            ]);
    }
}
<?php

namespace App\Filament\Resources\BlogPosts\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class BlogPostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(12)
            ->components([
                Section::make('Основное')
                    ->columnSpan(7)
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
                            ->helperText('Адрес статьи после /blog/. Например: dymohody-teplov-i-sukhov-v-belarusi')
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
                            ->helperText('Короткое описание для карточки статьи и шаринга. 1–2 предложения.')
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'link',
                                'undo',
                                'redo',
                            ])
                            ->columnSpanFull(),
                    ]),

                Grid::make(1)
                    ->columnSpan(5)
                    ->schema([
                        Section::make('Обложка / баннер статьи')
                            ->description('Главная картинка статьи. Она используется сверху статьи, в карточке блога и в Facebook/Telegram превью.')
                            ->schema([
                                Placeholder::make('cover_image_rules')
                                    ->label('Рекомендуемый размер')
                                    ->content(new HtmlString(
                                        '<div style="line-height:1.6">'
                                        . '<strong>1600 × 900 px</strong> или <strong>1920 × 1080 px</strong>, формат 16:9.<br>'
                                        . 'Важный объект держать ближе к центру, без текста на картинке, с запасом 8–10% по краям.'
                                        . '</div>'
                                    )),

                                FileUpload::make('cover_image')
                                    ->label('Заглавная картинка')
                                    ->helperText('Если оставить пустым, сайт возьмёт стандартную обложку для статьи.')
                                    ->image()
                                    ->imageEditor()
                                    ->imageEditorAspectRatios(['16:9'])
                                    ->imagePreviewHeight('220')
                                    ->disk('public')
                                    ->directory('blog')
                                    ->visibility('public')
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                    ->maxSize(4096),
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

                        Section::make('Теги')
                            ->schema([
                                TagsInput::make('tags')
                                    ->label('Теги')
                                    ->placeholder('Добавить тег')
                                    ->helperText('Например: дымоход, камин, баня, Теплов и Сухов'),
                            ]),
                    ]),

                Section::make('Контент статьи')
                    ->description('Основной текст. Для красивого шаблона можно использовать H2/H3, цитаты, списки и изображения.')
                    ->columnSpanFull()
                    ->schema([
                        RichEditor::make('content')
                            ->label('Содержание статьи')
                            ->required()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'bulletList',
                                'orderedList',
                                'h2',
                                'h3',
                                'h4',
                                'link',
                                'blockquote',
                                'codeBlock',
                                'attachFiles',
                                'undo',
                                'redo',
                            ])
                            ->fileAttachmentsDirectory('editor-uploads')
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsVisibility('public')
                            ->columnSpanFull(),
                    ]),

                Section::make('SEO')
                    ->description('Данные для Google, Facebook и Telegram. Если пусто — сайт возьмёт заголовок и анонс.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextInput::make('meta_title')
                            ->label('Meta Title'),

                        Textarea::make('meta_description')
                            ->label('Meta Description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

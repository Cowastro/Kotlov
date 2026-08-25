<?php

namespace App\Filament\Resources\BlogPosts\Schemas;

use App\Services\BlogImageOptimizer;
use Filament\Forms\Components\BaseFileUpload;
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
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

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
                            ->helperText('Адрес статьи после /blog/. Например: teplovye-nasosy-ge-r290-vysokotemperaturnye')
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
                                    ->helperText('Если оставить пустым, сайт возьмёт стандартную обложку для статьи. Загруженное фото автоматически обрежется до 1600×900 и сожмётся — крутить/обрезать вручную не обязательно.')
                                    ->image()
                                    ->imageEditor()
                                    ->imageEditorAspectRatios(['16:9'])
                                    ->imagePreviewHeight('220')
                                    ->disk('public')
                                    ->directory('blog')
                                    ->visibility('public')
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                    ->maxSize(4096)
                                    // Root cause this fixes: the site renders this image in a fixed
                                    // 1600x900 (hero) / 900x675 (listing card) box via CSS — without
                                    // this, a raw phone photo (e.g. 1536x2048, ~1MB) ships as-is and
                                    // is only cropped client-side, so every visitor downloads the full
                                    // multi-megapixel original. Store normally, then optimize in place.
                                    ->saveUploadedFileUsing(function (BaseFileUpload $component, TemporaryUploadedFile $file): ?string {
                                        $path = $component->saveUploadedFile($file);

                                        if ($path === null) {
                                            return null;
                                        }

                                        $disk = $component->getDisk();
                                        $optimized = app(BlogImageOptimizer::class)->optimizeCover($disk->path($path));

                                        // Extension may have changed (e.g. .png -> .jpg forced for
                                        // covers) — reflect that in the stored path so the DB points
                                        // at the file that actually exists on disk.
                                        return $optimized === null
                                            ? $path
                                            : Str::after($optimized, rtrim($disk->path(''), '/\\') . '/');
                                    }),
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
                                    ->helperText('Например: тепловые насосы, GE R290, дымоход, камин'),
                            ]),
                    ]),

                Section::make('Текст статьи')
                    ->description('Основной визуальный редактор. Можно менять текст, заголовки, цитаты, списки, ссылки и фотографии.')
                    ->columnSpanFull()
                    ->schema([
                        Placeholder::make('content_html_help')
                            ->hiddenLabel()
                            ->content(new HtmlString(
                                '<div style="line-height:1.6">Редактор сохраняет статью в HTML, но работать можно визуально. '
                                . 'Для фотографий используй кнопку загрузки в панели редактора.</div>'
                            )),

                        RichEditor::make('content')
                            ->label('Содержание статьи')
                            ->helperText('Визуальный редактор статьи. Фото добавляй кнопкой загрузки/скрепкой в панели редактора.')
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'h2',
                                'h3',
                                'h4',
                                'bulletList',
                                'orderedList',
                                'blockquote',
                                'lead',
                                'small',
                                'link',
                                'attachFiles',
                                'table',
                                'grid',
                                'horizontalRule',
                                'clearFormatting',
                                'undo',
                                'redo',
                            ])
                            ->fileAttachmentsDirectory('blog/editor')
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsVisibility('public')
                            ->fileAttachmentsAcceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->fileAttachmentsMaxSize(4096)
                            // Same fix as the cover image, applied to photos dropped into the
                            // article body: cap the shipped resolution instead of serving
                            // whatever the phone camera produced (routinely 3000px+ / several MB).
                            ->saveUploadedFileAttachmentUsing(function (RichEditor $component, TemporaryUploadedFile $file): mixed {
                                $path = $file->store($component->getFileAttachmentsDirectory(), $component->getFileAttachmentsDiskName());
                                $disk = $component->getFileAttachmentsDisk();

                                if ($component->getFileAttachmentsVisibility() === 'public') {
                                    rescue(fn () => $disk->setVisibility($path, 'public'), report: false);
                                }

                                $optimized = app(BlogImageOptimizer::class)->optimizeContentImage($disk->path($path));

                                return $optimized === null
                                    ? $path
                                    : Str::after($optimized, rtrim($disk->path(''), '/\\') . '/');
                            })
                            ->extraInputAttributes([
                                'style' => 'min-height: 640px;',
                            ])
                            ->required()
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

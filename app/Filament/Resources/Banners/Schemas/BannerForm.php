<?php

namespace App\Filament\Resources\Banners\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class BannerForm
{
    private static array $positionHints = [
        'hero' => [
            'label' => 'Главный слайдер (Hero)',
            'size'  => '1770 × 680 px',
            'hint'  => 'Широкоформатный слайд на всю ширину страницы. Рекомендуется: горизонтальное фото с тёмной или размытой областью слева для текста.',
        ],
        'promo_kotly' => [
            'label' => 'Промо — Котлы',
            'size'  => '450 × 608 px',
            'hint'  => 'Вертикальный баннер в табе "Котлы". Тёмный фон или тёмное фото — текст поверх белый.',
        ],
        'promo_nasosy' => [
            'label' => 'Промо — Тепловые насосы',
            'size'  => '450 × 608 px',
            'hint'  => 'Вертикальный баннер в табе "Тепловые насосы".',
        ],
        'promo_kaminy' => [
            'label' => 'Промо — Камины',
            'size'  => '450 × 608 px',
            'hint'  => 'Вертикальный баннер в табе "Камины".',
        ],
        'promo_akcii' => [
            'label' => 'Промо — Акции',
            'size'  => '450 × 608 px',
            'hint'  => 'Вертикальный баннер в табе "Акции".',
        ],
        'partners' => [
            'label' => 'Баннер "Партнёры"',
            'size'  => '1410 × 260 px',
            'hint'  => 'Широкий горизонтальный баннер в нижней части главной страницы.',
        ],
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Позиция')
                    ->schema([
                        Select::make('position')
                            ->label('Где показывать')
                            ->options(array_map(fn($v) => $v['label'], self::$positionHints))
                            ->default('hero')
                            ->required()
                            ->live()
                            ->helperText(fn($state) => isset(self::$positionHints[$state])
                                ? new HtmlString(
                                    '<strong>Размер изображения: ' . self::$positionHints[$state]['size'] . '</strong><br>' .
                                    self::$positionHints[$state]['hint']
                                )
                                : null
                            ),
                    ]),

                Section::make('Изображение')
                    ->schema([
                        FileUpload::make('image')
                            ->label('Изображение баннера')
                            ->image()
                            ->required()
                            ->directory('banners')
                            ->imagePreviewHeight('200')
                            ->helperText('Загрузите файл в формате JPG или PNG. Смотрите рекомендуемый размер в разделе "Позиция" выше.'),
                    ]),

                Section::make('Контент')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('Заголовок')
                            ->helperText('Например: "Тепловые насосы для дома"'),

                        TextInput::make('subtitle')
                            ->label('Подзаголовок')
                            ->helperText('Короткое описание под заголовком'),

                        TextInput::make('link')
                            ->label('Ссылка (куда ведёт баннер)')
                            ->url()
                            ->placeholder('/teplovye-nasosy')
                            ->helperText('Относительная или полная ссылка'),

                        TextInput::make('button_text')
                            ->label('Текст кнопки')
                            ->placeholder('Перейти в каталог'),
                    ]),

                Section::make('Настройки')
                    ->columns(2)
                    ->schema([
                        TextInput::make('sort_order')
                            ->label('Порядок сортировки')
                            ->numeric()
                            ->default(0)
                            ->helperText('Меньшее число — выше в слайдере. Для Hero: 1, 2, 3...'),

                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true)
                            ->helperText('Выключите чтобы скрыть баннер без удаления'),
                    ]),
            ]);
    }
}

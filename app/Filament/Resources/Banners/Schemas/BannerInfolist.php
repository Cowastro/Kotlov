<?php

namespace App\Filament\Resources\Banners\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BannerInfolist
{
    private static function positionLabel(string $state): string
    {
        return match($state) {
            'hero'         => 'Hero слайдер (1770×680 px)',
            'promo_kotly'  => 'Промо: Котлы (450×608 px)',
            'promo_nasosy' => 'Промо: Насосы (450×608 px)',
            'promo_kaminy' => 'Промо: Камины (450×608 px)',
            'promo_akcii'  => 'Промо: Акции (450×608 px)',
            'partners'     => 'Партнёры (1410×260 px)',
            default        => $state,
        };
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Изображения')
                    ->columns(1)
                    ->schema([
                        ImageEntry::make('image')
                            ->label('Десктоп')
                            ->disk('public')
                            ->height(220)
                            ->columnSpanFull(),

                        ImageEntry::make('image_mobile')
                            ->label('Мобильная версия')
                            ->disk('public')
                            ->height(220)
                            ->placeholder('Не загружено')
                            ->columnSpanFull(),
                    ]),

                Section::make('Контент')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('subtitle')
                            ->label('Надпись над заголовком')
                            ->placeholder('—'),

                        TextEntry::make('title')
                            ->label('Заголовок')
                            ->placeholder('—'),

                        TextEntry::make('description')
                            ->label('Описание')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('link')
                            ->label('Ссылка')
                            ->placeholder('—'),

                        TextEntry::make('button_text')
                            ->label('Текст кнопки')
                            ->placeholder('—'),
                    ]),

                Section::make('Настройки')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('position')
                            ->label('Позиция')
                            ->formatStateUsing(fn($state) => self::positionLabel($state))
                            ->badge(),

                        TextEntry::make('sort_order')
                            ->label('Порядок')
                            ->numeric(),

                        IconEntry::make('is_active')
                            ->label('Активен')
                            ->boolean(),
                    ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([

                // ── Левая часть: основное (2/3) ──────────────────────────────
                Section::make('Товар')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Название')
                            ->size('lg')
                            ->weight('bold')
                            ->columnSpanFull(),

                        TextEntry::make('sku')
                            ->label('Артикул'),

                        TextEntry::make('h1')
                            ->label('H1')
                            ->placeholder('—'),

                        TextEntry::make('slug')
                            ->label('URL')
                            ->copyable()
                            ->columnSpanFull(),

                        TextEntry::make('category.name')
                            ->label('Категория')
                            ->badge(),

                        TextEntry::make('brand.name')
                            ->label('Бренд')
                            ->placeholder('—'),

                        TextEntry::make('supplier.name')
                            ->label('Поставщик')
                            ->placeholder('—'),
                    ]),

                // ── Правая часть: статусы + цены (1/3) ───────────────────────
                Grid::make(1)
                    ->columnSpan(1)
                    ->schema([
                        Section::make('Статусы')
                            ->schema([
                                IconEntry::make('is_active')
                                    ->label('Активен')
                                    ->boolean(),

                                IconEntry::make('in_stock')
                                    ->label('В наличии')
                                    ->boolean(),

                                IconEntry::make('is_featured')
                                    ->label('Хит')
                                    ->boolean(),

                                IconEntry::make('is_new')
                                    ->label('Новинка')
                                    ->boolean(),

                                IconEntry::make('is_sale')
                                    ->label('Акция')
                                    ->boolean(),

                                TextEntry::make('sort_order')
                                    ->label('Сортировка'),
                            ]),

                        Section::make('Цены')
                            ->schema([
                                TextEntry::make('price')
                                    ->label('Цена')
                                    ->money('BYN'),

                                TextEntry::make('price_old')
                                    ->label('Старая цена')
                                    ->money('BYN')
                                    ->placeholder('—'),

                                TextEntry::make('currency')
                                    ->label('Валюта'),

                                TextEntry::make('stock_qty')
                                    ->label('На складе')
                                    ->numeric()
                                    ->placeholder('—'),

                                TextEntry::make('unit')
                                    ->label('Ед. изм.'),

                                TextEntry::make('warranty')
                                    ->label('Гарантия')
                                    ->placeholder('—'),
                            ]),
                    ]),

                // ── Фото ─────────────────────────────────────────────────────
                Section::make('Фотографии')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('images')
                            ->label('')
                            ->formatStateUsing(function ($record): string {
                                $images = $record->images ?? [];
                                if (empty($images)) {
                                    return '<p class="text-gray-400 text-sm">Нет фотографий</p>';
                                }
                                $html = '<div class="flex flex-wrap gap-3">';
                                foreach (array_slice($images, 0, 20) as $i => $img) {
                                    $url = $record->imageUrl($i);
                                    $html .= '<img src="' . e($url) . '" '
                                        . 'style="height:100px;border-radius:6px;object-fit:cover;border:1px solid #e5e7eb;" '
                                        . 'onerror="this.style.opacity=0.2">';
                                }
                                $html .= '</div>';
                                return $html;
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),

                // ── Описание ─────────────────────────────────────────────────
                Section::make('Описание')
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('short_description')
                            ->label('Краткое')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('content')
                            ->label('Полное')
                            ->html()
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                // ── Характеристики ────────────────────────────────────────────
                Section::make('Характеристики')
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('specs')
                            ->label('')
                            ->formatStateUsing(function ($record): string {
                                $specs = $record->specs ?? [];
                                if (empty($specs)) {
                                    return '<p class="text-gray-400 text-sm">Нет характеристик</p>';
                                }
                                $html = '<table class="w-full text-sm border-collapse">';
                                foreach ($specs as $spec) {
                                    $key   = e($spec['key']   ?? '');
                                    $val   = e($spec['value'] ?? '');
                                    $unit  = e($spec['unit']  ?? '');
                                    $html .= "<tr class=\"border-b border-gray-100\">"
                                        . "<td class=\"py-1 pr-4 text-gray-500 w-1/2\">{$key}</td>"
                                        . "<td class=\"py-1 font-medium\">{$val}" . ($unit ? " <span class=\"text-gray-400\">{$unit}</span>" : '') . "</td>"
                                        . "</tr>";
                                }
                                $html .= '</table>';
                                return $html;
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),

                // ── SEO ───────────────────────────────────────────────────────
                Section::make('SEO')
                    ->columnSpanFull()
                    ->collapsed()
                    ->columns(1)
                    ->schema([
                        TextEntry::make('meta_title')
                            ->label('Meta Title')
                            ->placeholder('—'),

                        TextEntry::make('meta_keywords')
                            ->label('Meta Keywords')
                            ->placeholder('—'),

                        TextEntry::make('meta_description')
                            ->label('Meta Description')
                            ->placeholder('—'),
                    ]),

                // ── Статистика и даты ─────────────────────────────────────────
                Section::make('Статистика')
                    ->columnSpanFull()
                    ->collapsed()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('rating')
                            ->label('Рейтинг')
                            ->formatStateUsing(fn($state) => $state > 0 ? '★ ' . $state : '—'),

                        TextEntry::make('reviews_count')
                            ->label('Отзывов')
                            ->numeric(),

                        TextEntry::make('views_count')
                            ->label('Просмотров')
                            ->numeric(),

                        TextEntry::make('created_at')
                            ->label('Создан')
                            ->dateTime('d.m.Y H:i'),

                        TextEntry::make('updated_at')
                            ->label('Обновлён')
                            ->dateTime('d.m.Y H:i'),
                    ]),

            ]);
    }
}

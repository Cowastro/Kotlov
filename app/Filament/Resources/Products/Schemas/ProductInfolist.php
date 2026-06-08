<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(12)
            ->components([
                Section::make('Карточка товара')
                    ->columnSpan(8)
                    ->columns(12)
                    ->schema([
                        TextEntry::make('photo_gallery')
                            ->label('Фото')
                            ->state(fn($record): string => self::renderImageGallery($record))
                            ->html()
                            ->columnSpanFull(),

                        Grid::make(2)
                            ->columnSpanFull()
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Название')
                                    ->size('lg')
                                    ->weight('bold')
                                    ->columnSpanFull(),

                                TextEntry::make('sku')
                                    ->label('Артикул')
                                    ->copyable()
                                    ->placeholder('—'),

                                TextEntry::make('slug')
                                    ->label('URL')
                                    ->copyable()
                                    ->placeholder('—'),

                                TextEntry::make('frontend_url')
                                    ->label('На сайте')
                                    ->state(fn($record) => self::productUrl($record) ? 'Открыть товар' : '—')
                                    ->url(fn($record) => self::productUrl($record))
                                    ->openUrlInNewTab()
                                    ->icon('heroicon-o-arrow-top-right-on-square')
                                    ->placeholder('—'),

                                TextEntry::make('category.name')
                                    ->label('Категория')
                                    ->badge()
                                    ->placeholder('—'),

                                TextEntry::make('brand.name')
                                    ->label('Бренд')
                                    ->placeholder('—'),

                                TextEntry::make('supplier.name')
                                    ->label('Поставщик')
                                    ->placeholder('—'),

                                TextEntry::make('h1')
                                    ->label('H1')
                                    ->placeholder('—'),
                            ]),
                    ]),

                Grid::make(1)
                    ->columnSpan(4)
                    ->schema([
                        Section::make('Цена и наличие')
                            ->columns(2)
                            ->schema([
                                TextEntry::make('price')
                                    ->label('Цена')
                                    ->money('BYN')
                                    ->size('lg')
                                    ->weight('bold'),

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

                        Section::make('Статусы')
                            ->columns(2)
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
                    ]),

                Section::make('Описание')
                    ->columnSpan(8)
                    ->schema([
                        TextEntry::make('short_description')
                            ->label('Краткое описание')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('content')
                            ->label('Полное описание')
                            ->html()
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Статистика')
                    ->columnSpan(4)
                    ->columns(2)
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
                            ->dateTime('d.m.Y H:i')
                            ->columnSpanFull(),
                    ]),

                Section::make('Характеристики')
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('specs')
                            ->label('')
                            ->formatStateUsing(function ($state, $record): string {
                                $specs = self::normalizeArrayState($state ?? $record->specs ?? []);
                                if (empty($specs)) {
                                    return '<p class="text-gray-400 text-sm">Нет характеристик</p>';
                                }

                                $html = '<div class="grid gap-2 md:grid-cols-2 xl:grid-cols-3">';
                                foreach ($specs as $spec) {
                                    $key = e($spec['key'] ?? '');
                                    $val = e($spec['value'] ?? '');
                                    $unit = e($spec['unit'] ?? '');
                                    $html .= '<div class="rounded-lg border border-gray-800 p-3">'
                                        . '<div class="text-xs text-gray-500">' . $key . '</div>'
                                        . '<div class="font-medium">' . $val . ($unit ? ' <span class="text-gray-500">' . $unit . '</span>' : '') . '</div>'
                                        . '</div>';
                                }
                                $html .= '</div>';

                                return $html;
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),

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
            ]);
    }

    private static function normalizeArrayState(mixed $state): array
    {
        if (is_array($state)) {
            return $state;
        }

        if (is_string($state)) {
            $decoded = json_decode($state, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            $state = trim($state);

            return $state === '' ? [] : [$state];
        }

        return [];
    }

    private static function renderImageGallery(object $record): string
    {
        $images = self::normalizeArrayState($record->images ?? []);

        if (empty($images)) {
            return '<div style="height:72px;display:flex;align-items:center;justify-content:center;border:1px dashed #3f3f46;border-radius:8px;color:#71717a;font-size:13px;">Нет фото</div>';
        }

        $seen = [];
        $items = [];

        foreach ($images as $index => $path) {
            $key = is_scalar($path) ? trim((string) $path) : json_encode($path);

            if ($key === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $items[] = [$index, $key];
        }

        $html = '<div style="display:flex;max-width:100%;gap:10px;overflow-x:auto;overflow-y:visible;padding:2px 2px 120px;">';

        foreach (array_slice($items, 0, 12) as [$index]) {
            $url = $record->imageUrl((int) $index);
            $html .= '<a href="' . e($url) . '" target="_blank" rel="noopener noreferrer" title="Открыть фото крупно" '
                . 'style="display:block;height:112px;width:112px;min-width:112px;">'
                . '<img src="' . e($url) . '" '
                . 'style="height:112px;width:112px;min-width:112px;border-radius:7px;object-fit:cover;border:1px solid #3f3f46;background:#18181b;transition:transform .16s ease,box-shadow .16s ease;transform-origin:top left;position:relative;" '
                . 'onerror="this.style.opacity=0.25">'
                . '</a>';
        }

        $html .= '</div>';

        return $html;
    }

    private static function productUrl(object $record): ?string
    {
        $categorySlug = $record->category?->slug;

        if (!$categorySlug || !$record->slug) {
            return null;
        }

        return url('/' . $categorySlug . '/' . $record->slug);
    }
}

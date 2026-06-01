<?php

namespace App\Filament\Resources\Attributes\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AttributeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name')->label('Название'),
            TextEntry::make('category.name')->label('Категория'),
            TextEntry::make('type')->label('Тип')
                ->formatStateUsing(fn($state) => match($state) {
                    'select' => 'Выбор из вариантов',
                    'value'  => 'Значение (число/текст)',
                    'check'  => 'Да / Нет',
                    default  => $state,
                }),
            TextEntry::make('suffix')->label('Ед. измерения')->placeholder('—'),
            TextEntry::make('sort_order')->label('Сортировка'),
            IconEntry::make('in_product')->label('На странице товара')->boolean(),
            IconEntry::make('in_filter')->label('В фильтрах')->boolean(),
            IconEntry::make('in_brief')->label('В карточке')->boolean(),
            IconEntry::make('in_sort')->label('Для сортировки')->boolean(),
            IconEntry::make('is_comparable')->label('При сравнении')->boolean(),
        ]);
    }
}
<?php

namespace App\Filament\Resources\CityCategorySeos\Schemas;

use App\Models\Category;
use App\Models\City;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class CityCategorySeoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Город и категория')
                ->description('Свой текст и мета-теги категории на сайте города, например lida.kotlov.by/gazovye. Пустое поле — берётся общий шаблон с названием города.')
                ->columns(2)
                ->schema([
                    Select::make('city_id')
                        ->label('Город')
                        ->options(fn () => City::query()->where('storefront_enabled', true)->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    Select::make('category_id')
                        ->label('Категория')
                        ->options(fn () => Category::query()->where('is_active', true)->orderBy('name')->get()
                            ->mapWithKeys(fn (Category $category) => [$category->id => $category->name.' — /'.$category->slug]))
                        ->searchable()
                        ->required()
                        ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, $get) => $rule->where('city_id', $get('city_id'))),
                    Toggle::make('is_active')->label('Показывать на сайте')->default(true),
                ]),
            Section::make('Мета-теги')
                ->schema([
                    TextInput::make('h1')->label('Заголовок H1')->maxLength(255)->placeholder('Газовые котлы в Лиде'),
                    TextInput::make('meta_title')->label('Title')->maxLength(70)
                        ->helperText('До 70 символов, с названием города: «Купить газовый котёл в Лиде — цены, наличие | KOTLOV».'),
                    Textarea::make('meta_description')->label('Description')->rows(3)->maxLength(180)
                        ->helperText('До 180 символов: что купить, где (адрес магазина), чем вы лучше.'),
                ]),
            Section::make('Текст на странице')
                ->schema([
                    RichEditor::make('content')->label('Текст под товарами')
                        ->helperText('Уникальный текст для этого города: адрес магазина, доставка по городу и району, что здесь чаще покупают.'),
                ]),
        ]);
    }
}

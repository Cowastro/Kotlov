<?php

namespace App\Filament\Resources\Categories\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AttributesRelationManager extends RelationManager
{
    protected static string $relationship = 'attributes';
    protected static ?string $title = 'Атрибуты категории';
    protected static ?string $label = 'Атрибут';
    protected static ?string $pluralLabel = 'Атрибуты';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Атрибут')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Название')
                        ->required()
                        ->placeholder('Мощность'),

                    Select::make('type')
                        ->label('Тип')
                        ->options([
                            'value'  => 'Значение (число/текст)',
                            'select' => 'Выбор из вариантов',
                            'check'  => 'Да / Нет',
                        ])
                        ->default('value')
                        ->required(),

                    TextInput::make('suffix')
                        ->label('Единица измерения')
                        ->placeholder('кВт, л, °C, %'),

                    TextInput::make('sort_order')
                        ->label('Сортировка')
                        ->numeric()
                        ->default(0),
                ]),

            Section::make('Отображение')
                ->columns(3)
                ->schema([
                    Toggle::make('in_product')
                        ->label('На странице товара')
                        ->default(true),
                    Toggle::make('in_filter')
                        ->label('В фильтрах каталога')
                        ->default(false),
                    Toggle::make('in_brief')
                        ->label('В карточке товара')
                        ->default(false),
                    Toggle::make('in_sort')
                        ->label('Для сортировки')
                        ->default(false),
                    Toggle::make('is_comparable')
                        ->label('При сравнении')
                        ->default(false),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->width('40px'),

                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->color(fn($state) => match($state) {
                        'select' => 'info',
                        'value'  => 'success',
                        'check'  => 'warning',
                        default  => 'gray',
                    })
                    ->formatStateUsing(fn($state) => match($state) {
                        'select' => 'Выбор',
                        'value'  => 'Значение',
                        'check'  => 'Да/Нет',
                        default  => $state,
                    }),

                TextColumn::make('suffix')
                    ->label('Ед. изм.')
                    ->placeholder('—'),

                TextColumn::make('options_count')
                    ->label('Вариантов')
                    ->counts('options'),

                IconColumn::make('in_product')->label('Товар')->boolean(),
                IconColumn::make('in_filter')->label('Фильтр')->boolean(),
                IconColumn::make('in_brief')->label('Карточка')->boolean(),
                IconColumn::make('is_comparable')->label('Сравнение')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('type')
                    ->label('Тип')
                    ->options([
                        'value'  => 'Значение',
                        'select' => 'Выбор',
                        'check'  => 'Да/Нет',
                    ]),
            ])
            ->headerActions([
                CreateAction::make()->label('Добавить атрибут'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
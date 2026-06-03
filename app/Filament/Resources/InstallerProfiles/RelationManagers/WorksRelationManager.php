<?php

namespace App\Filament\Resources\InstallerProfiles\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorksRelationManager extends RelationManager
{
    protected static string $relationship = 'works';
    protected static ?string $title = 'Портфолио работ';
    protected static ?string $label = 'Работа';
    protected static ?string $pluralLabel = 'Работы';

    public function form(Schema $schema): Schema
    {
        return $schema->components([

            TextInput::make('title')
                ->label('Название работы')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            Select::make('work_type')
                ->label('Тип работы')
                ->options([
                    'heating'       => 'Монтаж котла',
                    'heatpump'      => 'Монтаж теплового насоса',
                    'fireplace'     => 'Монтаж камина',
                    'chimney'       => 'Монтаж дымохода',
                    'sauna'         => 'Монтаж банной печи',
                    'service'       => 'Сервис',
                    'commissioning' => 'Пусконаладка',
                    'other'         => 'Другое',
                ])
                ->nullable(),

            Select::make('region')
                ->label('Область')
                ->options([
                    'Минск'               => 'Минск',
                    'Минская область'     => 'Минская область',
                    'Гомельская область'  => 'Гомельская область',
                    'Гродненская область' => 'Гродненская область',
                    'Брестская область'   => 'Брестская область',
                    'Витебская область'   => 'Витебская область',
                    'Могилёвская область' => 'Могилёвская область',
                ])
                ->nullable(),

            TextInput::make('city')
                ->label('Город')
                ->maxLength(255),

            TextInput::make('equipment_type')
                ->label('Тип оборудования')
                ->maxLength(255),

            TextInput::make('brand')
                ->label('Бренд')
                ->maxLength(255),

            DatePicker::make('completed_at')
                ->label('Дата выполнения')
                ->nullable(),

            Toggle::make('is_published')
                ->label('Опубликована')
                ->default(true),

            Textarea::make('description')
                ->label('Описание')
                ->rows(4)
                ->nullable()
                ->columnSpanFull(),

            FileUpload::make('photos')
                ->label('Фотографии работы')
                ->image()
                ->multiple()
                ->directory('installers/works')
                ->disk('public')
                ->maxSize(4096)
                ->columnSpanFull(),

        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Название')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                TextColumn::make('work_type')
                    ->label('Тип')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'heating'       => 'warning',
                        'heatpump'      => 'info',
                        'fireplace'     => 'danger',
                        'chimney'       => 'gray',
                        'sauna'         => 'success',
                        'service'       => 'primary',
                        'commissioning' => 'primary',
                        default         => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'heating'       => 'Монтаж котла',
                        'heatpump'      => 'Монтаж насоса',
                        'fireplace'     => 'Монтаж камина',
                        'chimney'       => 'Монтаж дымохода',
                        'sauna'         => 'Монтаж банной печи',
                        'service'       => 'Сервис',
                        'commissioning' => 'Пусконаладка',
                        'other'         => 'Другое',
                        default         => $state,
                    }),
                TextColumn::make('city')
                    ->label('Город')
                    ->placeholder('—'),
                TextColumn::make('region')
                    ->label('Область')
                    ->placeholder('—'),
                TextColumn::make('brand')
                    ->label('Бренд')
                    ->placeholder('—'),
                TextColumn::make('completed_at')
                    ->label('Дата')
                    ->date('d.m.Y')
                    ->sortable()
                    ->placeholder('—'),
                IconColumn::make('is_published')
                    ->label('Опубл.')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Добавлена')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('completed_at', 'desc')
            ->headerActions([
                CreateAction::make()->label('Добавить работу'),
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

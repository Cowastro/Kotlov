<?php

namespace App\Filament\Resources\MarketPriceSources;

use App\Filament\Resources\MarketPriceSources\Pages\CreateMarketPriceSource;
use App\Filament\Resources\MarketPriceSources\Pages\EditMarketPriceSource;
use App\Filament\Resources\MarketPriceSources\Pages\ListMarketPriceSources;
use App\Models\MarketPriceCollectionRun;
use App\Models\MarketPriceSource;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MarketPriceSourceResource extends Resource
{
    protected static ?string $model = MarketPriceSource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $navigationLabel = 'Источники рынка';

    protected static ?string $modelLabel = 'источник рынка';

    protected static ?string $pluralModelLabel = 'Источники рынка';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'Аналитика';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Название источника')
                ->required()
                ->maxLength(255),
            TextInput::make('code')
                ->label('Код')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),
            Select::make('kind')
                ->label('Тип источника')
                ->options(MarketPriceSource::KINDS)
                ->required(),
            Select::make('collection_method')
                ->label('Способ получения')
                ->options(MarketPriceSource::COLLECTION_METHODS)
                ->default('manual')
                ->required(),
            TextInput::make('base_url')
                ->label('Адрес сайта')
                ->url()
                ->maxLength(2048),
            TextInput::make('region')
                ->label('Регион сравнения')
                ->default('Беларусь')
                ->required(),
            TextInput::make('currency')
                ->label('Валюта по умолчанию')
                ->default('BYN')
                ->required()
                ->maxLength(3),
            TextInput::make('freshness_hours')
                ->label('Считать свежим, часов')
                ->helperText('После этого срока предложение исключается из рыночного коридора.')
                ->numeric()
                ->minValue(1)
                ->maxValue(720)
                ->default(48)
                ->required(),
            TextInput::make('collection_interval_minutes')
                ->label('Интервал сбора, минут')
                ->helperText('Для API и разрешённого автоматического сбора. Не запускает сбор чаще указанного интервала.')
                ->numeric()
                ->minValue(15)
                ->maxValue(10080)
                ->default(360)
                ->required(),
            TextInput::make('max_requests_per_run')
                ->label('Лимит за один запуск')
                ->numeric()
                ->minValue(1)
                ->maxValue(5000)
                ->default(100)
                ->required(),
            TextInput::make('max_requests_per_day')
                ->label('Лимит запросов в сутки')
                ->numeric()
                ->minValue(1)
                ->maxValue(50000)
                ->default(500)
                ->required(),
            TagsInput::make('allowed_path_prefixes')
                ->label('Разрешённые пути сайта')
                ->helperText('Например: /catalog/ и /products/. Пустое поле разрешает весь указанный домен.')
                ->placeholder('/catalog/')
                ->columnSpanFull(),
            Toggle::make('respect_robots_txt')
                ->label('Соблюдать robots.txt')
                ->helperText('Обязательное правило для автоматического сбора с сайтов.')
                ->default(true),
            Toggle::make('collection_authorized')
                ->label('Автоматический сбор подтверждён')
                ->helperText('Включайте только после проверки прав, условий источника, домена и допустимой нагрузки. Без этого API/сбор не запустится.')
                ->default(false),
            TextInput::make('minimum_match_confidence')
                ->label('Минимальная уверенность')
                ->helperText('От 0 до 1. Например, 0.85 означает 85%.')
                ->numeric()
                ->minValue(0)
                ->maxValue(1)
                ->step('0.01')
                ->default(0.85)
                ->required(),
            Toggle::make('is_active')
                ->label('Разрешён для анализа')
                ->helperText('Только активные разрешённые источники участвуют в расчётах.')
                ->default(true),
            Textarea::make('notes')
                ->label('Условия и заметки')
                ->helperText('Зафиксируйте ограничения API, импорта или разрешённого сбора.')
                ->rows(4)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Источник')->searchable()->sortable()->weight('bold'),
                TextColumn::make('kind')->label('Тип')
                    ->formatStateUsing(fn (string $state): string => MarketPriceSource::KINDS[$state] ?? $state)
                    ->badge(),
                TextColumn::make('collection_method')->label('Получение')
                    ->formatStateUsing(fn (string $state): string => MarketPriceSource::COLLECTION_METHODS[$state] ?? $state)
                    ->badge()
                    ->color('info'),
                TextColumn::make('region')->label('Регион'),
                TextColumn::make('freshness_hours')->label('Свежесть')->suffix(' ч')->alignRight(),
                TextColumn::make('collection_schedule')->label('Режим сбора')
                    ->state(fn (MarketPriceSource $record): string => $record->collectionScheduleLabel())
                    ->wrap(),
                IconColumn::make('collection_authorized')->label('Автосбор')->boolean(),
                TextColumn::make('last_collection_at')->label('Последний сбор')
                    ->dateTime('d.m.Y H:i', 'Europe/Minsk')->placeholder('Не запускался')->sortable(),
                TextColumn::make('latestCollectionRun.status')->label('Последний результат')
                    ->formatStateUsing(fn (?string $state): string => MarketPriceCollectionRun::STATUSES[$state] ?? 'Нет запусков')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'success' => 'success',
                        'warning' => 'warning',
                        'failed', 'blocked' => 'danger',
                        'running' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('minimum_match_confidence')->label('Порог')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state * 100, 0).'%')
                    ->alignRight(),
                TextColumn::make('observations_count')->label('Наблюдений')->counts('observations')->alignRight(),
                IconColumn::make('is_active')->label('Разрешён')->boolean(),
            ])
            ->defaultSort('name')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarketPriceSources::route('/'),
            'create' => CreateMarketPriceSource::route('/create'),
            'edit' => EditMarketPriceSource::route('/{record}/edit'),
        ];
    }
}

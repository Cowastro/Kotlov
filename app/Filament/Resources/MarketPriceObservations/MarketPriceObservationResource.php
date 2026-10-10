<?php

namespace App\Filament\Resources\MarketPriceObservations;

use App\Filament\Resources\MarketPriceObservations\Pages\CreateMarketPriceObservation;
use App\Filament\Resources\MarketPriceObservations\Pages\EditMarketPriceObservation;
use App\Filament\Resources\MarketPriceObservations\Pages\ListMarketPriceObservations;
use App\Models\MarketPriceObservation;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MarketPriceObservationResource extends Resource
{
    protected static ?string $model = MarketPriceObservation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?string $navigationLabel = 'Наблюдения рынка';

    protected static ?string $modelLabel = 'рыночное наблюдение';

    protected static ?string $pluralModelLabel = 'Наблюдения рынка';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return 'Аналитика';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['product.category', 'source']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('product_id')
                ->label('Карточка kotlov.by')
                ->relationship('product', 'name')
                ->searchable(['name', 'sku'])
                ->preload()
                ->required(),
            Select::make('market_price_source_id')
                ->label('Источник рынка')
                ->relationship('source', 'name', modifyQueryUsing: fn ($query) => $query->where('is_active', true))
                ->searchable()
                ->preload()
                ->required(),
            TextInput::make('url')
                ->label('URL предложения')
                ->url()
                ->required()
                ->columnSpanFull(),
            TextInput::make('external_name')->label('Название у источника')->maxLength(255)->columnSpanFull(),
            TextInput::make('external_sku')->label('Артикул'),
            TextInput::make('model')->label('Модель'),
            TextInput::make('package')->label('Комплектация'),
            TextInput::make('unit')->label('Единица измерения'),
            TextInput::make('observed_price')
                ->label('Цена у источника')
                ->numeric()
                ->minValue(0.01)
                ->required(),
            TextInput::make('currency')->label('Валюта')->default('BYN')->required()->maxLength(3),
            TextInput::make('exchange_rate_to_byn')
                ->label('Курс к BYN')
                ->helperText('Нормализованная цена рассчитывается автоматически.')
                ->numeric()
                ->minValue(0.000001)
                ->step('0.000001')
                ->default(1)
                ->required(),
            TextInput::make('delivery_price_byn')->label('Доставка, BYN')->numeric()->minValue(0),
            Select::make('price_includes_vat')
                ->label('НДС в рыночной цене')
                ->options([1 => 'Включён', 0 => 'Не включён'])
                ->placeholder('Неизвестно'),
            TextInput::make('vat_rate')->label('Ставка НДС, %')->numeric()->minValue(0)->maxValue(100),
            TextInput::make('region')->label('Регион')->default('Беларусь')->required(),
            Select::make('availability_status')
                ->label('Наличие')
                ->options(MarketPriceObservation::AVAILABILITY_STATUSES)
                ->default('unknown')
                ->required(),
            Select::make('match_method')
                ->label('Способ сопоставления')
                ->options(MarketPriceObservation::MATCH_METHODS)
                ->default('manual')
                ->required(),
            TextInput::make('match_confidence')
                ->label('Уверенность, 0–1')
                ->numeric()
                ->minValue(0)
                ->maxValue(1)
                ->step('0.01')
                ->default(0)
                ->required(),
            DateTimePicker::make('observed_at')
                ->label('Проверено')
                ->default(now())
                ->required(),
            Toggle::make('is_confirmed')
                ->label('Подтверждено человеком')
                ->helperText('Без подтверждения наблюдение хранится, но не влияет на вывод.'),
            Toggle::make('is_comparable')
                ->label('Комплектация сопоставима')
                ->helperText('Подтвердите модель, комплект, единицу измерения, НДС, регион и доставку.'),
            TagsInput::make('validation_flags')
                ->label('Причины исключения')
                ->helperText('Любая причина исключает наблюдение из рыночного коридора.')
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label('Товар kotlov.by')
                    ->description(fn (MarketPriceObservation $record): ?string => $record->product?->sku)
                    ->searchable()
                    ->limit(45)
                    ->wrap(),
                TextColumn::make('source.name')
                    ->label('Источник')
                    ->description(fn (MarketPriceObservation $record): ?string => $record->source?->kindLabel())
                    ->badge()
                    ->searchable(),
                TextColumn::make('external_name')
                    ->label('Предложение')
                    ->description(fn (MarketPriceObservation $record): ?string => $record->external_sku ?: $record->model)
                    ->placeholder('Название не передано')
                    ->limit(45)
                    ->wrap(),
                TextColumn::make('price_byn')
                    ->label('Цена рынка')
                    ->money('BYN')
                    ->description(fn (MarketPriceObservation $record): string => number_format((float) $record->observed_price, 2, ',', ' ').' '.$record->currency)
                    ->sortable(),
                TextColumn::make('availability_status')
                    ->label('Наличие')
                    ->formatStateUsing(fn (string $state): string => MarketPriceObservation::AVAILABILITY_STATUSES[$state] ?? $state)
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'in_stock' => 'success',
                        'out_of_stock' => 'danger',
                        'order' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('match_confidence')
                    ->label('Совпадение')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state * 100, 0).'%')
                    ->badge()
                    ->color(fn ($state): string => (float) $state >= 0.85 ? 'success' : 'warning'),
                IconColumn::make('is_confirmed')->label('Подтверждено')->boolean(),
                IconColumn::make('is_comparable')->label('Сопоставимо')->boolean(),
                TextColumn::make('freshness')
                    ->label('Актуальность')
                    ->state(fn (MarketPriceObservation $record): string => $record->observed_at?->diffForHumans() ?? 'Нет даты')
                    ->description(fn (MarketPriceObservation $record): string => $record->observed_at?->format('d.m.Y H:i') ?? '')
                    ->badge()
                    ->color(fn (MarketPriceObservation $record): string => $record->observed_at?->gte(now()->subHours($record->source?->freshness_hours ?? 0)) ? 'success' : 'danger'),
                TextColumn::make('validation_flags')
                    ->label('Проверка')
                    ->state(fn (MarketPriceObservation $record): string => empty($record->validation_flags) ? 'Без замечаний' : implode(', ', $record->validation_flags))
                    ->badge()
                    ->color(fn (MarketPriceObservation $record): string => empty($record->validation_flags) ? 'success' : 'danger')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('market_price_source_id')->label('Источник')->relationship('source', 'name')->searchable()->preload(),
                SelectFilter::make('product_id')->label('Товар')->relationship('product', 'name')->searchable(),
                SelectFilter::make('availability_status')->label('Наличие')->options(MarketPriceObservation::AVAILABILITY_STATUSES),
                TernaryFilter::make('is_confirmed')->label('Подтверждено'),
                TernaryFilter::make('is_comparable')->label('Сопоставимо'),
            ])
            ->defaultSort('observed_at', 'desc')
            ->recordActions([
                Action::make('openOffer')
                    ->label('Открыть предложение')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (MarketPriceObservation $record): string => $record->url)
                    ->openUrlInNewTab(),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarketPriceObservations::route('/'),
            'create' => CreateMarketPriceObservation::route('/create'),
            'edit' => EditMarketPriceObservation::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources\B2bPriceLevels;

use App\Filament\Resources\B2bPriceLevels\Pages\CreateB2bPriceLevel;
use App\Filament\Resources\B2bPriceLevels\Pages\EditB2bPriceLevel;
use App\Filament\Resources\B2bPriceLevels\Pages\ListB2bPriceLevels;
use App\Models\B2bPriceLevel;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class B2bPriceLevelResource extends Resource
{
    protected static ?string $model = B2bPriceLevel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Уровни цен';

    protected static ?string $modelLabel = 'уровень цены';

    protected static ?string $pluralModelLabel = 'Уровни цен';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'B2B';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Название')->required()->maxLength(255),
            TextInput::make('code')->label('Код')->required()->unique(ignoreRecord: true)->maxLength(40),
            TextInput::make('rank')->label('Ранг')->numeric()->minValue(1)->required(),
            TextInput::make('sort_order')->label('Порядок')->numeric()->minValue(0)->required(),
            TextInput::make('sale_price_discount_percent')
                ->label('Скидка от готовой B2B-цены')
                ->helperText('Не применяется к закупочным ценам. До настройки оставьте 0%.')
                ->numeric()
                ->minValue(0)
                ->maxValue(99.99)
                ->suffix('%')
                ->required(),
            Toggle::make('is_active')->label('Уровень активен')->default(true),
            Textarea::make('description')->label('Описание и привилегии')->rows(4)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Уровень')->searchable()->sortable()->weight('bold'),
                TextColumn::make('code')->label('Код')->badge(),
                TextColumn::make('rank')->label('Ранг')->numeric()->sortable(),
                TextColumn::make('sale_price_discount_percent')->label('Скидка')->suffix('%')->sortable(),
                TextColumn::make('clients_count')->label('Клиентов')->counts('clients')->numeric(),
                IconColumn::make('is_active')->label('Активен')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListB2bPriceLevels::route('/'),
            'create' => CreateB2bPriceLevel::route('/create'),
            'edit' => EditB2bPriceLevel::route('/{record}/edit'),
        ];
    }
}

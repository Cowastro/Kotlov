<?php

namespace App\Filament\Resources\IntegrationSources;

use App\Filament\Resources\IntegrationSources\Pages\CreateIntegrationSource;
use App\Filament\Resources\IntegrationSources\Pages\EditIntegrationSource;
use App\Filament\Resources\IntegrationSources\Pages\ListIntegrationSources;
use App\Models\IntegrationSource;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class IntegrationSourceResource extends Resource
{
    protected static ?string $model = IntegrationSource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $navigationLabel = 'Источники';

    protected static ?string $modelLabel = 'источник интеграции';

    protected static ?string $pluralModelLabel = 'Источники интеграции';

    protected static ?int $navigationSort = 9;

    public static function getNavigationGroup(): ?string
    {
        return 'Интеграции';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Подключение')
                ->schema([
                    TextInput::make('name')->label('Название')->required()->maxLength(255),
                    TextInput::make('code')->label('Код')->required()->maxLength(100)
                        ->unique(ignoreRecord: true)
                        ->helperText('Например: onec, ozon, wildberries.'),
                    Select::make('driver')->label('Формат')->options([
                        'commerceml' => 'CommerceML (1С)',
                        'api' => 'API маркетплейса',
                        'file' => 'Файл/прайс',
                    ])->required()->default('commerceml'),
                    Toggle::make('is_active')->label('Подключение активно')->default(true),
                    TextInput::make('username')->label('Пользователь обмена')->maxLength(255)
                        ->helperText('Для 1С задайте отдельного пользователя, не логин администратора.'),
                    TextInput::make('password_hash')->label('Новый пароль')->password()->revealable()
                        ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Hash::make($state) : null)
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->afterStateHydrated(fn (TextInput $component) => $component->state(null))
                        ->helperText('Хранится только хеш. Оставьте пустым, чтобы не менять пароль.'),
                    Placeholder::make('exchange_url')
                        ->label('Адрес обмена для 1С')
                        ->content(fn (?IntegrationSource $record): string => $record
                            ? url('/1c/exchange/'.$record->code)
                            : 'Появится после сохранения источника')
                        ->columnSpanFull(),
                ])->columns(2),
            Section::make('Разрешённые изменения')
                ->description('Для первой выгрузки оставьте всё выключенным: данные попадут только в буфер сопоставления.')
                ->schema([
                    Toggle::make('create_products')->label('Создавать новые карточки')->default(false),
                    Toggle::make('update_prices')->label('Обновлять цены')->default(false),
                    Toggle::make('update_stock')->label('Обновлять остатки')->default(false),
                ])->columns(3),
            Section::make('Оптовые цены')
                ->description('Определяет, как показывать цены этого источника одобренным B2B-партнёрам.')
                ->schema([
                    Select::make('settings.price_tax_mode')
                        ->label('Налогообложение цены')
                        ->options([
                            'exclusive' => 'Цена без НДС',
                            'inclusive' => 'Цена с НДС',
                        ])
                        ->default('exclusive')
                        ->required(),
                    TextInput::make('settings.vat_rate')
                        ->label('Ставка НДС')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->suffix('%')
                        ->default(20)
                        ->required(),
                    TextInput::make('settings.warehouse_label')
                        ->label('Название склада')
                        ->default('Основной')
                        ->maxLength(100),
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Источник')->searchable()->sortable(),
                TextColumn::make('code')->label('Код')->badge()->copyable(),
                TextColumn::make('exchange_url')->label('Адрес 1С')
                    ->state(fn (IntegrationSource $record): string => url('/1c/exchange/'.$record->code))
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('driver')->label('Формат')->badge(),
                TextColumn::make('products_count')->label('Товаров в наличии')
                    ->counts(['products' => fn ($query) => $query->inStock()])->sortable(),
                TextColumn::make('matched_products_count')->label('Привязано')
                    ->counts(['products' => fn ($query) => $query->inStock()->where('match_status', 'matched')]),
                IconColumn::make('update_prices')->label('Цены')->boolean(),
                IconColumn::make('update_stock')->label('Остатки')->boolean(),
                IconColumn::make('is_active')->label('Активен')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIntegrationSources::route('/'),
            'create' => CreateIntegrationSource::route('/create'),
            'edit' => EditIntegrationSource::route('/{record}/edit'),
        ];
    }
}

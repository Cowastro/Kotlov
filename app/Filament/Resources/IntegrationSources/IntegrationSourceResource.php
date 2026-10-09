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
            Section::make('Автоматический обмен и контроль')
                ->description('Регламентное задание запускается на стороне 1С. Сайт принимает каталог, цены и остатки, а при обмене заказами отдаёт новые заказы и принимает их статусы.')
                ->schema([
                    Placeholder::make('automation_notice')
                        ->label('Как работает автоматизация')
                        ->content('В 1С настройте запуск обмена каждые 5 минут. Полный каталог достаточно отправлять ночью или вручную; цены, остатки, новые заказы и статусы — в каждом регулярном цикле.')
                        ->columnSpanFull(),
                    TextInput::make('settings.order_interval_minutes')
                        ->label('Ожидаемый интервал заказов')
                        ->numeric()
                        ->minValue(2)
                        ->maxValue(60)
                        ->suffix('мин')
                        ->default(5)
                        ->required(),
                    TextInput::make('settings.catalog_interval_minutes')
                        ->label('Ожидаемый интервал цен и остатков')
                        ->numeric()
                        ->minValue(5)
                        ->maxValue(1440)
                        ->suffix('мин')
                        ->default(10)
                        ->required(),
                    TextInput::make('settings.stale_after_minutes')
                        ->label('Считать обмен просроченным через')
                        ->numeric()
                        ->minValue(5)
                        ->maxValue(1440)
                        ->suffix('мин')
                        ->default(15)
                        ->required(),
                    Toggle::make('settings.allow_order_export')
                        ->label('Отдавать новые заказы этому источнику')
                        ->helperText('Для основного источника onec включено протоколом автоматически.'),
                ])->columns(3),
            Section::make('Оптовые цены')
                ->description('Единое правило: хранится исходная цена поставщика, а клиенту всегда показывается итоговая цена с НДС. Для цены без НДС система добавляет указанную ставку.')
                ->schema([
                    Toggle::make('settings.b2b_enabled')
                        ->label('Публиковать партнёрские цены')
                        ->helperText('Только включённые источники участвуют в B2B-каталоге.')
                        ->default(false),
                    TextInput::make('settings.partner_name')
                        ->label('Поставщик для партнёра')
                        ->placeholder('ООО «СанБизнесГруп»')
                        ->maxLength(255),
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
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with([
                'latestExchangeRun',
                'latestSuccessfulExchangeRun',
            ]))
            ->columns([
                TextColumn::make('name')->label('Источник')->searchable()->sortable(),
                TextColumn::make('code')->label('Код')->badge()->copyable(),
                TextColumn::make('exchange_url')->label('Адрес 1С')
                    ->state(fn (IntegrationSource $record): string => url('/1c/exchange/'.$record->code))
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('driver')->label('Формат')->badge(),
                TextColumn::make('exchange_health')->label('Обмен')
                    ->state(fn (IntegrationSource $record): string => match (true) {
                        $record->latestExchangeRun?->status === 'failed' => 'Ошибка',
                        $record->latestExchangeRun?->status === 'running' => 'Выполняется',
                        ! $record->latestSuccessfulExchangeRun => 'Ещё не было',
                        $record->latestSuccessfulExchangeRun->finished_at?->lt(now()->subMinutes($record->staleAfterMinutes())) => 'Просрочен',
                        default => 'Работает',
                    })
                    ->description(fn (IntegrationSource $record): string => $record->latestSuccessfulExchangeRun?->finished_at
                        ? 'Успешно '.$record->latestSuccessfulExchangeRun->finished_at->timezone('Europe/Minsk')->format('d.m.Y H:i')
                        : 'Ожидается первый автоматический цикл')
                    ->badge()
                    ->color(fn (IntegrationSource $record): string => match (true) {
                        $record->latestExchangeRun?->status === 'failed' => 'danger',
                        $record->latestExchangeRun?->status === 'running' => 'info',
                        ! $record->latestSuccessfulExchangeRun => 'warning',
                        $record->latestSuccessfulExchangeRun->finished_at?->lt(now()->subMinutes($record->staleAfterMinutes())) => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('last_authenticated_at')->label('Авторизация 1С')
                    ->dateTime('d.m.Y H:i:s', 'Europe/Minsk')
                    ->placeholder('Ещё не подключалась')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('schedule')->label('Ожидаемая частота')
                    ->state(fn (IntegrationSource $record): string => $record->scheduleLabel())
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('pricing_rule')->label('Правило цены')
                    ->state(fn (IntegrationSource $record): string => $record->pricingRuleLabel())
                    ->badge()
                    ->color(fn (IntegrationSource $record): string => $record->priceTaxMode() === IntegrationSource::PRICE_TAX_INCLUSIVE
                        ? 'success'
                        : 'warning'),
                IconColumn::make('partner_prices_enabled')->label('B2B-цены')
                    ->state(fn (IntegrationSource $record): bool => $record->isB2bEnabled())
                    ->boolean(),
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

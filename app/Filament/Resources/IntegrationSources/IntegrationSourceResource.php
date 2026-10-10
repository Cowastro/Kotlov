<?php

namespace App\Filament\Resources\IntegrationSources;

use App\Filament\Resources\IntegrationSources\Pages\CreateIntegrationSource;
use App\Filament\Resources\IntegrationSources\Pages\EditIntegrationSource;
use App\Filament\Resources\IntegrationSources\Pages\ListIntegrationSources;
use App\Models\IntegrationSource;
use App\Models\Order;
use App\Models\Supplier;
use App\Services\Integrations\IntegrationFlowHealth;
use App\Services\Integrations\IntegrationOperationsSummary;
use App\Services\Integrations\IntegrationOrderStatusMapper;
use App\Services\Integrations\OrderIntegrationMonitoring;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
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
                    Select::make('supplier_id')
                        ->label('Поставщик-владелец')
                        ->relationship('supplier', 'name')
                        ->searchable()
                        ->preload()
                        ->helperText('Определяет, кто увидит этот источник и его товары в кабинете поставщика.'),
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
                    TextInput::make('settings.order_dispatch_delay_minutes')
                        ->label('Заказ задержан через')
                        ->numeric()
                        ->minValue(5)
                        ->maxValue(1440)
                        ->suffix('мин')
                        ->default(10)
                        ->required(),
                    TextInput::make('settings.order_response_timeout_minutes')
                        ->label('Ответ по заказу просрочен через')
                        ->numeric()
                        ->minValue(5)
                        ->maxValue(10080)
                        ->suffix('мин')
                        ->default(15)
                        ->required(),
                    Toggle::make('settings.allow_order_export')
                        ->label('Отдавать новые заказы этому источнику')
                        ->helperText('Для основного источника onec включено протоколом автоматически.'),
                    Toggle::make('settings.zero_missing_stock_on_complete')
                        ->label('Обнулять отсутствующие остатки')
                        ->helperText('После завершения полного пакета 1С товары, которых нет в предложениях, снимаются с наличия. Порционные файлы обрабатываются безопасно.')
                        ->default(true),
                    Select::make('settings.matching_supplier_code')
                        ->label('База проверенных соответствий')
                        ->options(fn (): array => Supplier::query()->orderBy('name')->pluck('name', 'code')->all())
                        ->placeholder('Отдельные правила этого источника')
                        ->searchable()
                        ->helperText('Ручные привязки запоминаются в выбранной базе. Одинаковые артикулы разных поставщиков не смешиваются.'),
                ])->columns(3),
            Section::make('Сопоставление статусов заказов')
                ->description('Для нестандартных названий из конкретной 1С или API. Точное правило имеет приоритет над общим распознаванием; неизвестное значение не меняет заказ и остаётся в очереди проблем.')
                ->schema([
                    Repeater::make('settings.order_status_rules')
                        ->label('Статусы заказа')
                        ->schema([
                            TextInput::make('source')
                                ->label('Значение в 1С / API')
                                ->placeholder('Передан логисту')
                                ->required()
                                ->maxLength(255),
                            Select::make('target')
                                ->label('Статус KOTLOV')
                                ->options(Order::STATUSES)
                                ->required(),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel('Добавить статус заказа'),
                    Repeater::make('settings.payment_status_rules')
                        ->label('Статусы оплаты')
                        ->schema([
                            TextInput::make('source')
                                ->label('Значение в 1С / API')
                                ->placeholder('Оплата подтверждена')
                                ->required()
                                ->maxLength(255),
                            Select::make('target')
                                ->label('Статус оплаты KOTLOV')
                                ->options(IntegrationOrderStatusMapper::PAYMENT_STATUSES)
                                ->required(),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel('Добавить статус оплаты'),
                ])
                ->columns(2)
                ->collapsed(),
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
            ->columns([
                TextColumn::make('name')->label('Источник')->searchable()->sortable(),
                TextColumn::make('supplier.name')->label('Поставщик-владелец')
                    ->placeholder('Не назначен')
                    ->badge()
                    ->sortable(),
                TextColumn::make('code')->label('Код')->badge()->copyable(),
                TextColumn::make('exchange_url')->label('Адрес 1С')
                    ->state(fn (IntegrationSource $record): string => url('/1c/exchange/'.$record->code))
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('driver')->label('Формат')->badge(),
                TextColumn::make('exchange_health')->label('Обмен')
                    ->state(fn (IntegrationSource $record): string => app(IntegrationOperationsSummary::class)
                        ->healthLabel(app(IntegrationFlowHealth::class)->snapshot($record)['health']))
                    ->description('Отдельно проверяются каталог, заказы и статусы')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Работает' => 'success',
                        'Выполняется' => 'info',
                        'Ошибка' => 'danger',
                        default => 'warning',
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
                TextColumn::make('order_queue')->label('Маршруты заказов')
                    ->state(function (IntegrationSource $record): string {
                        if (! $record->exportsOrders()) {
                            return 'Не используются';
                        }

                        $orders = app(OrderIntegrationMonitoring::class)->snapshot($record);

                        return 'Передать '.$orders['pending_routes'].' · статусы '.$orders['awaiting_responses'];
                    })
                    ->description(function (IntegrationSource $record): ?string {
                        if (! $record->exportsOrders()) {
                            return null;
                        }

                        $orders = app(OrderIntegrationMonitoring::class)->snapshot($record);

                        return $orders['delayed_routes'] > 0 || $orders['overdue_responses'] > 0
                            ? 'Просрочено: '.($orders['delayed_routes'] + $orders['overdue_responses'])
                            : 'Просрочек нет';
                    })
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Не используются' ? 'gray' : 'info')
                    ->toggleable(),
                TextColumn::make('status_rules')->label('Правила статусов')
                    ->state(fn (IntegrationSource $record): string => $record->statusRulesCount() > 0
                        ? $record->statusRulesCount().' настроено'
                        : 'Общие правила')
                    ->description('Неизвестные значения блокируются')
                    ->badge()
                    ->color(fn (IntegrationSource $record): string => $record->statusRulesCount() > 0 ? 'success' : 'gray')
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

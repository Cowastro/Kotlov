<?php

namespace App\Filament\Resources\Suppliers;

use App\Filament\Resources\IntegrationSources\IntegrationSourceResource;
use App\Filament\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Resources\Suppliers\Pages\ListSuppliers;
use App\Models\IntegrationSource;
use App\Models\Supplier;
use App\Services\Integrations\IntegrationFlowHealth;
use App\Services\Integrations\IntegrationOperationsSummary;
use App\Services\Orders\SupplierAutoTransferManager;
use App\Services\Orders\SupplierAutoTransferReadiness;
use App\Services\Pricing\CurrencyPriceConverter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SupplierResource extends Resource
{
    /** @var \WeakMap<Supplier, array<string, mixed>>|null */
    private static ?\WeakMap $autoTransferReadinessCache = null;

    protected static ?string $model = Supplier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $navigationLabel = 'Поставщики';

    protected static ?string $modelLabel = 'поставщик';

    protected static ?string $pluralModelLabel = 'Поставщики';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): ?string
    {
        return 'Каталог';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount('supplierProducts')
            ->with('latestChannelTransition')
            ->with([
                'integrationSources' => fn ($query) => $query
                    ->withCount(['products as linked_products_count' => fn ($products) => $products
                        ->where('match_status', 'matched')])
                    ->with(['latestExchangeRun', 'latestSuccessfulExchangeRun']),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->label('Код')
                ->helperText('Используется в командах синхронизации, например: elicon')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),

            TextInput::make('name')
                ->label('Название')
                ->required()
                ->maxLength(255),

            Select::make('currency')
                ->label('Валюта поставщика')
                ->options(array_combine(
                    CurrencyPriceConverter::SUPPORTED_CURRENCIES,
                    CurrencyPriceConverter::SUPPORTED_CURRENCIES
                ))
                ->default(CurrencyPriceConverter::BASE_CURRENCY)
                ->required()
                ->live()
                ->afterStateUpdated(function ($state, callable $set) {
                    if ($state === CurrencyPriceConverter::BASE_CURRENCY) {
                        $set('currency_rate', 1);
                    }
                }),

            TextInput::make('currency_rate')
                ->label('Курс к BYN')
                ->helperText('Цена поставщика × курс = цена на сайте в BYN. Для BYN курс = 1. Пример: 100 RUB × 0.035 = 3.50 BYN')
                ->numeric()
                ->step('0.0001')
                ->minValue(0.0001)
                ->default(1)
                ->required()
                ->disabled(fn (callable $get) => $get('currency') === CurrencyPriceConverter::BASE_CURRENCY)
                ->dehydrated(),

            TextInput::make('contact')
                ->label('Контакт / сайт')
                ->maxLength(255),

            TextInput::make('marketplace_commission_rate')
                ->label('Комиссия прямой продажи')
                ->helperText('Процент KOTLOV.BY при прямой передаче заказа поставщику. Пустое значение блокирует подтверждение взаиморасчёта, а не считается 0%.')
                ->numeric()
                ->minValue(0)
                ->maxValue(100)
                ->step('0.0001')
                ->suffix('%'),

            TextInput::make('settlement_terms_days')
                ->label('Срок взаиморасчёта')
                ->helperText('Через сколько календарных дней производится расчёт с поставщиком.')
                ->numeric()
                ->integer()
                ->minValue(0)
                ->maxValue(365)
                ->default(14)
                ->suffix('дн.'),

            Select::make('users')
                ->label('Пользователи кабинета')
                ->relationship(
                    name: 'users',
                    titleAttribute: 'email',
                    modifyQueryUsing: fn ($query) => $query
                        ->where('role', 'supplier')
                        ->where('is_active', true)
                        ->orderBy('name'),
                )
                ->multiple()
                ->searchable(['name', 'email'])
                ->preload()
                ->helperText('Каждый пользователь увидит только данные назначенных ему поставщиков.'),

            Toggle::make('is_active')
                ->label('Активен')
                ->default(true),

            Textarea::make('notes')
                ->label('Заметки')
                ->rows(3)
                ->columnSpanFull(),

            Textarea::make('settlement_notes')
                ->label('Условия взаиморасчётов')
                ->helperText('Внутренняя памятка: комиссия, порядок возвратов, документы и особые договорённости.')
                ->rows(3)
                ->columnSpanFull(),

            Placeholder::make('automatic_transfer_status')
                ->label('Автоматическая передача заказов')
                ->content(fn (?Supplier $record): string => $record
                    ? self::autoTransferReadinessLabel($record)
                    : 'Выключена до завершения контрольного периода')
                ->helperText('Настройка меняется отдельным подтверждаемым действием администратора. Пустые или новые поставщики никогда не включаются автоматически.')
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Поставщик')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('code')
                    ->label('Код')
                    ->searchable()
                    ->copyable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('currency')
                    ->label('Валюта')
                    ->badge()
                    ->color(fn (?string $state) => $state === CurrencyPriceConverter::BASE_CURRENCY ? 'success' : 'warning'),

                TextColumn::make('currency_rate')
                    ->label('Курс к BYN')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => rtrim(rtrim(number_format((float) $state, 4, '.', ' '), '0'), '.')),

                TextColumn::make('data_channel')
                    ->label('Канал данных')
                    ->state(fn (Supplier $record): string => self::dataChannelLabel($record))
                    ->description(fn (Supplier $record): ?string => self::integrationSourceNames($record))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        '1С' => 'success',
                        'Смешанный' => 'warning',
                        'Старый канал' => 'gray',
                        default => 'info',
                    }),

                TextColumn::make('integration_health')
                    ->label('Статус 1С')
                    ->state(fn (Supplier $record): string => self::integrationHealthLabel($record))
                    ->description(fn (Supplier $record): ?string => self::lastSuccessfulExchangeLabel($record))
                    ->badge()
                    ->color(fn (Supplier $record): string => self::integrationHealthColor($record)),

                TextColumn::make('supplier_products_count')
                    ->label('Связок товаров')
                    ->state(fn (Supplier $record): string => 'Старые: '.number_format((int) $record->supplier_products_count, 0, ',', ' '))
                    ->description(fn (Supplier $record): string => '1С: '.number_format(self::linkedIntegrationProductsCount($record), 0, ',', ' '))
                    ->alignRight(),

                TextColumn::make('marketplace_commission_rate')
                    ->label('Комиссия')
                    ->formatStateUsing(fn ($state): string => $state !== null
                        ? rtrim(rtrim(number_format((float) $state, 4, '.', ' '), '0'), '.').' %'
                        : 'Не задана')
                    ->color(fn ($state): string => $state === null ? 'warning' : 'success')
                    ->description(fn (Supplier $record): string => 'Расчёт через '.(int) $record->settlement_terms_days.' дн.')
                    ->toggleable(),

                TextColumn::make('automatic_order_transfer_enabled')
                    ->label('Автопередача')
                    ->state(fn (Supplier $record): string => self::autoTransferReadinessLabel($record))
                    ->description(fn (Supplier $record): string => self::autoTransferReadinessDescription($record))
                    ->badge()
                    ->color(fn (Supplier $record): string => match (true) {
                        $record->automatic_order_transfer_enabled && self::autoTransferReadiness($record)['ready'] => 'success',
                        $record->automatic_order_transfer_enabled => 'warning',
                        self::autoTransferReadiness($record)['ready'] => 'info',
                        default => 'gray',
                    })
                    ->wrap(),

                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Обновлён')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->recordActions([
                Action::make('fetchNbrb')
                    ->label('Курс НБРБ')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('info')
                    ->tooltip('Загрузить официальный курс НБРБ и пересчитать BYN цены')
                    ->visible(fn (Supplier $record) => $record->currency !== CurrencyPriceConverter::BASE_CURRENCY)
                    ->action(function (Supplier $record) {
                        $url = 'https://api.nbrb.by/exrates/rates/'.$record->currency.'?parammode=2';
                        $resp = Http::timeout(10)->get($url);
                        if (! $resp->ok() || ! ($rate = $resp->json('Cur_OfficialRate'))) {
                            Notification::make()
                                ->title('Ошибка НБРБ')
                                ->body('Не удалось получить курс '.$record->currency)
                                ->danger()
                                ->send();

                            return;
                        }
                        $rate = round((float) $rate, 4);
                        $record->update(['currency_rate' => $rate]);

                        // Пересчитать price_byn в supplier_products
                        DB::table('supplier_products')
                            ->where('supplier_id', $record->id)
                            ->where('currency', $record->currency)
                            ->update([
                                'currency_rate' => $rate,
                                'price_byn' => DB::raw("ROUND(price * {$rate}, 2)"),
                                'updated_at' => now(),
                            ]);

                        // Обновить products.price
                        $sps = DB::table('supplier_products')
                            ->where('supplier_id', $record->id)
                            ->whereNotNull('product_id')
                            ->get(['product_id', 'price_byn']);

                        foreach ($sps as $sp) {
                            if ($sp->price_byn > 0) {
                                DB::table('products')
                                    ->where('id', $sp->product_id)
                                    ->update(['price' => $sp->price_byn, 'updated_at' => now()]);
                            }
                        }

                        Notification::make()
                            ->title("Курс обновлён: 1 {$record->currency} = {$rate} BYN")
                            ->body("Пересчитано {$sps->count()} товаров.")
                            ->success()
                            ->send();
                    }),

                Action::make('integration')
                    ->label(fn (Supplier $record): string => $record->integrationSources->isEmpty()
                        ? 'Подключить 1С'
                        : 'Настроить интеграцию')
                    ->icon(Heroicon::OutlinedSignal)
                    ->color('info')
                    ->url(function (Supplier $record): string {
                        $source = $record->integrationSources
                            ->firstWhere('driver', 'commerceml')
                            ?? $record->integrationSources->first();

                        return $source
                            ? IntegrationSourceResource::getUrl('edit', ['record' => $source])
                            : IntegrationSourceResource::getUrl('create', ['supplier_id' => $record->id]);
                    }),

                self::automaticOrderTransferAction(),

                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
            'edit' => EditSupplier::route('/{record}/edit'),
        ];
    }

    private static function dataChannelLabel(Supplier $supplier): string
    {
        $hasLegacy = (int) $supplier->supplier_products_count > 0;
        $activeSources = $supplier->integrationSources->where('is_active', true);
        $hasIntegration = $activeSources->isNotEmpty();

        if ($hasIntegration && ! $supplier->usesLegacyChannel()) {
            return $activeSources->contains('driver', 'commerceml') ? '1С' : 'API / файл';
        }

        return match (true) {
            $hasLegacy && $hasIntegration => 'Смешанный',
            $activeSources->contains('driver', 'commerceml') => '1С',
            $hasIntegration => 'API / файл',
            $hasLegacy => 'Старый канал',
            default => 'Не настроен',
        };
    }

    private static function integrationSourceNames(Supplier $supplier): ?string
    {
        $names = $supplier->integrationSources->pluck('name')->filter()->implode(', ');

        return $names !== '' ? $names : null;
    }

    private static function integrationHealthLabel(Supplier $supplier): string
    {
        $source = self::oneCSource($supplier);
        if (! $source) {
            return 'Не подключена';
        }
        if (! $source->is_active) {
            return 'Отключена';
        }

        $health = app(IntegrationFlowHealth::class)->snapshot($source)['health'];

        return app(IntegrationOperationsSummary::class)->healthLabel($health);
    }

    private static function integrationHealthColor(Supplier $supplier): string
    {
        $source = self::oneCSource($supplier);
        if (! $source || ! $source->is_active) {
            return 'gray';
        }

        return app(IntegrationOperationsSummary::class)->healthColor(
            app(IntegrationFlowHealth::class)->snapshot($source)['health'],
        );
    }

    private static function lastSuccessfulExchangeLabel(Supplier $supplier): ?string
    {
        $lastSuccess = $supplier->integrationSources
            ->where('driver', 'commerceml')
            ->pluck('latestSuccessfulExchangeRun')
            ->filter()
            ->sortByDesc('finished_at')
            ->first()?->finished_at;

        return $lastSuccess
            ? 'Последний успешный: '.$lastSuccess->timezone('Europe/Minsk')->format('d.m.Y H:i')
            : null;
    }

    private static function linkedIntegrationProductsCount(Supplier $supplier): int
    {
        return (int) $supplier->integrationSources
            ->where('driver', 'commerceml')
            ->sum(
                fn ($source): int => (int) ($source->linked_products_count ?? 0),
            );
    }

    private static function oneCSource(Supplier $supplier): ?IntegrationSource
    {
        return $supplier->integrationSources
            ->where('driver', 'commerceml')
            ->sortByDesc('is_active')
            ->first();
    }

    public static function automaticOrderTransferAction(): Action
    {
        return Action::make('automaticOrderTransfer')
            ->label(fn (Supplier $record): string => $record->automatic_order_transfer_enabled
                ? 'Отключить автопередачу'
                : 'Включить автопередачу')
            ->icon(fn (Supplier $record): string => $record->automatic_order_transfer_enabled
                ? 'heroicon-o-pause-circle'
                : 'heroicon-o-play-circle')
            ->color(fn (Supplier $record): string => $record->automatic_order_transfer_enabled ? 'danger' : 'success')
            ->visible(fn (): bool => auth()->user()?->isAdmin() === true)
            ->disabled(fn (Supplier $record): bool => ! $record->automatic_order_transfer_enabled
                && ! self::autoTransferReadiness($record)['ready'])
            ->tooltip(fn (Supplier $record): string => self::autoTransferReadinessDescription($record))
            ->requiresConfirmation()
            ->modalHeading(fn (Supplier $record): string => $record->automatic_order_transfer_enabled
                ? 'Отключить автоматическую передачу?'
                : 'Включить автоматическую передачу?')
            ->modalDescription(fn (Supplier $record): string => self::autoTransferReadinessDescription($record))
            ->schema([
                Textarea::make('note')
                    ->label('Причина решения')
                    ->rows(3)
                    ->minLength(3)
                    ->maxLength(1000)
                    ->required(),
            ])
            ->action(function (Supplier $record, array $data): void {
                $enabled = ! $record->automatic_order_transfer_enabled;
                app(SupplierAutoTransferManager::class)->setEnabled(
                    $record,
                    $enabled,
                    auth()->user(),
                    $data['note'],
                );
                self::$autoTransferReadinessCache = null;

                Notification::make()
                    ->title($enabled ? 'Автоматическая передача включена' : 'Автоматическая передача отключена')
                    ->body('Решение сохранено в неизменяемом журнале поставщика.')
                    ->color($enabled ? 'success' : 'warning')
                    ->send();
            });
    }

    /** @return array<string, mixed> */
    private static function autoTransferReadiness(Supplier $supplier): array
    {
        self::$autoTransferReadinessCache ??= new \WeakMap;

        return self::$autoTransferReadinessCache[$supplier]
            ??= app(SupplierAutoTransferReadiness::class)->snapshot($supplier);
    }

    private static function autoTransferReadinessLabel(Supplier $supplier): string
    {
        $readiness = self::autoTransferReadiness($supplier);

        return match (true) {
            $supplier->automatic_order_transfer_enabled && $readiness['ready'] => 'Включена',
            $supplier->automatic_order_transfer_enabled => 'Приостановлена системой',
            $readiness['ready'] => 'Готова к включению',
            default => 'Контроль '.$readiness['successful_count'].'/'.$readiness['minimum_successful_requests'],
        };
    }

    private static function autoTransferReadinessDescription(Supplier $supplier): string
    {
        $readiness = self::autoTransferReadiness($supplier);
        if ($supplier->automatic_order_transfer_enabled && $readiness['ready']) {
            return collect([
                'Одобрено '.$supplier->automatic_order_transfer_approved_at?->timezone('Europe/Minsk')->format('d.m.Y H:i'),
                $supplier->automatic_order_transfer_note,
            ])->filter()->implode(' · ');
        }
        if ($supplier->automatic_order_transfer_enabled) {
            return 'Новые заявки не передаются: '.implode(' · ', $readiness['blockers']);
        }
        if ($readiness['ready']) {
            return 'Контрольный период успешно завершён. Включение всё равно требует отдельного подтверждения администратора.';
        }

        return implode(' · ', $readiness['blockers']);
    }
}

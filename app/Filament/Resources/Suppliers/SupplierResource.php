<?php

namespace App\Filament\Resources\Suppliers;

use App\Filament\Resources\IntegrationSources\IntegrationSourceResource;
use App\Filament\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Resources\Suppliers\Pages\ListSuppliers;
use App\Filament\Resources\Suppliers\Pages\ViewSupplier;
use App\Filament\Resources\Suppliers\RelationManagers\PriceSourcesRelationManager;
use App\Filament\Resources\Suppliers\RelationManagers\PriceSyncsRelationManager;
use App\Filament\Resources\Suppliers\RelationManagers\SupplierProductsRelationManager;
use App\Models\IntegrationSource;
use App\Models\Supplier;
use App\Models\SupplierSource;
use App\Models\SupplierSync;
use App\Services\Integrations\IntegrationFlowHealth;
use App\Services\Integrations\IntegrationOperationsSummary;
use App\Services\Orders\SupplierAutoTransferManager;
use App\Services\Orders\SupplierAutoTransferReadiness;
use App\Services\Pricing\CurrencyPriceConverter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
                'brands',
                'crmCounterparty.contactPoints',
                'sources',
                'syncs',
                'integrationSources' => fn ($query) => $query
                    ->withCount(['products as linked_products_count' => fn ($products) => $products
                        ->where('match_status', 'matched')])
                    ->with(['latestExchangeRun', 'latestSuccessfulExchangeRun']),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Компания и бренды')
                ->description('Единая карточка компании, с которой работает отдел продаж.')
                ->schema([
                    TextInput::make('name')->label('Название')->required()->maxLength(255)->columnSpan(2),
                    TextInput::make('code')->label('Код')->required()->unique(ignoreRecord: true)->maxLength(255)
                        ->helperText('Технический код синхронизации, например: bania или ligmet.'),
                    Toggle::make('is_active')->label('Работаем с компанией')->default(true),
                    Select::make('brands')
                        ->label('Бренды поставщика')
                        ->relationship('brands', 'name', modifyQueryUsing: fn ($query) => $query->orderBy('name'))
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->helperText('Укажите все бренды, которые можно заказывать у этой компании.')
                        ->columnSpanFull(),
                    Select::make('crm_counterparty_id')
                        ->label('Контрагент CRM')
                        ->relationship(
                            name: 'crmCounterparty',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn ($query) => $query
                                ->whereIn('classification', ['supplier', 'unclassified'])
                                ->orderBy('name'),
                        )
                        ->searchable(['name', 'public_name', 'legal_name', 'unp'])
                        ->preload()
                        ->helperText('Основные телефоны и email берутся из CRM.')
                        ->columnSpan(2),
                    TextInput::make('contact')->label('Сайт или общий контакт')->maxLength(255)->columnSpan(2),
                ])->columns(4),

            Section::make('Как связаться')
                ->description('Чаты и рабочие контакты из памятки поставщиков. Телефоны и email организации лучше хранить в связанной карточке CRM.')
                ->schema([
                    Repeater::make('communication_channels')
                        ->hiddenLabel()
                        ->schema([
                            Select::make('type')->label('Канал')->options([
                                'viber' => 'Viber',
                                'telegram' => 'Telegram',
                                'phone' => 'Телефон',
                                'email' => 'Email',
                                'website' => 'Сайт',
                                'other' => 'Другое',
                            ])->required(),
                            TextInput::make('name')->label('Чат или контакт')->placeholder('Лигмет или Дмитрий')->maxLength(255),
                            TextInput::make('value')->label('Адрес / номер')->required()->maxLength(255),
                            TextInput::make('note')->label('Комментарий')->maxLength(255),
                        ])
                        ->columns(4)
                        ->defaultItems(0)
                        ->addActionLabel('Добавить канал связи')
                        ->reorderable(false),
                ]),

            Section::make('Цены и взаиморасчёты')
                ->description('Ссылки на сами прайсы и остатки добавляются после сохранения карточки в блоке «Прайсы и остатки».')
                ->schema([
                    Select::make('currency')
                        ->label('Валюта поставщика')
                        ->options(array_combine(CurrencyPriceConverter::SUPPORTED_CURRENCIES, CurrencyPriceConverter::SUPPORTED_CURRENCIES))
                        ->default(CurrencyPriceConverter::BASE_CURRENCY)->required()->live()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state === CurrencyPriceConverter::BASE_CURRENCY) {
                                $set('currency_rate', 1);
                            }
                        }),
                    TextInput::make('currency_rate')->label('Курс к BYN')->numeric()->step('0.0001')->minValue(0.0001)
                        ->default(1)->required()
                        ->disabled(fn (callable $get) => $get('currency') === CurrencyPriceConverter::BASE_CURRENCY)
                        ->dehydrated(),
                    TextInput::make('marketplace_commission_rate')->label('Комиссия прямой продажи')->numeric()
                        ->minValue(0)->maxValue(100)->step('0.0001')->suffix('%'),
                    TextInput::make('settlement_terms_days')->label('Срок взаиморасчёта')->numeric()->integer()
                        ->minValue(0)->maxValue(365)->default(14)->suffix('дн.'),
                    Textarea::make('settlement_notes')->label('Условия взаиморасчётов')->rows(3)->columnSpanFull(),
                    Placeholder::make('automatic_transfer_status')->label('Автоматическая передача заказов')
                        ->content(fn (?Supplier $record): string => $record
                            ? self::autoTransferReadinessLabel($record)
                            : 'Выключена до завершения контрольного периода')
                        ->columnSpanFull(),
                ])->columns(2),

            Section::make('Доступ и внутренняя информация')
                ->schema([
                    Select::make('users')
                        ->label('Пользователи кабинета')
                        ->relationship(
                            name: 'users',
                            titleAttribute: 'email',
                            modifyQueryUsing: fn ($query) => $query
                                ->where('role', 'supplier')->where('is_active', true)->orderBy('name'),
                        )
                        ->multiple()->searchable(['name', 'email'])->preload()
                        ->helperText('Каждый пользователь увидит только назначенных ему поставщиков.'),
                    Textarea::make('notes')->label('Что важно знать менеджеру')->rows(4),
                ])->columns(2),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Компания')
                ->columns(3)
                ->schema([
                    TextEntry::make('name')->label('Поставщик')->weight('bold'),
                    TextEntry::make('brands.name')->label('Бренды')->badge()->placeholder('Не указаны')->columnSpan(2),
                    TextEntry::make('crmCounterparty.name')->label('Карточка CRM')->placeholder('Не связана'),
                    TextEntry::make('contact_summary')->label('Телефоны и email')
                        ->state(fn (Supplier $record): ?string => $record->contactSummary())
                        ->placeholder('Не указаны')->copyable(),
                    IconEntry::make('is_active')->label('Работаем')->boolean(),
                    TextEntry::make('notes')->label('Что важно знать менеджеру')->placeholder('—')->columnSpanFull(),
                ]),
            Section::make('Каналы общения')
                ->schema([
                    RepeatableEntry::make('communication_channels')->hiddenLabel()->schema([
                        TextEntry::make('type')->label('Канал')->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                            'viber' => 'Viber', 'telegram' => 'Telegram', 'phone' => 'Телефон',
                            'email' => 'Email', 'website' => 'Сайт', default => 'Другое',
                        }),
                        TextEntry::make('name')->label('Чат или контакт')->placeholder('—'),
                        TextEntry::make('value')->label('Адрес / номер')->copyable(),
                        TextEntry::make('note')->label('Комментарий')->placeholder('—'),
                    ])->columns(4),
                ]),
            Section::make('Прайсы и синхронизация')
                ->description('Ниже показаны рабочие ссылки на прайсы, расписание и технические каналы обмена.')
                ->schema([
                    RepeatableEntry::make('sources')->label('Прайсы и остатки')->schema([
                        TextEntry::make('name')->label('Источник')->weight('bold'),
                        TextEntry::make('type')->label('Формат')->badge()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'google_sheet' => 'Google Таблица', 'google_drive' => 'Google Drive',
                                'excel' => 'Excel', 'csv' => 'CSV', 'api' => 'API', 'email' => 'Email', default => $state,
                            }),
                        TextEntry::make('config.url')->label('Ссылка')->url(fn (?string $state): ?string => $state)->openUrlInNewTab()->placeholder('—'),
                        TextEntry::make('schedule_cron')->label('Обновление')->placeholder('По запросу'),
                        TextEntry::make('last_run_at')->label('Последний импорт')->dateTime('d.m.Y H:i', 'Europe/Minsk')->placeholder('Нет запусков'),
                    ])->columns(5),
                    RepeatableEntry::make('integrationSources')->label('Каналы интеграции')->schema([
                        TextEntry::make('name')->label('Канал')->weight('bold'),
                        TextEntry::make('driver')->label('Формат')->badge(),
                        TextEntry::make('schedule')->label('Частота')->state(fn (IntegrationSource $record): string => $record->scheduleLabel()),
                        IconEntry::make('is_active')->label('Включён')->boolean(),
                    ])->columns(4),
                    RepeatableEntry::make('syncs')->label('Автоматические обновления')->schema([
                        TextEntry::make('title')->label('Сценарий')->weight('bold'),
                        TextEntry::make('last_status')->label('Статус')->badge()
                            ->state(fn (SupplierSync $record): string => $record->statusLabel())
                            ->color(fn (SupplierSync $record): string => $record->statusColor()),
                        TextEntry::make('last_run_at')->label('Последний запуск')->dateTime('d.m.Y H:i', 'Europe/Minsk')->placeholder('Нет запусков'),
                    ])->columns(3),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Компания')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Supplier $record): string => self::supplierIdentityDescription($record))
                    ->url(fn (Supplier $record): string => self::getUrl('view', ['record' => $record])),

                TextColumn::make('brands.name')
                    ->label('Бренды')
                    ->badge()
                    ->separator(',')
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->searchable()
                    ->placeholder('Нужно указать'),

                TextColumn::make('manager_contacts')
                    ->label('Контакты')
                    ->state(fn (Supplier $record): array => self::managerContacts($record))
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->placeholder('Нужно заполнить')
                    ->description(fn (Supplier $record): ?string => $record->crmCounterparty?->name
                        ? 'CRM: '.$record->crmCounterparty->name
                        : 'Карточка CRM не связана')
                    ->wrap(),

                TextColumn::make('data_channel')
                    ->label('Прайсы и синхронизация')
                    ->state(fn (Supplier $record): string => self::priceSourceLabel($record))
                    ->description(fn (Supplier $record): string => self::priceSourceDescription($record))
                    ->url(fn (Supplier $record): ?string => self::primaryPriceSource($record)?->sourceUrl())
                    ->openUrlInNewTab()
                    ->icon(fn (Supplier $record): ?Heroicon => self::primaryPriceSource($record)?->sourceUrl()
                        ? Heroicon::OutlinedArrowTopRightOnSquare
                        : null)
                    ->color(fn (Supplier $record): string => self::primaryPriceSource($record)?->sourceUrl()
                        ? 'info'
                        : 'gray')
                    ->tooltip(fn (Supplier $record): string => self::primaryPriceSource($record)?->sourceUrl()
                        ? 'Открыть прайс или остатки в новой вкладке'
                        : 'Добавьте внешнюю ссылку в карточке поставщика')
                    ->wrap(),

                TextColumn::make('supplier_products_count')
                    ->label('Товары')
                    ->state(fn (Supplier $record): string => number_format(
                        (int) $record->supplier_products_count + self::linkedIntegrationProductsCount($record),
                        0,
                        ',',
                        ' ',
                    ).' связок')
                    ->description(fn (Supplier $record): string => 'Прайсы: '.number_format((int) $record->supplier_products_count, 0, ',', ' ')
                        .' · 1С: '.number_format(self::linkedIntegrationProductsCount($record), 0, ',', ' '))
                    ->alignRight()
                    ->sortable(),

                TextColumn::make('directory_status')
                    ->label('Статус')
                    ->state(fn (Supplier $record): string => $record->is_active ? 'Работаем' : 'Отключён')
                    ->description(fn (Supplier $record): string => $record->automatic_order_transfer_enabled
                        ? 'Автопередача заказов включена'
                        : 'Автопередача выключена')
                    ->badge()
                    ->color(fn (Supplier $record): string => $record->is_active ? 'success' : 'gray'),
            ])
            ->searchPlaceholder('Введите компанию или бренд...')
            ->filters([
                SelectFilter::make('brands')
                    ->label('Бренд')
                    ->relationship('brands', 'name', modifyQueryUsing: fn ($query) => $query->orderBy('name'))
                    ->searchable()
                    ->preload()
                    ->multiple(),
            ])
            ->defaultSort('name')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50])
            ->striped()
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('integration')
                        ->label(fn (Supplier $record): string => $record->integrationSources->isEmpty()
                            ? 'Подключить 1С'
                            : 'Настроить интеграцию')
                        ->icon(Heroicon::OutlinedSignal)
                        ->color('info')
                        ->visible(fn (Supplier $record): bool => self::canEdit($record))
                        ->url(function (Supplier $record): string {
                            $source = $record->integrationSources
                                ->firstWhere('driver', 'commerceml')
                                ?? $record->integrationSources->first();

                            return $source
                                ? IntegrationSourceResource::getUrl('edit', ['record' => $source])
                                : IntegrationSourceResource::getUrl('create', ['supplier_id' => $record->id]);
                        }),
                    Action::make('fetchNbrb')
                        ->label('Обновить курс НБРБ')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->color('info')
                        ->visible(fn (Supplier $record): bool => self::canEdit($record)
                            && $record->currency !== CurrencyPriceConverter::BASE_CURRENCY)
                        ->action(fn (Supplier $record) => self::refreshNbrbRate($record)),
                    self::automaticOrderTransferAction(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
            'view' => ViewSupplier::route('/{record}'),
            'edit' => EditSupplier::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            SupplierProductsRelationManager::class,
            PriceSourcesRelationManager::class,
            PriceSyncsRelationManager::class,
        ];
    }

    private static function supplierIdentityDescription(Supplier $supplier): string
    {
        $currency = $supplier->currency ?: CurrencyPriceConverter::BASE_CURRENCY;
        $rate = $currency !== CurrencyPriceConverter::BASE_CURRENCY
            ? ' · курс '.rtrim(rtrim(number_format((float) $supplier->currency_rate, 4, '.', ' '), '0'), '.')
            : '';

        return $supplier->code.' · '.$currency.$rate;
    }

    /** @return array<int, string> */
    private static function managerContacts(Supplier $supplier): array
    {
        $structuredChannels = collect($supplier->communication_channels ?? [])
            ->map(function (array $channel): string {
                $label = trim((string) ($channel['name'] ?? ''));
                $value = trim((string) ($channel['value'] ?? ''));

                return $label !== '' ? $label.': '.$value : $value;
            });

        return collect([
            ...$supplier->contactPhoneNumbers(),
            ...$supplier->contactEmailAddresses(),
            ...$structuredChannels,
        ])->filter()->unique()->values()->all();
    }

    private static function syncSummary(Supplier $supplier): string
    {
        $oneCSource = self::oneCSource($supplier);

        return collect([
            self::hasActivePriceSource($supplier) ? self::priceSourcesLabel($supplier) : 'Прайсы не указаны',
            $oneCSource ? '1С: '.self::integrationHealthLabel($supplier) : null,
            self::lastPriceUpdateLabel($supplier),
        ])->filter()->implode(' · ');
    }

    private static function primaryPriceSource(Supplier $supplier): ?SupplierSource
    {
        return $supplier->sources
            ->where('is_active', true)
            ->sortByDesc(fn (SupplierSource $source): bool => filled($source->sourceUrl()))
            ->first();
    }

    private static function priceSourceLabel(Supplier $supplier): string
    {
        $source = self::primaryPriceSource($supplier);

        if (! $source) {
            return self::dataChannelLabel($supplier);
        }

        $additionalSources = $supplier->sources->where('is_active', true)->count() - 1;

        return $source->name.($additionalSources > 0 ? ' +'.$additionalSources : '');
    }

    private static function priceSourceDescription(Supplier $supplier): string
    {
        $source = self::primaryPriceSource($supplier);

        if (! $source) {
            return self::syncSummary($supplier);
        }

        return collect([
            $source->typeLabel(),
            $source->scheduleLabel(),
            $supplier->syncs->where('is_active', true)->isNotEmpty()
                ? 'автообновление включено'
                : null,
        ])->filter()->implode(' · ');
    }

    private static function refreshNbrbRate(Supplier $supplier): void
    {
        $url = 'https://api.nbrb.by/exrates/rates/'.$supplier->currency.'?parammode=2';
        $response = Http::timeout(10)->get($url);

        if (! $response->ok() || ! ($rate = $response->json('Cur_OfficialRate'))) {
            Notification::make()
                ->title('Ошибка НБРБ')
                ->body('Не удалось получить курс '.$supplier->currency)
                ->danger()
                ->send();

            return;
        }

        $rate = round((float) $rate, 4);
        $supplier->update(['currency_rate' => $rate]);

        DB::table('supplier_products')
            ->where('supplier_id', $supplier->id)
            ->where('currency', $supplier->currency)
            ->update([
                'currency_rate' => $rate,
                'price_byn' => DB::raw("ROUND(price * {$rate}, 2)"),
                'updated_at' => now(),
            ]);

        $supplierProducts = DB::table('supplier_products')
            ->where('supplier_id', $supplier->id)
            ->whereNotNull('product_id')
            ->get(['product_id', 'price_byn']);

        foreach ($supplierProducts as $supplierProduct) {
            if ($supplierProduct->price_byn > 0) {
                DB::table('products')
                    ->where('id', $supplierProduct->product_id)
                    ->update(['price' => $supplierProduct->price_byn, 'updated_at' => now()]);
            }
        }

        Notification::make()
            ->title("Курс обновлён: 1 {$supplier->currency} = {$rate} BYN")
            ->body("Пересчитано {$supplierProducts->count()} товаров.")
            ->success()
            ->send();
    }

    private static function dataChannelLabel(Supplier $supplier): string
    {
        $hasLegacy = (int) $supplier->supplier_products_count > 0;
        $activeSources = $supplier->integrationSources->where('is_active', true);
        $hasIntegration = $activeSources->isNotEmpty();
        $hasPriceChannel = self::hasActivePriceSource($supplier);

        if ($hasIntegration && ! $supplier->usesLegacyChannel() && ! $hasPriceChannel) {
            return $activeSources->contains('driver', 'commerceml') ? '1С' : 'API / файл';
        }

        return match (true) {
            ($hasLegacy || $hasPriceChannel) && $hasIntegration => 'Смешанный',
            $activeSources->contains('driver', 'commerceml') => '1С',
            $hasIntegration => 'API / файл',
            $hasPriceChannel => 'Прайс / файл',
            $hasLegacy => 'Старый канал',
            default => 'Не настроен',
        };
    }

    private static function hasActivePriceSource(Supplier $supplier): bool
    {
        return $supplier->sources->contains('is_active', true)
            || $supplier->syncs->contains('is_active', true);
    }

    private static function priceSourcesLabel(Supplier $supplier): string
    {
        $manual = $supplier->sources->where('is_active', true)->count();
        $automatic = $supplier->syncs->where('is_active', true)->count();

        if ($manual === 0 && $automatic === 0) {
            return 'Не указаны';
        }

        return collect([
            $manual > 0 ? 'ссылок '.$manual : null,
            $automatic > 0 ? 'авто '.$automatic : null,
        ])->filter()->implode(' · ');
    }

    private static function lastPriceUpdateLabel(Supplier $supplier): ?string
    {
        $lastRun = $supplier->sources->pluck('last_run_at')
            ->merge($supplier->syncs->pluck('last_run_at'))
            ->filter()
            ->sortDesc()
            ->first();

        return $lastRun
            ? 'Последнее обновление '.($lastRun instanceof \DateTimeInterface ? $lastRun->format('d.m.Y H:i') : (string) $lastRun)
            : 'Дата обновления пока не зафиксирована';
    }

    private static function supplierPhones(Supplier $supplier): string
    {
        return implode(', ', $supplier->contactPhoneNumbers());
    }

    private static function supplierEmails(Supplier $supplier): string
    {
        return implode(', ', $supplier->contactEmailAddresses());
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

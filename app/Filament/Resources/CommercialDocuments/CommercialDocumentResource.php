<?php

namespace App\Filament\Resources\CommercialDocuments;

use App\Filament\Resources\CommercialDocuments\Pages\CreateCommercialDocument;
use App\Filament\Resources\CommercialDocuments\Pages\EditCommercialDocument;
use App\Filament\Resources\CommercialDocuments\Pages\ListCommercialDocuments;
use App\Filament\Resources\CommercialDocuments\Pages\ViewCommercialDocument;
use App\Models\CommercialDocument;
use App\Models\Product;
use App\Services\CommercialDocuments\CommercialOfferCopilotService;
use App\Services\CommercialDocuments\QuickCommercialOfferService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class CommercialDocumentResource extends Resource
{
    protected static ?string $model = CommercialDocument::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyDollar;

    protected static ?string $navigationLabel = 'КП и счета';

    protected static ?string $modelLabel = 'документ';

    protected static ?string $pluralModelLabel = 'Коммерческие предложения и счета';

    protected static ?string $recordTitleAttribute = 'number';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return 'Продажи';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['manager', 'items', 'mailThread']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Документ')
                ->icon('heroicon-o-document-text')
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    Hidden::make('crm_mail_thread_id'),
                    Select::make('type')->label('Тип')->options(CommercialDocument::TYPES)->default('quote')->required()->native(false)->live(),
                    Select::make('status')->label('Статус')->options(CommercialDocument::STATUSES)->default('draft')->required()->native(false),
                    DatePicker::make('issued_at')->label('Дата')->default(today())->required()->native(false),
                    DatePicker::make('valid_until')->label('Действует до')->default(today()->addDays(10))->native(false),
                    Select::make('manager_id')->label('Менеджер')->relationship('manager', 'name')->default(fn () => auth()->id())->searchable()->preload(),
                    TextInput::make('payment_terms')->label('Условия оплаты')->default('Безналичный расчёт по выставленному счёту.')->columnSpan(3),
                    TextInput::make('delivery_terms')->label('Условия поставки')->default('Срок и способ поставки согласовываются с менеджером.')->columnSpanFull(),
                ]),

            Section::make('Клиент')
                ->icon('heroicon-o-user')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('client_company')->label('Компания')->maxLength(255),
                    TextInput::make('client_name')->label('Контактное лицо')->maxLength(255),
                    TextInput::make('client_email')->label('Email')->email()->maxLength(255),
                    TextInput::make('client_phone')->label('Телефон')->tel()->maxLength(64),
                    TextInput::make('client_unp')->label('УНП / ИНН')->maxLength(32),
                    Textarea::make('client_address')->label('Адрес')->rows(2),
                ]),

            Section::make('ИИ-помощник подбора')
                ->description('Вставьте список, запрос клиента или текст письма. Помощник найдёт товары и количества, но ничего не добавит и не изменит без подтверждения менеджера.')
                ->icon('heroicon-o-sparkles')
                ->columnSpanFull()
                ->schema([
                    Textarea::make('copilot_request')
                        ->label('Что нужно подобрать')
                        ->placeholder("Например:\nНасос Grundfos Comfort 15-14 — 2 шт.\nКотёл Tatra KT30 — 1 шт.")
                        ->rows(5)
                        ->dehydrated(false)
                        ->columnSpanFull(),
                    Hidden::make('copilot_suggestion_ids')->default([])->dehydrated(false),
                    Hidden::make('copilot_quantities')->default([])->dehydrated(false),
                    Textarea::make('copilot_summary')
                        ->label('Результат разбора')
                        ->rows(3)
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpanFull(),
                    Select::make('copilot_product_ids')
                        ->label('Предложенные товары')
                        ->helperText('Проверьте варианты, снимите лишние или найдите другой товар. Затем нажмите «Добавить выбранные в КП».')
                        ->multiple()
                        ->searchable()
                        ->dehydrated(false)
                        ->options(fn (Get $get): array => app(QuickCommercialOfferService::class)->productLabels((array) $get('copilot_suggestion_ids')))
                        ->getSearchResultsUsing(fn (string $search): array => app(QuickCommercialOfferService::class)->searchProducts($search))
                        ->getOptionLabelsUsing(fn (array $values): array => app(QuickCommercialOfferService::class)->productLabels($values))
                        ->columnSpanFull(),
                    SchemaActions::make([
                        Action::make('analyze_copilot_request')
                            ->label('Подобрать товары')
                            ->icon('heroicon-o-sparkles')
                            ->color('warning')
                            ->action(function (Get $get, Set $set): void {
                                $request = trim((string) $get('copilot_request'));
                                if ($request === '') {
                                    Notification::make()->warning()->title('Вставьте список товаров или текст запроса')->send();

                                    return;
                                }

                                $result = app(CommercialOfferCopilotService::class)->analyze($request);
                                $suggestionIds = array_map('intval', array_keys((array) ($result['suggestions'] ?? [])));
                                $set('copilot_suggestion_ids', $suggestionIds);
                                $set('copilot_product_ids', (array) ($result['default_product_ids'] ?? []));
                                $set('copilot_quantities', (array) ($result['product_quantities'] ?? []));
                                $set('copilot_summary', (string) ($result['analysis_summary'] ?? 'Подбор завершён.'));

                                Notification::make()
                                    ->success()
                                    ->title('Подбор завершён')
                                    ->body(count($suggestionIds).' вариант(ов). Проверьте выбранные позиции перед добавлением.')
                                    ->send();
                            }),
                        Action::make('add_copilot_products')
                            ->label('Добавить выбранные в КП')
                            ->icon('heroicon-o-plus-circle')
                            ->color('success')
                            ->action(function (Get $get, Set $set): void {
                                $selected = array_values(array_unique(array_map('intval', (array) $get('copilot_product_ids'))));
                                if ($selected === []) {
                                    Notification::make()->warning()->title('Выберите хотя бы один товар')->send();

                                    return;
                                }

                                $items = (array) $get('items');
                                $existingIds = collect($items)->pluck('product_id')->filter()->map(fn ($id): int => (int) $id)->all();
                                $newIds = array_values(array_diff($selected, $existingIds));
                                $rows = app(CommercialOfferCopilotService::class)->itemRows($newIds, (array) $get('copilot_quantities'));
                                foreach ($rows as $row) {
                                    $items[(string) Str::uuid()] = $row;
                                }
                                $set('items', $items);

                                Notification::make()
                                    ->success()
                                    ->title($rows === [] ? 'Все выбранные товары уже добавлены' : 'Товары добавлены в КП')
                                    ->body($rows === [] ? null : 'Добавлено позиций: '.count($rows).'. Цены можно проверить по рынку ниже.')
                                    ->send();
                            }),
                    ])->key('copilot_actions')->columnSpanFull(),
                ]),

            Section::make('Товары и услуги')
                ->description('Найдите товар по названию или артикулу. Название и цена сохраняются в документе как снимок и потом не меняются вместе с каталогом.')
                ->icon('heroicon-o-shopping-cart')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('items')
                        ->relationship()
                        ->label('Позиции')
                        ->addActionLabel('Добавить позицию')
                        ->reorderable()
                        ->orderColumn('sort_order')
                        ->defaultItems(1)
                        ->columns(12)
                        ->schema([
                            Select::make('product_id')
                                ->label('Товар сайта')
                                ->relationship('product', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->orderBy('name'))
                                ->getOptionLabelFromRecordUsing(fn (Product $record): string => trim($record->name.' · '.($record->sku ?: 'без артикула')))
                                ->searchable(['name', 'sku'])
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set): void {
                                    $product = $state ? Product::query()->find($state) : null;
                                    if (! $product) {
                                        return;
                                    }
                                    $set('name', $product->name);
                                    $set('sku', $product->sku);
                                    $set('unit', $product->unit ?: 'шт.');
                                    $set('unit_price', (float) $product->price);
                                    $set('line_total', (float) $product->price);
                                })
                                ->columnSpan(4),
                            TextInput::make('name')->label('Наименование')->required()->maxLength(255)->columnSpan(4),
                            TextInput::make('sku')->label('Артикул')->maxLength(100)->columnSpan(2),
                            TextInput::make('unit')->label('Ед.')->default('шт.')->required()->columnSpan(2),
                            TextInput::make('quantity')->label('Кол-во')->numeric()->minValue(0.001)->default(1)->required()->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, callable $get, callable $set) => self::updateLineTotal($get, $set))->columnSpan(2),
                            TextInput::make('unit_price')->label('Цена, BYN')->numeric()->minValue(0)->default(0)->required()->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, callable $get, callable $set) => self::updateLineTotal($get, $set))->columnSpan(3),
                            TextInput::make('discount_percent')->label('Скидка, %')->numeric()->minValue(0)->maxValue(100)->default(0)->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, callable $get, callable $set) => self::updateLineTotal($get, $set))->columnSpan(2),
                            TextInput::make('vat_rate')->label('НДС, %')->numeric()->minValue(0)->default(20)->required()->columnSpan(2),
                            TextInput::make('line_total')->label('Сумма, BYN')->numeric()->readOnly()->dehydrated()->default(0)->columnSpan(3),
                            Placeholder::make('pricing_insight')
                                ->label('Цена, рынок и маржа')
                                ->content(function (Get $get): HtmlString {
                                    $productId = (int) $get('product_id');
                                    if ($productId <= 0) {
                                        return new HtmlString('<span class="text-sm text-gray-500">Выберите товар, чтобы увидеть закупочную цену, маржу и рынок.</span>');
                                    }

                                    $pricing = app(CommercialOfferCopilotService::class)->pricing($productId, (float) $get('unit_price'));

                                    return self::pricingInsightHtml($pricing);
                                })
                                ->columnSpan(9),
                            SchemaActions::make([
                                Action::make('compare_market')
                                    ->label('Сравнить рынок')
                                    ->icon('heroicon-o-chart-bar')
                                    ->color('info')
                                    ->action(function (Get $get): void {
                                        $productId = (int) $get('product_id');
                                        if ($productId <= 0) {
                                            Notification::make()->warning()->title('Сначала выберите товар')->send();

                                            return;
                                        }

                                        $pricing = app(CommercialOfferCopilotService::class)->pricing($productId, (float) $get('unit_price'));
                                        Notification::make()
                                            ->info()
                                            ->title('Проверка цены')
                                            ->body(self::pricingInsightText($pricing))
                                            ->persistent()
                                            ->send();
                                    }),
                            ])->key('pricing_actions')->columnSpan(3),
                        ]),
                ]),

            Section::make('Итоги и примечание')
                ->icon('heroicon-o-calculator')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('discount_total')->label('Дополнительная скидка')->numeric()->minValue(0)->default(0)->prefix('BYN'),
                    TextInput::make('delivery_price')->label('Доставка')->numeric()->minValue(0)->default(0)->prefix('BYN'),
                    Select::make('currency')->label('Валюта')->options(['BYN' => 'BYN'])->default('BYN')->required(),
                    Textarea::make('notes')->label('Примечание для клиента')->rows(3)->columnSpanFull(),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Документ')->columns(4)->schema([
                TextEntry::make('number')->label('Номер')->weight(FontWeight::Bold)->copyable(),
                TextEntry::make('type')->label('Тип')->badge()->formatStateUsing(fn (string $state): string => CommercialDocument::TYPES[$state] ?? $state),
                TextEntry::make('status')->label('Статус')->badge()->formatStateUsing(fn (string $state): string => CommercialDocument::STATUSES[$state] ?? $state),
                TextEntry::make('issued_at')->label('Дата')->date('d.m.Y'),
                TextEntry::make('client_company')->label('Компания')->placeholder('—'),
                TextEntry::make('client_name')->label('Контакт')->placeholder('—'),
                TextEntry::make('client_email')->label('Email')->placeholder('—')->copyable(),
                TextEntry::make('client_phone')->label('Телефон')->placeholder('—')->copyable(),
            ]),
            Section::make('Позиции')->schema([
                RepeatableEntry::make('items')->label('')->schema([
                    Grid::make(12)->schema([
                        TextEntry::make('name')->label('Товар')->weight(FontWeight::Bold)->columnSpan(5),
                        TextEntry::make('sku')->label('Артикул')->placeholder('—')->columnSpan(2),
                        TextEntry::make('quantity')->label('Количество')->suffix(fn ($record): string => ' '.$record->unit)->columnSpan(1),
                        TextEntry::make('unit_price')->label('Цена')->money('BYN')->columnSpan(2),
                        TextEntry::make('line_total')->label('Сумма')->money('BYN')->weight(FontWeight::Bold)->columnSpan(2),
                    ]),
                ])->columns(1),
            ]),
            Section::make('Итого')->columns(4)->schema([
                TextEntry::make('subtotal')->label('Товары')->money('BYN'),
                TextEntry::make('discount_total')->label('Скидка')->money('BYN'),
                TextEntry::make('delivery_price')->label('Доставка')->money('BYN'),
                TextEntry::make('total')->label('К оплате')->money('BYN')->weight(FontWeight::Bold)->color('warning'),
                TextEntry::make('payment_terms')->label('Оплата')->columnSpan(2),
                TextEntry::make('delivery_terms')->label('Поставка')->columnSpan(2),
                TextEntry::make('notes')->label('Примечание')->placeholder('—')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label('Номер')->searchable()->sortable()->weight('bold'),
                TextColumn::make('type')->label('Тип')->badge()->formatStateUsing(fn (string $state): string => CommercialDocument::TYPES[$state] ?? $state)
                    ->color(fn (string $state): string => $state === 'invoice' ? 'success' : 'info'),
                TextColumn::make('issued_at')->label('Дата')->date('d.m.Y')->sortable(),
                TextColumn::make('client_display')->label('Клиент')->state(fn (CommercialDocument $record): string => $record->client_company ?: ($record->client_name ?: 'Не указан'))
                    ->description(fn (CommercialDocument $record): ?string => $record->client_email),
                TextColumn::make('items_count')->label('Позиций')->counts('items')->alignCenter(),
                TextColumn::make('total')->label('Сумма')->money('BYN')->sortable()->weight('bold'),
                TextColumn::make('status')->label('Статус')->badge()->formatStateUsing(fn (string $state): string => CommercialDocument::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'sent', 'accepted', 'paid' => 'success', 'cancelled' => 'danger', default => 'warning'
                    }),
                TextColumn::make('manager.name')->label('Менеджер')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('type')->label('Тип')->options(CommercialDocument::TYPES),
                SelectFilter::make('status')->label('Статус')->options(CommercialDocument::STATUSES),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommercialDocuments::route('/'),
            'create' => CreateCommercialDocument::route('/create'),
            'view' => ViewCommercialDocument::route('/{record}'),
            'edit' => EditCommercialDocument::route('/{record}/edit'),
        ];
    }

    /** @param array<string, mixed> $pricing */
    private static function pricingInsightHtml(array $pricing): HtmlString
    {
        if (($pricing['status'] ?? null) !== 'ready') {
            return new HtmlString('<span class="text-sm text-gray-500">'.e((string) ($pricing['label'] ?? 'Нет данных')).'</span>');
        }

        $market = (array) ($pricing['market'] ?? []);
        $parts = [
            '<strong>Оценка:</strong> '.e((string) $pricing['label']).($pricing['quote_market_delta_percent'] !== null ? ' ('.number_format((float) $pricing['quote_market_delta_percent'], 1, ',', ' ').'% к медиане)' : ''),
            $pricing['purchase_price'] !== null ? '<strong>Закупка:</strong> '.self::money($pricing['purchase_price']) : '<strong>Закупка:</strong> нет данных',
            $pricing['margin_byn'] !== null ? '<strong>Маржа:</strong> '.self::money($pricing['margin_byn']).' / '.number_format((float) $pricing['margin_percent'], 1, ',', ' ').'%' : '<strong>Маржа:</strong> нет данных',
            ($market['status'] ?? null) === 'ready'
                ? '<strong>Рынок:</strong> '.self::money($market['minimum']).'–'.self::money($market['maximum']).', медиана '.self::money($market['median']).' ('.(int) $market['sources_count'].' ист.)'
                : '<strong>Рынок:</strong> '.e((string) ($market['reason'] ?? 'недостаточно подтверждённых данных')),
            $pricing['recommended_price'] !== null
                ? '<strong>Ориентир:</strong> '.self::money($pricing['recommended_price']).' — только рекомендация, цена не меняется автоматически'
                : ($pricing['minimum_safe_price'] !== null ? '<strong>Минимум для маржи:</strong> '.self::money($pricing['minimum_safe_price']) : null),
        ];

        return new HtmlString('<div class="space-y-1 text-sm">'.collect($parts)->filter()->map(fn (string $part): string => '<div>'.$part.'</div>')->implode('').'</div>');
    }

    /** @param array<string, mixed> $pricing */
    private static function pricingInsightText(array $pricing): string
    {
        if (($pricing['status'] ?? null) !== 'ready') {
            return (string) ($pricing['label'] ?? 'Нет данных');
        }

        $market = (array) ($pricing['market'] ?? []);
        $parts = [
            'Оценка: '.(string) $pricing['label'].($pricing['quote_market_delta_percent'] !== null ? ' ('.number_format((float) $pricing['quote_market_delta_percent'], 1, ',', ' ').'% к медиане)' : ''),
            $pricing['purchase_price'] !== null ? 'Закупка: '.self::money($pricing['purchase_price']) : 'Закупка: нет данных',
            $pricing['margin_byn'] !== null ? 'Маржа: '.self::money($pricing['margin_byn']).' / '.number_format((float) $pricing['margin_percent'], 1, ',', ' ').'%' : 'Маржа: нет данных',
            ($market['status'] ?? null) === 'ready'
                ? 'Рынок: '.self::money($market['minimum']).'–'.self::money($market['maximum']).', медиана '.self::money($market['median']).', источников '.(int) $market['sources_count']
                : 'Рынок: '.(string) ($market['reason'] ?? 'недостаточно подтверждённых данных'),
            $pricing['recommended_price'] !== null
                ? 'Безопасный ориентир: '.self::money($pricing['recommended_price']).'. Цена не изменена.'
                : ($pricing['minimum_safe_price'] !== null ? 'Минимум для допустимой маржи: '.self::money($pricing['minimum_safe_price']) : null),
        ];

        return collect($parts)->filter()->implode("\n");
    }

    private static function money(float|int|string $value): string
    {
        return number_format((float) $value, 2, ',', ' ').' BYN';
    }

    private static function updateLineTotal(callable $get, callable $set): void
    {
        $gross = (float) ($get('quantity') ?: 0) * (float) ($get('unit_price') ?: 0);
        $discount = (float) ($get('discount_percent') ?: 0);
        $set('line_total', round($gross * (1 - ($discount / 100)), 2));
    }
}

<?php

namespace App\Filament\Resources\CommercialDocuments;

use App\Filament\Resources\CommercialDocuments\Pages\CreateCommercialDocument;
use App\Filament\Resources\CommercialDocuments\Pages\EditCommercialDocument;
use App\Filament\Resources\CommercialDocuments\Pages\ListCommercialDocuments;
use App\Filament\Resources\CommercialDocuments\Pages\ViewCommercialDocument;
use App\Filament\Resources\MarketResearchReports\MarketResearchReportResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\CommercialDocument;
use App\Models\CrmContact;
use App\Models\CrmCounterparty;
use App\Models\Product;
use App\Services\CommercialDocuments\CommercialDocumentReadiness;
use App\Services\CommercialDocuments\CommercialDocumentRevisionComparison;
use App\Services\CommercialDocuments\CommercialOfferCopilotService;
use App\Services\CommercialDocuments\QuickCommercialOfferService;
use App\Services\Crm\BelarusTaxpayerRegistry;
use App\Services\Market\MarketWebResearchService;
use App\Services\Telephony\PhoneNumber;
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
use Filament\Infolists\Components\ViewEntry;
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
use Filament\Tables\Filters\Filter;
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

    public static function canEdit($record): bool
    {
        return parent::canEdit($record) && $record->status === 'draft';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['manager', 'items', 'mailThread', 'clientCounterparty', 'clientContact', 'order']);
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
                    Placeholder::make('revision_label')
                        ->label('Редакция')
                        ->content(fn (?CommercialDocument $record): string => '№ '.($record?->revision_number ?: 1)),
                ]),

            Section::make('Клиент')
                ->icon('heroicon-o-user')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('crm_counterparty_id')
                        ->label('Контрагент CRM')
                        ->placeholder('Начните вводить компанию, УНП, email или телефон')
                        ->helperText('Если контрагент не найден, он будет создан автоматически при сохранении документа.')
                        ->searchable()
                        ->live()
                        ->getSearchResultsUsing(fn (string $search): array => self::searchCounterparties($search))
                        ->getOptionLabelUsing(fn (mixed $value): ?string => self::counterpartyOptionLabel($value))
                        ->afterStateUpdated(fn (mixed $state, Set $set) => self::fillClientFromCounterparty($state, $set))
                        ->columnSpanFull(),
                    Hidden::make('client_default_discount_percent')->default(0)->dehydrated(false),
                    Select::make('crm_contact_id')
                        ->label('Контактное лицо CRM')
                        ->placeholder('Выберите получателя коммерческого предложения')
                        ->options(fn (Get $get): array => self::contactOptions($get('crm_counterparty_id')))
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(fn (mixed $state, Set $set) => self::fillClientFromContact($state, $set)),
                    TextInput::make('client_company')->label('Компания')->maxLength(255),
                    TextInput::make('client_legal_name')->label('Юридическое название')->maxLength(255)->columnSpanFull(),
                    TextInput::make('client_name')->label('Контактное лицо')->maxLength(255),
                    TextInput::make('client_email')->label('Email')->email()->maxLength(255),
                    TextInput::make('client_phone')->label('Телефон')->tel()->maxLength(64),
                    TextInput::make('client_unp')->label('УНП / ИНН')->maxLength(32),
                    Textarea::make('client_address')->label('Юридический адрес')->rows(2),
                    SchemaActions::make([
                        Action::make('lookup_client_requisites')
                            ->label('Подобрать реквизиты по УНП')
                            ->icon('heroicon-o-magnifying-glass')
                            ->color('info')
                            ->modalHeading('Проверка реквизитов покупателя')
                            ->modalDescription('Сначала используется карточка CRM / 1С, затем публичный реестр МНС. Проверьте найденные данные перед применением.')
                            ->modalSubmitActionLabel('Применить реквизиты')
                            ->fillForm(function (Get $get): array {
                                $lookup = app(BelarusTaxpayerRegistry::class)->lookup((string) $get('client_unp'));
                                if (! $lookup) {
                                    Notification::make()
                                        ->warning()
                                        ->title('Реквизиты не найдены')
                                        ->body('Проверьте УНП: для поиска белорусской организации требуется 9 цифр.')
                                        ->send();
                                }

                                return [
                                    'lookup_source' => $lookup['source'] ?? 'Не найдено',
                                    'lookup_status' => $lookup['status'] ?? null,
                                    'found_counterparty_id' => $lookup['crm_counterparty_id'] ?? null,
                                    'found_unp' => $lookup['unp'] ?? $get('client_unp'),
                                    'found_legal_name' => $lookup['legal_name'] ?? $get('client_legal_name'),
                                    'found_company' => $lookup['short_name'] ?? $get('client_company'),
                                    'found_address' => $lookup['address'] ?? $get('client_address'),
                                    'found_email' => $lookup['email'] ?? $get('client_email'),
                                    'found_phone' => $lookup['phone'] ?? $get('client_phone'),
                                ];
                            })
                            ->schema([
                                TextInput::make('lookup_source')->label('Источник')->readOnly(),
                                TextInput::make('lookup_status')->label('Статус в источнике')->readOnly(),
                                Hidden::make('found_counterparty_id'),
                                TextInput::make('found_unp')->label('УНП')->required(),
                                TextInput::make('found_company')->label('Краткое название')->required(),
                                TextInput::make('found_legal_name')->label('Юридическое название')->required()->columnSpanFull(),
                                Textarea::make('found_address')->label('Юридический адрес')->rows(2)->columnSpanFull(),
                                TextInput::make('found_email')->label('Email')->email(),
                                TextInput::make('found_phone')->label('Телефон'),
                            ])
                            ->action(function (array $data, Set $set): void {
                                if ($data['found_counterparty_id'] ?? null) {
                                    $set('crm_counterparty_id', $data['found_counterparty_id']);
                                }
                                $set('client_unp', $data['found_unp']);
                                $set('client_company', $data['found_company']);
                                $set('client_legal_name', $data['found_legal_name']);
                                $set('client_address', $data['found_address'] ?? null);
                                $set('client_email', $data['found_email'] ?? null);
                                $set('client_phone', $data['found_phone'] ?? null);

                                Notification::make()->success()->title('Реквизиты применены')->send();
                            }),
                    ])->columnSpanFull(),
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
                                $defaultDiscount = min(100, max(0, (float) $get('client_default_discount_percent')));
                                foreach ($rows as $row) {
                                    $row['discount_percent'] = $defaultDiscount;
                                    $row['line_total'] = round(
                                        (float) $row['unit_price'] * (float) $row['quantity'] * (1 - ($defaultDiscount / 100)),
                                        2,
                                    );
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
                                ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                    $product = $state ? Product::query()->find($state) : null;
                                    if (! $product) {
                                        return;
                                    }
                                    $discount = min(100, max(0, (float) $get('discount_percent')));
                                    $set('name', $product->name);
                                    $set('sku', $product->sku);
                                    $set('unit', $product->unit ?: 'шт.');
                                    $set('unit_price', (float) $product->price);
                                    $set('line_total', round((float) $product->price * (1 - ($discount / 100)), 2));
                                })
                                ->columnSpan(4),
                            TextInput::make('name')->label('Наименование')->required()->maxLength(255)->columnSpan(4),
                            TextInput::make('sku')->label('Артикул')->maxLength(100)->columnSpan(2),
                            TextInput::make('unit')->label('Ед.')->default('шт.')->required()->columnSpan(2),
                            TextInput::make('quantity')->label('Кол-во')->numeric()->minValue(0.001)->default(1)->required()->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, callable $get, callable $set) => self::updateLineTotal($get, $set))->columnSpan(2),
                            TextInput::make('unit_price')->label('Цена, BYN')->numeric()->minValue(0)->default(0)->required()->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, callable $get, callable $set) => self::updateLineTotal($get, $set))->columnSpan(3),
                            TextInput::make('discount_percent')->label('Скидка, %')->numeric()->minValue(0)->maxValue(100)
                                ->default(fn (Get $get): float => (float) ($get('../../client_default_discount_percent') ?: 0))->live(onBlur: true)
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
                                    ->label('Рынок / исследование')
                                    ->icon('heroicon-o-globe-alt')
                                    ->color('info')
                                    ->slideOver()
                                    ->modalWidth('7xl')
                                    ->modalHeading(fn (Get $get): string => 'Исследование рынка · '.(Product::query()->find((int) $get('product_id'))?->name ?: 'товар не выбран'))
                                    ->modalDescription('ИИ выполнит живой поиск в интернете, проверит точность модели и покажет вывод со ссылками. Обычно это занимает до двух минут.')
                                    ->modalSubmitAction(false)
                                    ->modalCancelActionLabel('Закрыть')
                                    ->modalContent(function (Get $get) {
                                        $product = Product::query()->find((int) $get('product_id'));
                                        if (! $product) {
                                            return view('filament.market.live-price-research', [
                                                'research' => [
                                                    'status' => 'error',
                                                    'message' => 'Сначала выберите товар.',
                                                ],
                                                'pricing' => [],
                                            ]);
                                        }

                                        $quotePrice = (float) $get('unit_price');

                                        return view('filament.market.live-price-research', [
                                            'research' => app(MarketWebResearchService::class)->research($product, $quotePrice),
                                            'pricing' => app(CommercialOfferCopilotService::class)->pricing($product->id, $quotePrice),
                                        ]);
                                    }),
                                Action::make('refresh_market')
                                    ->label('Обновить')
                                    ->icon('heroicon-o-arrow-path')
                                    ->color('warning')
                                    ->requiresConfirmation()
                                    ->modalHeading('Обновить исследование рынка?')
                                    ->modalDescription('Будет выполнен новый веб-поиск. Новый результат сохранится в карточке товара и заменит сохранённый результат при следующих просмотрах.')
                                    ->visible(fn (Get $get): bool => (int) $get('product_id') > 0)
                                    ->action(function (Get $get): void {
                                        $product = Product::query()->find((int) $get('product_id'));
                                        if (! $product) {
                                            Notification::make()->danger()->title('Сначала выберите товар')->send();

                                            return;
                                        }

                                        $research = app(MarketWebResearchService::class)->research(
                                            $product,
                                            (float) $get('unit_price'),
                                            forceRefresh: true,
                                        );

                                        Notification::make()
                                            ->title(($research['status'] ?? null) === 'ready'
                                                ? 'Исследование обновлено и сохранено'
                                                : 'Не удалось обновить исследование')
                                            ->body((string) (($research['status'] ?? null) === 'ready'
                                                ? 'Следующий просмотр рынка возьмёт этот результат из карточки товара.'
                                                : ($research['message'] ?? 'Провайдер не вернул готовый отчёт.')))
                                            ->color(($research['status'] ?? null) === 'ready' ? 'success' : 'danger')
                                            ->send();
                                    }),
                                Action::make('market_research_history')
                                    ->label('История')
                                    ->icon('heroicon-o-archive-box')
                                    ->color('gray')
                                    ->visible(fn (Get $get): bool => Product::query()
                                        ->find((int) $get('product_id'))
                                        ?->marketResearchReports()
                                        ->exists() ?? false)
                                    ->url(fn (Get $get): string => MarketResearchReportResource::getUrl('index', [
                                        'filters' => ['product_id' => ['value' => (int) $get('product_id')]],
                                    ]))
                                    ->openUrlInNewTab(),
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
                    TextInput::make('payment_terms')
                        ->label('Условия оплаты')
                        ->placeholder('Например: 100% предоплата')
                        ->default('Безналичный расчёт по выставленному счёту.')
                        ->maxLength(255)
                        ->columnSpan(2),
                    TextInput::make('delivery_terms')
                        ->label('Срок поставки')
                        ->placeholder('Например: 3–5 рабочих дней после оплаты')
                        ->default('Срок и способ поставки согласовываются с менеджером.')
                        ->maxLength(255),
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
                TextEntry::make('revision_number')->label('Редакция')->formatStateUsing(fn ($state): string => '№ '.($state ?: 1)),
                TextEntry::make('client_company')->label('Компания')->placeholder('—'),
                TextEntry::make('client_legal_name')->label('Юридическое название')->placeholder('—'),
                TextEntry::make('client_name')->label('Контакт')->placeholder('—'),
                TextEntry::make('client_email')->label('Email')->placeholder('—')->copyable(),
                TextEntry::make('client_phone')->label('Телефон')->placeholder('—')->copyable(),
                TextEntry::make('client_unp')->label('УНП / ИНН')->placeholder('—')->copyable(),
                TextEntry::make('client_address')->label('Юридический адрес')->placeholder('—')->columnSpan(2),
                TextEntry::make('outcome_reason')->label('Причина закрытия')->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => $state ? (CommercialDocument::OUTCOME_REASONS[$state] ?? $state) : '—'),
                TextEntry::make('outcome_note')->label('Комментарий по результату')->placeholder('—')->columnSpan(2),
                TextEntry::make('order.number')->label('Созданный заказ')->placeholder('Заказ ещё не создан')
                    ->url(fn (CommercialDocument $record): ?string => $record->order
                        ? OrderResource::getUrl('view', ['record' => $record->order])
                        : null),
            ]),
            Section::make('Готовность к отправке')->schema([
                TextEntry::make('readiness')
                    ->label('Проверка данных')
                    ->state(fn (CommercialDocument $record): string => app(CommercialDocumentReadiness::class)->summary($record))
                    ->badge()
                    ->color(fn (CommercialDocument $record): string => app(CommercialDocumentReadiness::class)->issues($record) === [] ? 'success' : 'warning'),
            ]),
            Section::make('История и изменения редакций')
                ->description('Все версии КП сохранены отдельно. Откройте любую редакцию или посмотрите, что изменилось в текущей.')
                ->visible(fn (CommercialDocument $record): bool => app(CommercialDocumentRevisionComparison::class)->history($record)->count() > 1)
                ->schema([
                    ViewEntry::make('revision_comparison')->hiddenLabel()
                        ->view('filament.commercial-documents.revision-comparison', function (CommercialDocument $record): array {
                            $service = app(CommercialDocumentRevisionComparison::class);

                            return [
                                'comparison' => $service->compare($record),
                                'document' => $record,
                                'history' => $service->history($record)->map(fn (CommercialDocument $revision): array => [
                                    'number' => $revision->number,
                                    'revision_number' => $revision->revision_number ?: 1,
                                    'status' => CommercialDocument::STATUSES[$revision->status] ?? $revision->status,
                                    'current' => $revision->is($record),
                                    'url' => self::getUrl('view', ['record' => $revision]),
                                ])->all(),
                            ];
                        })
                        ->columnSpanFull(),
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
                TextEntry::make('delivery_terms')->label('Срок поставки')->columnSpan(2),
                TextEntry::make('notes')->label('Примечание')->placeholder('—')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label('Номер')->searchable()->sortable()->weight('bold')
                    ->description(fn (CommercialDocument $record): string => 'Редакция №'.($record->revision_number ?: 1)),
                TextColumn::make('type')->label('Тип')->badge()->formatStateUsing(fn (string $state): string => CommercialDocument::TYPES[$state] ?? $state)
                    ->color(fn (string $state): string => $state === 'invoice' ? 'success' : 'info'),
                TextColumn::make('issued_at')->label('Дата')->date('d.m.Y')->sortable(),
                TextColumn::make('client_display')->label('Клиент')->state(fn (CommercialDocument $record): string => $record->client_company ?: ($record->client_name ?: 'Не указан'))
                    ->description(fn (CommercialDocument $record): ?string => $record->client_email),
                TextColumn::make('items_count')->label('Позиций')->counts('items')->alignCenter(),
                TextColumn::make('total')->label('Сумма')->money('BYN')->sortable()->weight('bold'),
                TextColumn::make('status')->label('Статус')->badge()->formatStateUsing(fn (string $state): string => CommercialDocument::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'sent', 'accepted', 'paid' => 'success',
                        'needs_revision' => 'warning',
                        'rejected', 'cancelled' => 'danger',
                        'expired' => 'gray',
                        default => 'warning'
                    }),
                TextColumn::make('manager.name')->label('Менеджер')->placeholder('—'),
                TextColumn::make('order.number')->label('Заказ')->placeholder('—')
                    ->url(fn (CommercialDocument $record): ?string => $record->order
                        ? OrderResource::getUrl('view', ['record' => $record->order])
                        : null)
                    ->toggleable(),
                TextColumn::make('control')
                    ->label('Следующее действие')
                    ->state(fn (CommercialDocument $record): string => $record->controlLabel())
                    ->badge()
                    ->color(fn (CommercialDocument $record): string => match ($record->controlLabel()) {
                        'Просрочено' => 'danger',
                        'Истекает сегодня', 'Создать заказ', 'Создать новую редакцию' => 'warning',
                        '—' => 'gray',
                        default => $record->needsManagerControl() ? 'info' : 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('type')->label('Тип')->options(CommercialDocument::TYPES),
                SelectFilter::make('status')->label('Статус')->options(CommercialDocument::STATUSES),
                Filter::make('needs_control')
                    ->label('Требует действия')
                    ->query(fn (Builder $query): Builder => $query->where('type', 'quote')->where(function (Builder $control): void {
                        $control->where('status', 'needs_revision')
                            ->orWhere(function (Builder $sent): void {
                                $sent->where('status', 'sent')->whereDate('valid_until', '<=', today()->addDays(3));
                            })->orWhere(function (Builder $accepted): void {
                                $accepted->where('status', 'accepted')->whereDoesntHave('order');
                            });
                    })),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn (CommercialDocument $record): bool => $record->status === 'draft'),
            ])
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

    /** @return array<int, string> */
    private static function searchCounterparties(string $search): array
    {
        $search = trim($search);
        $normalizedEmail = mb_strtolower($search);
        $normalizedPhone = PhoneNumber::normalize($search);

        return CrmCounterparty::query()
            ->where(function (Builder $query) use ($search, $normalizedEmail, $normalizedPhone): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('public_name', 'like', "%{$search}%")
                    ->orWhere('legal_name', 'like', "%{$search}%")
                    ->orWhere('unp', 'like', "%{$search}%")
                    ->orWhereHas('contactPoints', function (Builder $points) use ($normalizedEmail, $normalizedPhone): void {
                        $points->where('normalized_value', 'like', "%{$normalizedEmail}%");
                        if ($normalizedPhone) {
                            $points->orWhere('normalized_value', 'like', "%{$normalizedPhone}%");
                        }
                    });
            })
            ->limit(30)
            ->get()
            ->mapWithKeys(fn (CrmCounterparty $counterparty): array => [
                $counterparty->id => self::formatCounterpartyLabel($counterparty),
            ])
            ->all();
    }

    private static function counterpartyOptionLabel(mixed $value): ?string
    {
        $counterparty = CrmCounterparty::query()->find($value);

        return $counterparty ? self::formatCounterpartyLabel($counterparty) : null;
    }

    private static function formatCounterpartyLabel(CrmCounterparty $counterparty): string
    {
        return $counterparty->name.($counterparty->unp ? ' · УНП '.$counterparty->unp : '');
    }

    private static function fillClientFromCounterparty(mixed $value, Set $set): void
    {
        if (! $value) {
            $set('crm_contact_id', null);

            return;
        }

        $counterparty = CrmCounterparty::query()
            ->with(['contactPoints', 'contacts.contactPoints'])
            ->find($value);
        if (! $counterparty) {
            return;
        }

        $contact = $counterparty->contacts->firstWhere('is_primary', true) ?? $counterparty->contacts->first();
        $points = $contact?->contactPoints->isNotEmpty() ? $contact->contactPoints : $counterparty->contactPoints;

        $set('crm_contact_id', $contact?->id);
        $set('client_company', $counterparty->type === 'organization' ? ($counterparty->public_name ?: $counterparty->name) : null);
        $set('client_legal_name', $counterparty->type === 'organization' ? ($counterparty->legal_name ?: $counterparty->name) : null);
        $set('client_name', $contact?->full_name ?: ($counterparty->type === 'person' ? $counterparty->name : null));
        $set('client_email', $points->firstWhere('type', 'email')?->value);
        $set('client_phone', $points->firstWhere('type', 'phone')?->value);
        $set('client_unp', $counterparty->unp);
        $set('client_address', $counterparty->legal_address ?: ($counterparty->actual_address ?: $counterparty->postal_address));
        $set('client_default_discount_percent', (float) $counterparty->commercial_discount_percent);
        if (filled($counterparty->commercial_payment_terms)) {
            $set('payment_terms', $counterparty->commercial_payment_terms);
        }
        if (filled($counterparty->commercial_delivery_terms)) {
            $set('delivery_terms', $counterparty->commercial_delivery_terms);
        }
        $set('currency', $counterparty->commercial_currency ?: 'BYN');
    }

    /** @return array<int, string> */
    private static function contactOptions(mixed $counterpartyId): array
    {
        if (! $counterpartyId) {
            return [];
        }

        return CrmContact::query()
            ->where('counterparty_id', $counterpartyId)
            ->where('is_active', true)
            ->with('contactPoints')
            ->orderByDesc('is_primary')
            ->orderBy('full_name')
            ->get()
            ->mapWithKeys(function (CrmContact $contact): array {
                $details = $contact->contactPoints
                    ->where('is_active', true)
                    ->pluck('value')
                    ->take(2)
                    ->implode(' · ');

                return [$contact->id => $contact->full_name.($details ? ' · '.$details : '')];
            })
            ->all();
    }

    private static function fillClientFromContact(mixed $value, Set $set): void
    {
        $contact = $value
            ? CrmContact::query()->with('contactPoints')->find($value)
            : null;
        if (! $contact) {
            return;
        }

        $points = $contact->contactPoints->where('is_active', true);
        $set('client_name', $contact->full_name);
        $set('client_email', $points->firstWhere('type', 'email')?->value);
        $set('client_phone', $points->firstWhere('type', 'phone')?->value);
    }
}

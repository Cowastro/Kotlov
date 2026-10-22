<?php

namespace App\Filament\Resources\Products\Tables;

use App\Filament\Resources\CommercialDocuments\CommercialDocumentResource;
use App\Filament\Resources\CrmMailThreads\CrmMailThreadResource;
use App\Filament\Resources\MarketResearchReports\MarketResearchReportResource;
use App\Filament\Resources\Products\Actions\CreateCommercialDocumentAction;
use App\Filament\Resources\Products\Actions\ManualPricingAction;
use App\Filament\Resources\Products\Actions\RequestSupplierReserveAction;
use App\Filament\Resources\Products\ProductResource;
use App\Jobs\RunProductCatalogAiReview;
use App\Jobs\RunProductSourceEnrichment;
use App\Models\Brand;
use App\Models\Category;
use App\Models\MarketResearchReport;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\CommercialDocuments\CommercialOfferCopilotService;
use App\Services\Market\MarketPriceIndicator;
use App\Services\Market\MarketWebResearchService;
use App\Services\Pricing\B2bBrandRetailPricing;
use App\Support\AdminPhoneLinks;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select as FormSelect;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

class ProductsTable
{
    /** @var \WeakMap<Product, array<string, mixed>>|null */
    private static ?\WeakMap $marketIndicatorCache = null;

    public static function configure(Table $table): Table
    {
        return $table
            ->extraAttributes(['class' => 'products-workspace'])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('is_archived', false))
            ->columns([
                ImageColumn::make('images')
                    ->label('Фото')
                    ->getStateUsing(fn ($record) => url($record->imageUrl(0)))
                    ->square()
                    ->height(40)
                    ->width(40),

                TextColumn::make('name')
                    ->label('Товар')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->wrap()
                    ->width('20rem')
                    ->grow(false)
                    ->url(fn (Product $record): string => ProductResource::getUrl(
                        'view',
                        ['record' => $record],
                        panel: 'admin',
                    )),

                IconColumn::make('frontend_url')
                    ->label('')
                    ->state(true)
                    ->url(fn ($record) => self::productUrl($record))
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->tooltip('Открыть карточку на сайте')
                    ->alignCenter()
                    ->width('2.5rem')
                    ->grow(false)
                    ->toggleable(),

                TextColumn::make('sku')
                    ->label('Артикул')
                    ->searchable()
                    ->copyable()
                    ->limit(18)
                    ->tooltip(fn (Product $record): ?string => $record->sku)
                    ->width('7.5rem')
                    ->grow(false)
                    ->toggleable(),

                TextColumn::make('category.name')
                    ->label('Категория')
                    ->sortable()
                    ->badge()
                    ->limit(22)
                    ->width('9rem')
                    ->grow(false)
                    ->tooltip(fn ($record): ?string => $record->category?->name)
                    ->toggleable(),

                TextColumn::make('brand.name')
                    ->label('Бренд')
                    ->sortable()
                    ->placeholder('—')
                    ->width('6.5rem')
                    ->grow(false)
                    ->toggleable(),

                TextColumn::make('supplier_stock')
                    ->label('Остаток поставщика')
                    ->getStateUsing(fn ($record): string => ($stock = self::supplierStock($record)) === null
                        ? '—'
                        : number_format($stock, 0, ',', ' '))
                    ->alignCenter()
                    ->width('5.5rem')
                    ->grow(false)
                    ->toggleable(),

                TextColumn::make('supplier_price')
                    ->label('Цена опт')
                    ->getStateUsing(fn ($record): string => ($price = self::wholesalePrice($record))
                        ? number_format($price, 2, ',', ' ').' BYN'
                        : '—')
                    ->width('7rem')
                    ->grow(false)
                    ->toggleable(),

                TextColumn::make('price')
                    ->label('Розница')
                    ->sortable()
                    ->formatStateUsing(fn ($state, $record) => $state > 0
                        ? number_format((float) $state, 2).' '.($record->currency ?: 'BYN')
                        : '<span class="text-danger-500 font-bold">— не задана</span>'
                    )
                    ->width('7rem')
                    ->grow(false)
                    ->html(),

                TextColumn::make('market_price_indicator')
                    ->label('Рынок')
                    ->state(fn (Product $record): string => self::marketIndicator($record)['indicator_label'])
                    ->tooltip(fn (Product $record): string => self::marketIndicator($record)['indicator_tooltip'])
                    ->badge()
                    ->color(fn (Product $record): string => self::marketIndicator($record)['indicator_color'])
                    ->icon(fn (Product $record): string => self::marketIndicator($record)['indicator_icon'])
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('margin_min')
                    ->label('Маржа мин.')
                    ->getStateUsing(function ($record): string {
                        $cost = self::wholesalePrice($record);
                        $retail = $record->price !== null ? (float) $record->price : 0.0;

                        if (! $cost || $retail <= 0) {
                            return '—';
                        }

                        $cost = (float) $cost;
                        $margin = $retail - $cost;
                        $percent = $retail > 0 ? ($margin / $retail) * 100 : 0;

                        return number_format($margin, 2).' BYN / '.number_format($percent, 1).'%';
                    })
                    ->badge()
                    ->color(function ($record): string {
                        $cost = self::wholesalePrice($record);
                        $retail = $record->price !== null ? (float) $record->price : 0.0;

                        if (! $cost || $retail <= 0) {
                            return 'gray';
                        }

                        $percent = (($retail - (float) $cost) / $retail) * 100;

                        return match (true) {
                            $percent <= 0 => 'danger',
                            $percent < 10 => 'warning',
                            default => 'success',
                        };
                    })
                    ->width('8rem')
                    ->grow(false)
                    ->toggleable(),

                TextColumn::make('suppliers_list')
                    ->label('Поставщик')
                    ->getStateUsing(fn ($record): string => self::supplierNames($record))
                    ->description(fn ($record): ?HtmlString => self::supplierContacts($record))
                    ->limit(30)
                    ->lineClamp(1)
                    ->width('14rem')
                    ->grow(false)
                    ->tooltip(fn ($record): string => self::supplierNames($record))
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(
                        fn (Builder $query) => $query
                            ->whereHas('supplierProducts.supplier', fn ($supplier) => $supplier->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('integrationProducts', fn ($integrationProducts) => $integrationProducts
                                ->where('match_status', 'matched')
                                ->whereHas('source.supplier', fn ($supplier) => $supplier->where('name', 'like', "%{$search}%")))
                    ))
                    ->toggleable(),

                IconColumn::make('reserve_request_status')
                    ->label('Резерв')
                    ->state(fn (Product $record): string => self::reserveRequestStatus($record))
                    ->color(fn (Product $record): string => self::reserveRequestColor($record))
                    ->icon(fn (Product $record): ?string => self::reserveRequestIcon($record))
                    ->tooltip(fn (Product $record): string => self::reserveRequestTooltip($record))
                    ->url(fn (Product $record): ?string => $record->latestSupplierMailThread
                        ? CrmMailThreadResource::getUrl('index', [
                            'filters' => ['product_id' => ['value' => $record->id]],
                        ])
                        : null)
                    ->alignCenter()
                    ->width('3.5rem')
                    ->grow(false),

                TextColumn::make('supplier_channels')
                    ->label('Канал данных')
                    ->getStateUsing(fn ($record): string => self::supplierChannelState($record))
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_contains($state, '+') => 'warning',
                        $state === '1С' => 'success',
                        $state === 'Не настроен' => 'gray',
                        default => 'info',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('price_old')
                    ->label('Старая цена')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state ? number_format($state, 2).' BYN' : '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('currency')
                    ->label('Валюта')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('in_stock')
                    ->label('Наличие')
                    ->getStateUsing(fn ($record) => $record->in_stock || $record->supplierProducts->sum('stock_quantity') > 0)
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_archived')
                    ->label('Архив')
                    ->boolean()
                    ->trueIcon('heroicon-o-archive-box')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('operational_status')
                    ->label('На сайте')
                    ->state(fn (Product $record): string => match (true) {
                        $record->is_archived => 'Архив',
                        ! $record->is_active => 'Отключён',
                        default => self::availabilityLabel($record),
                    })
                    ->description(fn (Product $record): string => self::availabilityDescription($record))
                    ->badge()
                    ->color(fn (Product $record): string => match (true) {
                        $record->is_archived => 'gray',
                        ! $record->is_active => 'warning',
                        self::availabilityStatus($record) === Product::AVAILABILITY_IN_STOCK => 'success',
                        self::availabilityStatus($record) === Product::AVAILABILITY_OUT_OF_STOCK => 'danger',
                        self::isSupplierOrderOnly($record) => 'warning',
                        default => 'info',
                    }),

                TextColumn::make('status_badges')
                    ->label('Статусы')
                    ->getStateUsing(function ($record): string {
                        $badges = [];

                        if ($record->is_featured) {
                            $badges[] = '<span class="fi-badge fi-color-warning">Хит</span>';
                        }
                        if ($record->is_new) {
                            $badges[] = '<span class="fi-badge fi-color-info">Новинка</span>';
                        }
                        if ($record->is_sale) {
                            $badges[] = '<span class="fi-badge fi-color-danger">Акция</span>';
                        }

                        return $badges ? implode(' ', $badges) : '<span class="text-gray-500">—</span>';
                    })
                    ->html()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_featured')
                    ->label('Хит')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_new')
                    ->label('Новинка')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_sale')
                    ->label('Акция')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Добавлен')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Обновлён')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Категория')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('brand_id')
                    ->label('Бренд')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('supplier')
                    ->label('Поставщик')
                    ->options(fn () => Supplier::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->query(fn (Builder $query, array $data) => $data['value']
                        ? self::applySupplierFilter($query, (int) $data['value'])
                        : $query
                    ),

                TernaryFilter::make('in_stock')
                    ->label('Наличие')
                    ->placeholder('Все')
                    ->trueLabel('В наличии')
                    ->falseLabel('Нет в наличии')
                    ->queries(
                        true: fn (Builder $query): Builder => self::wherePubliclyInStock($query),
                        false: fn (Builder $query): Builder => self::whereNotPubliclyInStock($query),
                    ),
            ])
            ->recordUrl(fn (Product $record): string => ProductResource::getUrl(
                'view',
                ['record' => $record],
                panel: 'admin',
            ))
            ->recordActions([
                CreateCommercialDocumentAction::make()
                    ->iconButton()
                    ->tooltip('Создать КП и привязать к лиду'),
                Action::make('open_supplier_correspondence')
                    ->label('Переписка с поставщиком')
                    ->icon('heroicon-o-envelope-open')
                    ->iconButton()
                    ->color('gray')
                    ->tooltip(fn (Product $record): string => 'Переписка с поставщиком · '.(int) $record->supplier_mail_threads_count)
                    ->visible(fn (Product $record): bool => $record->latestSupplierMailThread !== null)
                    ->url(fn (Product $record): string => CrmMailThreadResource::getUrl('view', [
                        'record' => $record->latestSupplierMailThread,
                    ])),
                Action::make('review_high_market_price')
                    ->label('Цена выше рынка')
                    ->icon('heroicon-o-flag')
                    ->iconButton()
                    ->color('danger')
                    ->tooltip(fn (Product $record): string => self::highMarketPriceTooltip($record))
                    ->visible(fn (Product $record): bool => self::latestHighMarketPriceReport($record) !== null)
                    ->url(fn (Product $record): string => MarketResearchReportResource::getUrl('index', [
                        'filters' => ['product_id' => ['value' => $record->id]],
                    ])),
                ActionGroup::make([
                    RequestSupplierReserveAction::make(),
                    Action::make('market_price_details')
                        ->label('Рынок и цены')
                        ->icon('heroicon-o-presentation-chart-line')
                        ->color('info')
                        ->slideOver()
                        ->modalWidth('7xl')
                        ->modalHeading(fn (Product $record): string => 'Рынок: '.$record->name)
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Закрыть')
                        ->modalContent(function (Product $record) {
                            $indicator = self::marketIndicator($record);

                            return view('filament.market.price-details', [
                                'overview' => [
                                    'label' => $indicator['indicator_label'],
                                    'description' => $indicator['indicator_description'],
                                ],
                                'rows' => collect([[
                                    'item' => null,
                                    'product' => $record,
                                    'indicator' => $indicator,
                                ]]),
                            ]);
                        }),
                    ManualPricingAction::make(),
                    Action::make('market_research_history')
                        ->label(fn (Product $record): string => 'Исследования рынка ('.(int) ($record->market_research_reports_count ?? 0).')')
                        ->icon('heroicon-o-archive-box')
                        ->color('info')
                        ->visible(fn (Product $record): bool => (int) ($record->market_research_reports_count ?? 0) > 0)
                        ->url(fn (Product $record): string => MarketResearchReportResource::getUrl('index', [
                            'filters' => ['product_id' => ['value' => $record->id]],
                        ])),
                    Action::make('research_market')
                        ->label('Исследовать рынок')
                        ->icon('heroicon-o-globe-alt')
                        ->color('info')
                        ->visible(fn (Product $record): bool => ProductResource::canEdit($record))
                        ->slideOver()
                        ->modalWidth('7xl')
                        ->modalHeading(fn (Product $record): string => 'Исследование рынка · '.$record->name)
                        ->modalDescription('Откроется сохранённое исследование или будет выполнен первый поиск с сохранением в карточке товара.')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Закрыть')
                        ->modalContent(fn (Product $record) => view('filament.market.live-price-research', [
                            'research' => app(MarketWebResearchService::class)->research($record, (float) $record->price),
                            'pricing' => app(CommercialOfferCopilotService::class)->pricing($record->id, (float) $record->price),
                        ])),
                    Action::make('preview_source_enrichment')
                        ->label('Проверить из ссылки')
                        ->icon('heroicon-o-magnifying-glass')
                        ->color(fn (Product $record): string => self::hasProductImages($record) ? 'gray' : 'warning')
                        ->visible(fn (Product $record): bool => ProductResource::canEdit($record))
                        ->form(self::sourceEnrichmentForm(includePreviewToggle: false))
                        ->modalHeading('Проверить данные из ссылки')
                        ->modalDescription('База не изменится. Результат сохранится в уведомлениях.')
                        ->action(function (Product $record, array $data): void {
                            self::queueSourceEnrichment(collect([$record]), $data, previewOnly: true);
                        }),
                    Action::make('apply_source_enrichment')
                        ->label('Обновить из ссылки')
                        ->icon('heroicon-o-arrow-path')
                        ->color(fn (Product $record): string => self::hasProductImages($record) ? 'success' : 'warning')
                        ->visible(fn (Product $record): bool => ProductResource::canEdit($record))
                        ->form(self::sourceEnrichmentForm(includePreviewToggle: false))
                        ->requiresConfirmation()
                        ->modalHeading('Обновить товар из ссылки')
                        ->modalDescription('Фото, описание и характеристики будут записаны в выбранный товар.')
                        ->action(function (Product $record, array $data): void {
                            self::queueSourceEnrichment(collect([$record]), $data, previewOnly: false);
                        }),
                    ViewAction::make(),
                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkAction::make('create_commercial_document')
                    ->label('Создать КП / счёт')
                    ->icon('heroicon-o-document-currency-dollar')
                    ->color('primary')
                    ->action(function (Collection $records) {
                        session()->put('commercial_document_product_ids', $records->modelKeys());

                        return redirect(CommercialDocumentResource::getUrl('create'));
                    })
                    ->deselectRecordsAfterCompletion(),
                BulkActionGroup::make([
                    BulkAction::make('activate')
                        ->label('Активировать')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('deactivate')
                        ->label('Деактивировать')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('set_in_stock')
                        ->label('В наличии')
                        ->icon('heroicon-o-archive-box')
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each->update(['in_stock' => true]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('set_out_of_stock')
                        ->label('Нет в наличии')
                        ->icon('heroicon-o-archive-box-x-mark')
                        ->color('danger')
                        ->action(fn (Collection $records) => $records->each->update(['in_stock' => false]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('mark_featured')
                        ->label('Отметить хитом')
                        ->icon('heroicon-o-fire')
                        ->color('warning')
                        ->action(fn (Collection $records) => $records->each->update(['is_featured' => true]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('unmark_featured')
                        ->label('Снять хит')
                        ->icon('heroicon-o-fire')
                        ->color('gray')
                        ->action(fn (Collection $records) => $records->each->update(['is_featured' => false]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('mark_new')
                        ->label('Отметить новинкой')
                        ->icon('heroicon-o-sparkles')
                        ->color('info')
                        ->action(fn (Collection $records) => $records->each->update(['is_new' => true]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('unmark_new')
                        ->label('Снять новинку')
                        ->icon('heroicon-o-sparkles')
                        ->color('gray')
                        ->action(fn (Collection $records) => $records->each->update(['is_new' => false]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('mark_sale')
                        ->label('Отметить акцией')
                        ->icon('heroicon-o-tag')
                        ->color('danger')
                        ->action(fn (Collection $records) => $records->each->update(['is_sale' => true]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('unmark_sale')
                        ->label('Снять акцию')
                        ->icon('heroicon-o-tag')
                        ->color('gray')
                        ->action(fn (Collection $records) => $records->each->update(['is_sale' => false]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('archive')
                        ->label('Архивировать')
                        ->icon('heroicon-o-archive-box')
                        ->color('warning')
                        ->action(fn (Collection $records) => $records->each->update(['is_archived' => true]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('unarchive')
                        ->label('Разархивировать')
                        ->icon('heroicon-o-archive-box-x-mark')
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each->update(['is_archived' => false]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('change_brand')
                        ->label('Сменить бренд')
                        ->icon('heroicon-o-building-storefront')
                        ->color('info')
                        ->form([
                            FormSelect::make('brand_id')
                                ->label('Бренд')
                                ->options(fn () => Brand::query()
                                    ->orderBy('name')
                                    ->pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->nullable()
                                ->helperText('Можно оставить пустым, чтобы снять бренд.'),
                        ])
                        ->requiresConfirmation()
                        ->modalHeading('Сменить бренд у выбранных товаров')
                        ->action(fn (Collection $records, array $data) => $records->each->update([
                            'brand_id' => $data['brand_id'] ?? null,
                        ]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('change_category')
                        ->label('Сменить категорию')
                        ->icon('heroicon-o-folder')
                        ->color('info')
                        ->form([
                            FormSelect::make('category_id')
                                ->label('Категория')
                                ->options(fn () => Category::query()
                                    ->orderBy('name')
                                    ->pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->required(),
                        ])
                        ->requiresConfirmation()
                        ->modalHeading('Сменить категорию у выбранных товаров')
                        ->action(fn (Collection $records, array $data) => $records->each->update([
                            'category_id' => $data['category_id'],
                        ]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('ai_catalog_review')
                        ->label('AI-разбор каталога')
                        ->icon('heroicon-o-sparkles')
                        ->color('warning')
                        ->form([
                            Toggle::make('use_ai')
                                ->label('Использовать DeepSeek/AI')
                                ->helperText('Если выключить, сработают только локальные правила по брендам и категориям.')
                                ->default(true),
                        ])
                        ->requiresConfirmation()
                        ->modalHeading('Разобрать выбранные товары')
                        ->modalDescription('AI предложит бренд, категорию и отметит возможные дубли. Изменения не применяются сразу: будут созданы решения на проверку.')
                        ->action(function (Collection $records, array $data): void {
                            self::queueCatalogAiReview($records, (bool) ($data['use_ai'] ?? true));
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('enrich_from_source_url')
                        ->label('Обновить из ссылки')
                        ->icon('heroicon-o-link')
                        ->color('success')
                        ->form(self::sourceEnrichmentForm(includePreviewToggle: true))
                        ->requiresConfirmation()
                        ->modalHeading('Обновить выбранные товары из ссылки')
                        ->modalDescription('Система попробует взять с указанной страницы фото, характеристики и описание. Лучше применять к одному товару или к одинаковым дублям.')
                        ->action(function (Collection $records, array $data): void {
                            self::queueSourceEnrichment($records, $data, previewOnly: (bool) ($data['preview_only'] ?? true));
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('update_supplier')
                        ->label('Обновить поставщика')
                        ->icon('heroicon-o-building-storefront')
                        ->color('gray')
                        ->form([
                            FormSelect::make('supplier_id')
                                ->label('Поставщик')
                                ->options(fn () => User::query()
                                    ->where('role', 'supplier')
                                    ->orderBy('name')
                                    ->pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->nullable(),
                        ])
                        ->action(fn (Collection $records, array $data) => $records->each->update([
                            'supplier_id' => $data['supplier_id'] ?? null,
                        ]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('export_csv')
                        ->label('Экспорт цен CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('gray')
                        ->action(function (Collection $records) {
                            $filename = 'products_'.now()->format('Ymd_His').'.csv';
                            $headers = [
                                'Content-Type' => 'text/csv; charset=UTF-8',
                                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                            ];

                            $callback = function () use ($records) {
                                $out = fopen('php://output', 'w');
                                fwrite($out, "\xEF\xBB\xBF"); // BOM для Excel

                                fputcsv($out, [
                                    'id',
                                    'sku',
                                    'name',
                                    'price',
                                    'price_old',
                                    'currency',
                                    'in_stock',
                                    'stock_qty',
                                    'supplier_id',
                                    'supplier_name',
                                    'category_id',
                                    'category_name',
                                    'brand_id',
                                    'brand_name',
                                ], ';');

                                foreach ($records as $r) {
                                    fputcsv($out, [
                                        $r->id,
                                        $r->sku,
                                        $r->name,
                                        $r->price,
                                        $r->price_old,
                                        $r->currency,
                                        $r->in_stock ? '1' : '0',
                                        $r->stock_qty,
                                        $r->supplier_id,
                                        $r->supplier?->name,
                                        $r->category_id,
                                        $r->category?->name,
                                        $r->brand_id,
                                        $r->brand?->name,
                                    ], ';');
                                }
                                fclose($out);
                            };

                            return response()->stream($callback, 200, $headers);
                        }),

                    DeleteBulkAction::make(),
                ])->visible(fn (): bool => ProductResource::canEdit(new Product)),
            ]);
    }

    private static function latestHighMarketPriceReport(Product $product): ?MarketResearchReport
    {
        $report = $product->latestMarketResearchReport;

        if (! $report) {
            return null;
        }

        $currentPrice = round((float) $product->price, 2);
        $recommendedPrice = round((float) $report->recommended_price, 2);

        return $recommendedPrice > 0 && $currentPrice - $recommendedPrice >= 0.01
            ? $report
            : null;
    }

    private static function reserveRequestStatus(Product $product): string
    {
        $threads = $product->supplierMailThreads;
        if ($threads->isEmpty()) {
            return 'Не запрашивали';
        }

        $answers = $threads->whereIn('status', ['waiting_manager', 'new'])->count();
        $waiting = $threads->where('status', 'waiting_customer')->count();
        $inProgress = $threads->where('status', 'in_progress')->count();
        $drafts = $threads->where('status', 'draft')->count();

        if ($answers > 0 && $waiting > 0) {
            return 'Ответ: '.$answers.' · ждём: '.$waiting;
        }
        if ($answers > 0) {
            return $answers > 1 ? 'Ответов: '.$answers : 'Ответ получен';
        }
        if ($inProgress > 0) {
            return $inProgress > 1 ? 'В работе: '.$inProgress : 'Ответ в работе';
        }
        if ($waiting > 0) {
            return $waiting > 1 ? 'Ждём ответа · '.$waiting : 'Ждём ответа';
        }
        if ($drafts > 0) {
            return $drafts > 1 ? 'Черновиков: '.$drafts : 'Черновик';
        }

        return 'Закрыто · '.$threads->count();
    }

    private static function reserveRequestDescription(Product $product): ?string
    {
        $thread = $product->latestSupplierMailThread;
        if (! $thread) {
            return null;
        }

        $supplier = $thread->supplier?->name ?: $thread->participant_name ?: $thread->participant_email;
        $date = $thread->last_message_at?->timezone('Europe/Minsk')->format('d.m H:i');

        return collect([$supplier, $date])->filter()->implode(' · ') ?: null;
    }

    private static function reserveRequestColor(Product $product): string
    {
        $statuses = $product->supplierMailThreads->pluck('status');

        return match (true) {
            $statuses->contains(fn (string $status): bool => in_array($status, ['waiting_manager', 'new'], true)) => 'success',
            $statuses->contains('in_progress') => 'info',
            $statuses->contains('waiting_customer') => 'warning',
            default => 'gray',
        };
    }

    private static function reserveRequestIcon(Product $product): ?string
    {
        $statuses = $product->supplierMailThreads->pluck('status');

        return match (true) {
            $statuses->contains(fn (string $status): bool => in_array($status, ['waiting_manager', 'new'], true)) => 'heroicon-o-envelope-open',
            $statuses->contains('in_progress') => 'heroicon-o-arrow-path',
            $statuses->contains('waiting_customer') => 'heroicon-o-clock',
            $statuses->contains('draft') => 'heroicon-o-pencil-square',
            $statuses->isNotEmpty() => 'heroicon-o-check-circle',
            default => 'heroicon-o-minus',
        };
    }

    private static function reserveRequestTooltip(Product $product): string
    {
        $count = (int) ($product->supplier_mail_threads_count ?? 0);
        if ($count === 0) {
            return 'Запросов резерва ещё не было';
        }

        $threads = $product->supplierMailThreads;
        $answers = $threads->whereIn('status', ['waiting_manager', 'new'])->count();
        $waiting = $threads->where('status', 'waiting_customer')->count();
        $inProgress = $threads->where('status', 'in_progress')->count();
        $closed = $threads->whereIn('status', ['closed', 'spam'])->count();

        return collect([
            self::reserveRequestStatus($product),
            self::reserveRequestDescription($product),
            'Запросов: '.$count,
            $answers > 0 ? 'ответ получен: '.$answers : null,
            $waiting > 0 ? 'ожидаем ответ: '.$waiting : null,
            $inProgress > 0 ? 'обрабатывается: '.$inProgress : null,
            $closed > 0 ? 'закрыто: '.$closed : null,
            'Нажмите, чтобы открыть всю историю.',
        ])->filter()->implode(' · ');
    }

    private static function highMarketPriceTooltip(Product $product): string
    {
        $report = self::latestHighMarketPriceReport($product);

        if (! $report) {
            return 'Открыть анализ рынка';
        }

        return 'Цена выше рынка: '.number_format((float) $product->price, 2, ',', ' ')
            .' → '.number_format((float) $report->recommended_price, 2, ',', ' ')
            .' BYN. Открыть анализ и пересмотреть решение';
    }

    /** @return array<string, mixed> */
    private static function marketIndicator(Product $product): array
    {
        self::$marketIndicatorCache ??= new \WeakMap;

        return self::$marketIndicatorCache[$product]
            ??= app(MarketPriceIndicator::class)->forProduct($product);
    }

    public static function applySupplierFilter(Builder $query, int $supplierId): Builder
    {
        return $query->where(fn (Builder $query): Builder => $query
            ->whereHas('supplierProducts', fn (Builder $supplierProducts): Builder => $supplierProducts
                ->where('supplier_id', $supplierId)
                ->whereHas('supplier', fn (Builder $supplier): Builder => $supplier->where('is_active', true))
                ->where(fn (Builder $links): Builder => $links
                    ->where('match_confidence', 'manual_price_list')
                    ->orWhere(fn (Builder $official): Builder => $official
                        ->where('match_confidence', 'official_source')
                        ->where('price_byn', '>', 0))
                    ->orWhereHas('supplier', fn (Builder $supplier): Builder => $supplier
                        ->whereDoesntHave('latestChannelTransition', fn (Builder $transition): Builder => $transition
                            ->where('status', 'legacy_disabled')))))
            ->orWhereHas('integrationProducts', fn (Builder $integrationProducts): Builder => $integrationProducts
                ->where('match_status', 'matched')
                ->whereHas('source', fn (Builder $source): Builder => $source
                    ->where('supplier_id', $supplierId)
                    ->where('is_active', true)
                    ->whereHas('supplier', fn (Builder $supplier): Builder => $supplier->where('is_active', true))))
            ->orWhereHas('purchasePriceSources', fn (Builder $sources): Builder => $sources
                ->where('supplier_id', $supplierId)
                ->usable()
                ->whereHas('supplier', fn (Builder $supplier): Builder => $supplier->where('is_active', true))));
    }

    private static function sourceEnrichmentForm(bool $includePreviewToggle): array
    {
        $form = [
            TextInput::make('source_url')
                ->label('Ссылка на карточку товара')
                ->url()
                ->required()
                ->placeholder('https://example.com/product/...'),
        ];

        if ($includePreviewToggle) {
            $form[] = Toggle::make('preview_only')
                ->label('Только проверить, без записи')
                ->helperText('Покажет, что найдено. Для обновления товара снимите этот переключатель.')
                ->default(true);
        }

        return [
            ...$form,
            Toggle::make('update_images')
                ->label('Загрузить фотографии')
                ->default(true),
            Toggle::make('replace_images')
                ->label('Заменить текущие фото')
                ->helperText('Оставьте выключенным, чтобы новые фото только добавлялись к существующим.')
                ->default(false),
            Toggle::make('update_specs')
                ->label('Обновить характеристики')
                ->default(true),
            Toggle::make('update_content')
                ->label('SEO-описание через ИИ')
                ->helperText('Сырой текст поставщика не сохраняется. Если ИИ не настроен, описание не изменится.')
                ->default(true),
            Toggle::make('update_service')
                ->label('Обновить сервис')
                ->default(false),
        ];
    }

    private static function queueSourceEnrichment(iterable $records, array $data, bool $previewOnly): void
    {
        $productIds = collect($records)->pluck('id')->filter()->values()->all();
        if ($productIds === []) {
            self::sendAdminNotification(Notification::make()
                ->warning()
                ->title('Товары не выбраны')
                ->body('Выберите товар и повторите действие.'));

            return;
        }

        $options = [
            'update_images' => (bool) ($data['update_images'] ?? true),
            'replace_images' => (bool) ($data['replace_images'] ?? false),
            'update_specs' => (bool) ($data['update_specs'] ?? true),
            'replace_specs' => true,
            'update_content' => (bool) ($data['update_content'] ?? true),
            'update_service' => (bool) ($data['update_service'] ?? false),
        ];

        RunProductSourceEnrichment::dispatch(
            $productIds,
            (int) auth()->id(),
            (string) $data['source_url'],
            $options,
            $previewOnly,
        );

        self::sendAdminNotification(Notification::make()
            ->success()
            ->title($previewOnly ? 'Проверка поставлена в очередь' : 'Обновление поставлено в очередь')
            ->body('Товаров в задаче: '.count($productIds).".\nРезультат появится в уведомлениях после выполнения очереди."));
    }

    private static function queueCatalogAiReview(iterable $records, bool $useAi): void
    {
        $productIds = collect($records)->pluck('id')->filter()->values()->all();
        if ($productIds === []) {
            self::sendAdminNotification(Notification::make()
                ->warning()
                ->title('Товары не выбраны')
                ->body('Выберите товары и повторите действие.'));

            return;
        }

        RunProductCatalogAiReview::dispatch(
            $productIds,
            (int) auth()->id(),
            $useAi,
        );

        self::sendAdminNotification(Notification::make()
            ->success()
            ->title('AI-разбор поставлен в очередь')
            ->body('Товаров в задаче: '.count($productIds).".\nРезультат и команды проверки появятся в уведомлениях."));
    }

    private static function hasProductImages(Product $record): bool
    {
        return array_values(array_filter((array) $record->images)) !== [];
    }

    private static function sendAdminNotification(Notification $notification, ?string $databaseBody = null): void
    {
        $notification->send();

        $user = auth()->user();
        if ($user) {
            $databaseNotification = clone $notification;
            if ($databaseBody !== null) {
                $databaseNotification->body($databaseBody);
            }
            $databaseNotification->sendToDatabase($user);
        }
    }

    private static function productUrl(object $record): ?string
    {
        $categorySlug = $record->category?->slug;

        if (! $categorySlug || ! $record->slug) {
            return null;
        }

        $url = url('/'.$categorySlug.'/'.$record->slug);

        return ! $record->is_active && ! $record->is_archived
            ? $url.'?admin-preview=1'
            : $url;
    }

    private static function supplierChannelState(object $record): string
    {
        $integrationItems = self::activeIntegrationItems($record);
        $channels = $integrationItems
            ->map(fn ($item): ?string => match ($item->source?->driver) {
                'commerceml' => '1С',
                'api' => 'API',
                'file' => 'Файл',
                default => $item->source?->name,
            })
            ->filter()
            ->toBase();

        if ($record->operationalSupplierProducts()->isNotEmpty()) {
            $channels->push('Прайс поставщика');
        }

        if ($record->usablePurchasePriceSources()->isNotEmpty()) {
            $channels->push('Подтверждённый документ');
        }

        if ($channels->isEmpty() && (float) $record->manual_purchase_price_byn > 0) {
            return 'Вручную';
        }

        return $channels->unique()->implode(' + ') ?: 'Не настроен';
    }

    private static function wholesalePrice(object $record): ?float
    {
        if ($record instanceof Product) {
            $brandRetailPrice = app(B2bBrandRetailPricing::class)->priceFor($record);
            if ($brandRetailPrice !== null) {
                return $brandRetailPrice;
            }
        }

        $prices = $record->usablePurchasePriceSources()->pluck('price_byn')
            ->concat(self::activeIntegrationItems($record)
                ->map(fn ($item): ?float => $item->normalizedPriceByn()))
            ->concat($record->operationalSupplierProducts()->pluck('price_byn'))
            ->map(fn ($price): float => (float) $price)
            ->filter(fn (float $price): bool => $price > 0);

        if ($prices->isNotEmpty()) {
            return (float) $prices->min();
        }

        return (float) $record->manual_purchase_price_byn > 0
            ? (float) $record->manual_purchase_price_byn
            : null;
    }

    private static function supplierStock(object $record): ?int
    {
        $bySupplier = collect();

        $record->operationalSupplierProducts()
            ->filter(fn ($item): bool => $item->stock_quantity !== null)
            ->each(function ($item) use ($bySupplier): void {
                $supplierId = (int) $item->supplier_id;
                $bySupplier->put($supplierId, max((float) ($bySupplier->get($supplierId) ?? 0), (float) $item->stock_quantity));
            });

        self::activeIntegrationItems($record)
            ->filter(fn ($item): bool => $item->stock_quantity !== null)
            ->each(function ($item) use ($bySupplier): void {
                $supplierId = (int) $item->source->supplier_id;
                $bySupplier->put($supplierId, max((float) ($bySupplier->get($supplierId) ?? 0), (float) $item->stock_quantity));
            });

        return $bySupplier->isEmpty() ? null : (int) $bySupplier->sum();
    }

    private static function supplierNames(object $record): string
    {
        return $record->operationalSuppliers()->pluck('name')->filter()->implode(', ') ?: '—';
    }

    private static function supplierContacts(object $record): ?HtmlString
    {
        $suppliers = self::productSuppliers($record);
        $showNames = $suppliers->count() > 1;
        $rows = $suppliers->map(function (Supplier $supplier) use ($record, $showNames): ?string {
            $phone = $supplier->contactPhoneNumbers()[0] ?? null;
            $email = $supplier->contactEmailAddresses()[0] ?? null;
            if (! $phone && ! $email) {
                return null;
            }

            $links = collect([
                $phone ? AdminPhoneLinks::render($phone)?->toHtml() : null,
                $email ? sprintf(
                    '<button type="button" class="text-primary-600 hover:underline" wire:click.stop="mountTableAction(\'request_supplier_reserve\', \'%s\')" title="Запросить резерв через CRM: %s" aria-label="Запросить резерв у поставщика через CRM">✉</button>',
                    e((string) $record->getKey()),
                    e($email),
                ) : null,
            ])->filter()->implode(' · ');

            return ($showNames ? '<span class="text-gray-500">'.e($supplier->name).':</span> ' : '').$links;
        })->filter()->implode('<br>');

        return $rows !== '' ? new HtmlString($rows) : null;
    }

    /** @return Collection<int, Supplier> */
    private static function productSuppliers(object $record): Collection
    {
        return new Collection($record->operationalSuppliers()->all());
    }

    private static function availabilityStatus(Product $record): string
    {
        return $record->effectiveAvailabilityStatus();
    }

    private static function availabilityLabel(Product $record): string
    {
        if (self::isSupplierOrderOnly($record)) {
            return 'Под заказ';
        }

        return Product::availabilityStatusOptions()[self::availabilityStatus($record)];
    }

    private static function isSupplierOrderOnly(Product $record): bool
    {
        if (self::activeIntegrationItems($record)->isNotEmpty()) {
            return false;
        }

        $freshSupplierItems = $record->supplierProducts->filter(function ($item): bool {
            $confirmedAt = $item->last_stock_synced_at
                ?? $item->last_synced_at
                ?? $item->updated_at;

            return $item->supplier?->is_active === true
                && $confirmedAt?->gte(now()->subHours(SupplierProduct::STALE_AFTER_HOURS));
        });

        return $freshSupplierItems->isNotEmpty()
            && $freshSupplierItems->every(fn ($item): bool => $item->stock_status === 'on_order');
    }

    private static function wherePubliclyInStock(Builder $query): Builder
    {
        return self::whereEffectivelyInStock($query)
            ->where('is_active', true)
            ->where('is_archived', false)
            ->where('price', '>', 0);
    }

    private static function whereNotPubliclyInStock(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query
                ->where('is_active', false)
                ->orWhere('is_archived', true)
                ->orWhereNull('price')
                ->orWhere('price', '<=', 0)
                ->orWhere('in_stock', false)
                ->orWhereNull('in_stock')
                ->orWhere('availability_status', Product::AVAILABILITY_OUT_OF_STOCK)
                ->orWhere(function (Builder $query): void {
                    $query
                        ->whereDoesntHave('supplierProducts', fn (Builder $supplierProducts): Builder => $supplierProducts
                            ->where(fn (Builder $stock): Builder => $stock
                                ->where('in_stock', true)
                                ->orWhere('stock_quantity', '>', 0)))
                        ->whereDoesntHave('integrationProducts', fn (Builder $integrationProducts): Builder => $integrationProducts
                            ->where('match_status', 'matched')
                            ->where('stock_quantity', '>', 0)
                            ->whereHas('source', fn (Builder $source): Builder => $source->where('is_active', true)));
                });
        });
    }

    private static function whereEffectivelyInStock(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('is_archived', false)
            ->where('in_stock', true)
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('availability_status')
                    ->orWhere('availability_status', '!=', Product::AVAILABILITY_OUT_OF_STOCK);
            })
            ->where(function (Builder $query): void {
                $query
                    ->whereHas('supplierProducts', fn (Builder $supplierProducts): Builder => $supplierProducts
                        ->where(fn (Builder $stock): Builder => $stock
                            ->where('in_stock', true)
                            ->orWhere('stock_quantity', '>', 0)))
                    ->orWhereHas('integrationProducts', fn (Builder $integrationProducts): Builder => $integrationProducts
                        ->where('match_status', 'matched')
                        ->where('stock_quantity', '>', 0)
                        ->whereHas('source', fn (Builder $source): Builder => $source->where('is_active', true)));
            });
    }

    private static function whereAvailabilityNeedsCheck(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('is_archived', false)
            ->where(function (Builder $status): void {
                $status
                    ->whereNull('availability_status')
                    ->orWhere('availability_status', '!=', Product::AVAILABILITY_OUT_OF_STOCK);
            })
            ->whereDoesntHave('supplierProducts', fn (Builder $supplierProducts): Builder => $supplierProducts
                ->where(fn (Builder $stock): Builder => $stock
                    ->where('in_stock', true)
                    ->orWhere('stock_quantity', '>', 0)))
            ->whereDoesntHave('integrationProducts', fn (Builder $integrationProducts): Builder => $integrationProducts
                ->where('match_status', 'matched')
                ->where('stock_quantity', '>', 0)
                ->whereHas('source', fn (Builder $source): Builder => $source->where('is_active', true)));
    }

    private static function whereEffectivelyOutOfStock(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query
                ->where('is_active', false)
                ->orWhere('is_archived', true)
                ->orWhere('availability_status', Product::AVAILABILITY_OUT_OF_STOCK);
        });
    }

    private static function activeIntegrationItems(object $record): Collection
    {
        return new Collection($record->operationalIntegrationProducts()->all());
    }

    private static function availabilityDescription(Product $record): string
    {
        if (self::isSupplierOrderOnly($record)) {
            return 'По актуальному прайсу поставщика';
        }

        $supplierStock = self::supplierStock($record);
        $stock = $record->stock_qty === null
            ? $supplierStock
            : (float) $record->stock_qty;

        if ($stock === null) {
            if (self::availabilityStatus($record) === Product::AVAILABILITY_IN_STOCK) {
                return 'Наличие подтверждено прайсом';
            }

            return 'Количество не указано';
        }

        return 'Остаток: '.number_format($stock, 0, ',', ' ').' шт.';
    }
}

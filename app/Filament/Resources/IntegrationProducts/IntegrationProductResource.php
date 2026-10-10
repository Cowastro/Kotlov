<?php

namespace App\Filament\Resources\IntegrationProducts;

use App\Filament\Resources\IntegrationProducts\Pages\EditIntegrationProduct;
use App\Filament\Resources\IntegrationProducts\Pages\ListIntegrationProducts;
use App\Models\Category;
use App\Models\IntegrationCategory;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Services\Integrations\IntegrationCategoryAdvisor;
use App\Services\Integrations\IntegrationProductMatchAdvisor;
use App\Services\Integrations\IntegrationProductMatchDecision;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class IntegrationProductResource extends Resource
{
    protected static ?string $model = IntegrationProduct::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $navigationLabel = 'Привязка товаров';

    protected static ?string $modelLabel = 'внешний товар';

    protected static ?string $pluralModelLabel = 'Привязка товаров';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return 'Интеграции';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['source', 'integrationCategory.siteCategory', 'targetCategory', 'product.category'])
            ->inStock();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Товар из внешней системы')
                ->schema([
                    Placeholder::make('source_name')->label('Источник')
                        ->content(fn (?IntegrationProduct $record): string => $record?->source?->name ?? '—'),
                    Placeholder::make('integration_category')
                        ->label('Группа источника')
                        ->content(fn (?IntegrationProduct $record): string => $record?->integrationCategory?->path ?? '—'),
                    TextInput::make('external_id')->label('Внешний ID')->disabled()->dehydrated(false),
                    TextInput::make('external_code')->label('Код 1С')->disabled()->dehydrated(false),
                    TextInput::make('external_sku')->label('Артикул')->disabled()->dehydrated(false),
                    TextInput::make('barcode')->label('Штрихкод')->disabled()->dehydrated(false),
                    TextInput::make('name')->label('Название')->disabled()->dehydrated(false)->columnSpanFull(),
                ])->columns(2),
            Section::make('Карточка kotlov.by')
                ->description('Выбор товара создаёт постоянную ручную привязку. Если источник передал артикул, решение запомнится для будущих синхронизаций. Цена и остаток не изменяются.')
                ->schema([
                    Select::make('product_id')
                        ->label('Товар сайта')
                        ->relationship('product', 'name')
                        ->getOptionLabelFromRecordUsing(fn ($record): string => "{$record->sku} — {$record->name}")
                        ->searchable(['sku', 'name'])
                        ->nullable(),
                    Select::make('target_category_id')
                        ->label('Категория для новой карточки')
                        ->options(fn (): array => self::siteCategoryOptions())
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->helperText('Индивидуальное назначение имеет приоритет над правилом группы. Существующую карточку не перемещает.'),
                    TextInput::make('match_status')->label('Текущий статус')->disabled()->dehydrated(false),
                    TextInput::make('match_method')->label('Метод')->disabled()->dehydrated(false),
                    TextInput::make('match_confidence')->label('Уверенность')->disabled()->dehydrated(false),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source_partner')->label('Поставщик')
                    ->state(fn (IntegrationProduct $record): string => $record->source?->partnerName() ?? 'Источник не указан')
                    ->description(fn (IntegrationProduct $record): ?string => $record->source?->name)
                    ->badge()
                    ->color('warning')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderBy(
                            IntegrationSource::query()
                                ->select('name')
                                ->whereColumn('integration_sources.id', 'integration_products.integration_source_id'),
                            $direction
                        ))
                    ->size(TextSize::ExtraSmall),
                TextColumn::make('integrationCategory.path')->label('Группа источника')
                    ->searchable()->sortable()->limit(45)
                    ->tooltip(fn (IntegrationProduct $record): ?string => $record->integrationCategory?->path)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('catalog_destination')->label('Категория сайта')
                    ->state(fn (IntegrationProduct $record): ?string => $record->resolvedSiteCategory()?->name)
                    ->placeholder('Не назначена')
                    ->description(fn (IntegrationProduct $record): string => $record->categoryResolutionLabel())
                    ->badge()
                    ->color(fn (IntegrationProduct $record): string => match (true) {
                        filled($record->product?->category_id) => 'success',
                        filled($record->target_category_id) => 'primary',
                        filled($record->integrationCategory?->category_id) => 'info',
                        default => 'warning',
                    })
                    ->wrap(),
                TextColumn::make('name')->label('Товар во внешней системе')
                    ->searchable(['name', 'external_sku', 'external_code', 'external_id'])
                    ->limit(58)->lineClamp(2)->size(TextSize::Small)
                    ->tooltip(fn (IntegrationProduct $record): string => $record->name)
                    ->description(fn (IntegrationProduct $record): string => collect([
                        $record->external_sku ? "арт. {$record->external_sku}" : null,
                        $record->external_code ? "код {$record->external_code}" : null,
                    ])->filter()->implode(' · ') ?: $record->external_id),
                TextColumn::make('product.name')->label('Карточка сайта')->searchable()
                    ->state(fn (IntegrationProduct $record): ?string => $record->product?->name
                        ?? ($record->candidates[0]['name'] ?? null))
                    ->limit(48)->lineClamp(2)->size(TextSize::Small)
                    ->tooltip(fn (IntegrationProduct $record): ?string => $record->product?->name
                        ?? ($record->candidates[0]['name'] ?? null))
                    ->url(fn (IntegrationProduct $record): ?string => self::productUrl($record)
                        ?? (app(IntegrationProductMatchAdvisor::class)->candidates($record)[0]['public_url'] ?? null))
                    ->openUrlInNewTab()
                    ->icon(fn (IntegrationProduct $record): ?string => $record->product
                        || filled($record->candidates[0]['product_id'] ?? null)
                            ? 'heroicon-o-arrow-top-right-on-square'
                            : null)
                    ->color(fn (IntegrationProduct $record): string => $record->product
                        ? 'primary'
                        : (filled($record->candidates[0]['product_id'] ?? null) ? 'info' : 'gray'))
                    ->description(fn (IntegrationProduct $record): ?string => $record->product?->sku
                        ?? (filled($record->candidates[0]['sku'] ?? null)
                            ? 'кандидат · '.$record->candidates[0]['sku']
                            : null)),
                TextColumn::make('match_status')->label('Статус')->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'matched' => 'Привязан',
                        'suggested' => 'Предложение',
                        'ambiguous' => 'Несколько вариантов',
                        'ignored' => 'Игнорируется',
                        default => 'Не найден',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'matched' => 'success',
                        'suggested' => 'info',
                        'ambiguous' => 'warning',
                        'ignored' => 'gray',
                        default => 'danger',
                    }),
                TextColumn::make('match_confidence')->label('Совпадение')->formatStateUsing(
                    fn ($state): string => $state === null ? '—' : round((float) $state * 100).'%'
                ),
                TextColumn::make('customer_price')->label('Цена клиенту')
                    ->state(function (IntegrationProduct $record): ?float {
                        $sourcePrice = (float) $record->price;

                        if ($sourcePrice <= 0) {
                            return null;
                        }

                        return $record->source
                            ? $record->source->priceIncludingTax($sourcePrice)
                            : $sourcePrice;
                    })
                    ->money('BYN')
                    ->placeholder('Цена не передана')
                    ->description(fn (IntegrationProduct $record): ?string => (float) $record->price > 0
                        ? sprintf(
                            'Цена 1С: %s BYN · %s',
                            number_format((float) $record->price, 2, ',', ' '),
                            $record->source?->sourcePriceTaxLabel() ?? 'режим НДС не указан',
                        )
                        : null)
                    ->size(TextSize::Small),
                TextColumn::make('stock_quantity')->label('Остаток 1С')
                    ->state(fn (IntegrationProduct $record): string => $record->formattedStockQuantity())
                    ->sortable()
                    ->toggleable()
                    ->size(TextSize::Small),
                TextColumn::make('last_seen_at')->label('Получен')->dateTime('d.m.Y H:i')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('integration_source_id')
                    ->label('Поставщик / источник')
                    ->placeholder('Все поставщики')
                    ->options(fn (): array => IntegrationSource::query()
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (IntegrationSource $source): array => [
                            $source->getKey() => $source->partnerName().' — '.$source->name,
                        ])
                        ->all())
                    ->searchable()
                    ->preload(),
                SelectFilter::make('integration_category_id')->label('Группа поставщика')
                    ->placeholder('Все группы')
                    ->options(fn (): array => IntegrationCategory::query()->orderBy('path')->pluck('path', 'id')->all())
                    ->searchable(),
                SelectFilter::make('match_status')
                    ->label('Статус привязки')
                    ->placeholder('Все статусы')
                    ->options([
                        'matched' => 'Привязан',
                        'suggested' => 'Предложение',
                        'ambiguous' => 'Несколько вариантов',
                        'unmatched' => 'Не найден',
                        'ignored' => 'Не для сайта',
                    ]),
                SelectFilter::make('site_category_id')
                    ->label('Категория сайта')
                    ->placeholder('Все категории сайта')
                    ->options(fn (): array => self::siteCategoryOptions())
                    ->searchable()
                    ->preload()
                    ->query(function (Builder $query, array $data): Builder {
                        $categoryId = $data['value'] ?? null;

                        if (blank($categoryId)) {
                            return $query;
                        }

                        return $query->where(function (Builder $query) use ($categoryId): void {
                            $query
                                ->whereHas('product', fn (Builder $product) => $product->where('category_id', $categoryId))
                                ->orWhere(function (Builder $query) use ($categoryId): void {
                                    $query->whereNull('product_id')
                                        ->where('target_category_id', $categoryId);
                                })
                                ->orWhere(function (Builder $query) use ($categoryId): void {
                                    $query->whereNull('product_id')
                                        ->whereNull('target_category_id')
                                        ->whereHas('integrationCategory', fn (Builder $category) => $category->where('category_id', $categoryId));
                                });
                        });
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->deferFilters(false)
            ->persistFiltersInSession()
            ->defaultSort('last_seen_at', 'desc')
            ->recordActions([
                Action::make('reviewCandidates')
                    ->label('Сравнить варианты')
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->color('warning')
                    ->visible(fn (IntegrationProduct $record): bool => $record->match_status === 'ambiguous'
                        && filled($record->candidates[0]['product_id'] ?? null))
                    ->modalHeading('Выбрать существующую карточку kotlov.by')
                    ->modalDescription(fn (IntegrationProduct $record): string => 'Для «'.$record->name.'» найдено несколько похожих карточек. Сверьте модель, диаметр, размер и артикул.')
                    ->form([
                        Select::make('product_id')
                            ->label('Подходящая карточка сайта')
                            ->options(function (IntegrationProduct $record): array {
                                return collect(app(IntegrationProductMatchAdvisor::class)->candidates($record))
                                    ->mapWithKeys(fn (array $candidate): array => [
                                        $candidate['product_id'] => collect([
                                            round($candidate['confidence'] * 100).'%',
                                            $candidate['product_sku'] ? 'SKU '.$candidate['product_sku'] : null,
                                            $candidate['product_name'],
                                            $candidate['category_name'] ? '('.$candidate['category_name'].')' : null,
                                        ])->filter()->implode(' · '),
                                    ])
                                    ->all();
                            })
                            ->default(fn (IntegrationProduct $record): ?int => app(IntegrationProductMatchAdvisor::class)
                                ->candidates($record)[0]['product_id'] ?? null)
                            ->searchable()
                            ->required()
                            ->helperText('Показываются только кандидаты текущего расчёта. Решение будет сохранено для следующих обменов.'),
                        Placeholder::make('safety_note')
                            ->label('Что изменится')
                            ->content('Будет создана только постоянная связь с выбранной карточкой. Название, категория, цена и остаток карточки сайта не изменятся.'),
                    ])
                    ->requiresConfirmation()
                    ->modalSubmitActionLabel('Подтвердить привязку')
                    ->action(function (IntegrationProduct $record, array $data): void {
                        $product = app(IntegrationProductMatchDecision::class)
                            ->confirmCandidate($record, (int) $data['product_id']);

                        if (! $product) {
                            Notification::make()
                                ->warning()
                                ->title('Кандидат больше не актуален')
                                ->body('Обновите таблицу и повторите проверку вариантов.')
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title('Привязка подтверждена')
                            ->body('Товар связан с карточкой «'.$product->name.'». Решение сохранено для будущих синхронизаций.')
                            ->send();
                    }),
                Action::make('acceptSuggestion')
                    ->label('Принять')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (IntegrationProduct $record): bool => filled(
                        app(IntegrationProductMatchAdvisor::class)->explain($record)
                    ))
                    ->modalHeading('Подтвердить привязку к карточке')
                    ->modalDescription('Проверьте рекомендацию. Она не применяется автоматически.')
                    ->form([
                        Placeholder::make('recommended_product')
                            ->label('Предлагаемая карточка kotlov.by')
                            ->content(function (IntegrationProduct $record): string {
                                $advice = app(IntegrationProductMatchAdvisor::class)->explain($record);

                                return $advice
                                    ? collect([
                                        $advice['product_name'],
                                        $advice['product_sku'] ? 'SKU '.$advice['product_sku'] : null,
                                        $advice['category_name'] ? 'Категория: '.$advice['category_name'] : null,
                                    ])->filter()->implode(' · ')
                                    : 'Рекомендация больше не доступна';
                            }),
                        Placeholder::make('recommendation_basis')
                            ->label('Почему предложено')
                            ->content(function (IntegrationProduct $record): string {
                                $advice = app(IntegrationProductMatchAdvisor::class)->explain($record);

                                return $advice
                                    ? $advice['method_label'].' · '.round($advice['confidence'] * 100).'% — '.$advice['reason']
                                    : 'Кандидат отсутствует или уже изменён.';
                            }),
                        Placeholder::make('confirmation_effect')
                            ->label('Что произойдёт после подтверждения')
                            ->content(fn (IntegrationProduct $record): string => app(IntegrationProductMatchAdvisor::class)->explain($record)['warning']
                                ?? 'Никаких изменений не будет.'),
                    ])
                    ->requiresConfirmation()
                    ->modalSubmitActionLabel('Подтвердить привязку')
                    ->action(function (IntegrationProduct $record): void {
                        $product = app(IntegrationProductMatchDecision::class)
                            ->confirmSuggestion($record);
                        if (! $product) {
                            Notification::make()
                                ->warning()
                                ->title('Рекомендация больше не актуальна')
                                ->body('Обновите таблицу и проверьте кандидата ещё раз.')
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title('Привязка подтверждена')
                            ->body('Товар связан с карточкой «'.$product->name.'». Решение сохранено для будущих синхронизаций.')
                            ->send();
                    }),
                Action::make('mapSourceGroup')
                    ->label('Категория группы')
                    ->icon(Heroicon::OutlinedFolderOpen)
                    ->color('info')
                    ->visible(fn (IntegrationProduct $record): bool => filled($record->integration_category_id))
                    ->fillForm(fn (IntegrationProduct $record): array => [
                        'category_id' => $record->integrationCategory?->category_id,
                    ])
                    ->form([
                        Select::make('category_id')
                            ->label('Категория kotlov.by для группы поставщика')
                            ->options(fn (): array => self::siteCategoryOptions())
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Это правило для всей папки поставщика. Уже существующие карточки сайта не перемещаются.'),
                    ])
                    ->modalHeading(fn (IntegrationProduct $record): string => 'Куда направлять группу «'.($record->integrationCategory?->path ?? 'Без группы').'»')
                    ->action(function (IntegrationProduct $record, array $data): void {
                        $record->integrationCategory?->update([
                            'category_id' => $data['category_id'] ?? null,
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Категория группы сохранена')
                            ->body('Правило применяется ко всем товарам этой группы поставщика.')
                            ->send();
                    }),
                Action::make('applyCategorySuggestion')
                    ->label('Применить рекомендацию')
                    ->icon(Heroicon::OutlinedLightBulb)
                    ->color('warning')
                    ->visible(fn (IntegrationProduct $record): bool => filled(
                        app(IntegrationCategoryAdvisor::class)->suggest($record)
                    ))
                    ->modalHeading('Рекомендация категории')
                    ->modalDescription(function (IntegrationProduct $record): string {
                        $suggestion = app(IntegrationCategoryAdvisor::class)->suggest($record);

                        return $suggestion
                            ? $suggestion['reason'].' Уверенность: '.round($suggestion['confidence'] * 100).'%.'
                            : 'Надёжная рекомендация для этого товара не найдена.';
                    })
                    ->form([
                        Placeholder::make('suggested_category')
                            ->label('Категория kotlov.by')
                            ->content(fn (IntegrationProduct $record): string => app(IntegrationCategoryAdvisor::class)->suggest($record)['category_name'] ?? '—'),
                    ])
                    ->requiresConfirmation()
                    ->modalSubmitActionLabel('Назначить категорию')
                    ->action(function (IntegrationProduct $record): void {
                        $suggestion = app(IntegrationCategoryAdvisor::class)->suggest($record);
                        if (! $suggestion) {
                            Notification::make()
                                ->warning()
                                ->title('Рекомендация больше не актуальна')
                                ->send();

                            return;
                        }

                        $record->update(['target_category_id' => $suggestion['category_id']]);

                        Notification::make()
                            ->success()
                            ->title('Категория назначена')
                            ->body('Применена подтверждённая рекомендация: '.$suggestion['category_name'])
                            ->send();
                    }),
                Action::make('setTargetCategory')
                    ->label('Категория товара')
                    ->icon(Heroicon::OutlinedTag)
                    ->color('primary')
                    ->visible(fn (IntegrationProduct $record): bool => blank($record->product_id))
                    ->fillForm(fn (IntegrationProduct $record): array => [
                        'target_category_id' => $record->target_category_id,
                    ])
                    ->form([
                        Select::make('target_category_id')
                            ->label('Категория kotlov.by для этого товара')
                            ->options(fn (): array => self::siteCategoryOptions())
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Имеет приоритет над общей категорией группы поставщика.'),
                    ])
                    ->modalHeading(fn (IntegrationProduct $record): string => 'Категория для «'.$record->name.'»')
                    ->action(function (IntegrationProduct $record, array $data): void {
                        $record->update(['target_category_id' => $data['target_category_id'] ?? null]);

                        Notification::make()
                            ->success()
                            ->title('Категория товара сохранена')
                            ->send();
                    }),
                EditAction::make()->label('Выбрать вручную'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('assignTargetCategory')
                        ->label('Назначить категорию сайта')
                        ->icon(Heroicon::OutlinedTag)
                        ->color('primary')
                        ->form([
                            Select::make('target_category_id')
                                ->label('Категория kotlov.by')
                                ->options(fn (): array => self::siteCategoryOptions())
                                ->searchable()
                                ->preload()
                                ->required()
                                ->helperText('Назначение применяется только к товарам без привязанной карточки.'),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records
                                ->filter(fn (IntegrationProduct $record): bool => blank($record->product_id))
                                ->each->update(['target_category_id' => $data['target_category_id']]);
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('acceptSuggestions')
                        ->label('Принять выбранные предложения')
                        ->icon(Heroicon::OutlinedCheck)
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $applied = 0;
                            $skipped = 0;

                            $records->each(function (IntegrationProduct $record) use (&$applied, &$skipped): void {
                                $product = app(IntegrationProductMatchDecision::class)
                                    ->confirmSuggestion($record);

                                $product ? $applied++ : $skipped++;
                            });

                            Notification::make()
                                ->title('Массовая привязка завершена')
                                ->body("Привязано: {$applied}. Пропущено после проверки: {$skipped}.")
                                ->color($skipped > 0 ? 'warning' : 'success')
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('ignore')
                        ->label('Пометить как не относящиеся к сайту')
                        ->icon(Heroicon::OutlinedEyeSlash)
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update([
                            'product_id' => null,
                            'match_status' => 'ignored',
                            'match_method' => 'manual_ignore',
                            'match_confidence' => null,
                            'matched_at' => null,
                        ]))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIntegrationProducts::route('/'),
            'edit' => EditIntegrationProduct::route('/{record}/edit'),
        ];
    }

    private static function productUrl(IntegrationProduct $record): ?string
    {
        $categorySlug = $record->product?->category?->slug;
        $productSlug = $record->product?->slug;

        if (! $categorySlug || ! $productSlug) {
            return null;
        }

        return url('/'.$categorySlug.'/'.$productSlug);
    }

    /**
     * @return array<int, string>
     */
    private static function siteCategoryOptions(): array
    {
        return Category::query()
            ->with('parent:id,name')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name'])
            ->mapWithKeys(fn (Category $category): array => [
                $category->getKey() => $category->parent
                    ? $category->parent->name.' → '.$category->name
                    : $category->name,
            ])
            ->all();
    }
}

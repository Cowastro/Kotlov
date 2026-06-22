<?php

namespace App\Filament\Resources\Products\Tables;

use App\Jobs\RunProductCatalogAiReview;
use App\Jobs\RunProductSourceEnrichment;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
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
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('images')
                    ->label('Фото')
                    ->getStateUsing(fn($record) => url($record->imageUrl(0)))
                    ->square()
                    ->height(48)
                    ->width(48),

                TextColumn::make('name')
                    ->label('Товар')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(60)
                    ->tooltip(fn($record) => $record->name)
                    ->wrap()
                    ->description(fn($record) => $record->sku ? 'SKU: ' . $record->sku : null),

                TextColumn::make('frontend_url')
                    ->label('Сайт')
                    ->state('Открыть')
                    ->url(fn($record) => self::productUrl($record))
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->toggleable(),

                TextColumn::make('sku')
                    ->label('Артикул')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                TextColumn::make('category.name')
                    ->label('Категория')
                    ->sortable()
                    ->badge()
                    ->toggleable(),

                TextColumn::make('brand.name')
                    ->label('Бренд')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('supplier_article')
                    ->label('Арт. пост.')
                    ->getStateUsing(function ($record): string {
                        $articles = $record->supplierProducts
                            ->map(fn ($sp) => $sp->supplier_article)
                            ->filter()
                            ->unique()
                            ->values();
                        return $articles->isNotEmpty() ? $articles->implode(' / ') : '—';
                    })
                    ->copyable()
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas(
                        'supplierProducts',
                        fn ($q) => $q->where('supplier_article', 'like', "%{$search}%")
                    ))
                    ->toggleable(),

                TextColumn::make('supplier_stock')
                    ->label('Кол-во (пост.)')
                    ->getStateUsing(fn ($record): int => (int) $record->supplierProducts->sum('stock_quantity'))
                    ->toggleable(),

                TextColumn::make('supplier_price')
                    ->label('Закупка мин.')
                    ->getStateUsing(function ($record): string {
                        $min = $record->supplierProducts->min('price_byn');
                        return $min ? number_format((float) $min, 2) . ' BYN' : '—';
                    })
                    ->toggleable(),

                TextColumn::make('price')
                    ->label('Розница сайта')
                    ->sortable()
                    ->formatStateUsing(fn($state, $record) => $state > 0
                        ? number_format((float) $state, 2) . ' ' . ($record->currency ?: 'BYN')
                        : '<span class="text-danger-500 font-bold">— не задана</span>'
                    )
                    ->html(),

                TextColumn::make('margin_min')
                    ->label('Маржа мин.')
                    ->getStateUsing(function ($record): string {
                        $cost = $record->supplierProducts->min('price_byn');
                        $retail = $record->price !== null ? (float) $record->price : 0.0;

                        if (! $cost || $retail <= 0) {
                            return '—';
                        }

                        $cost = (float) $cost;
                        $margin = $retail - $cost;
                        $percent = $retail > 0 ? ($margin / $retail) * 100 : 0;

                        return number_format($margin, 2) . ' BYN / ' . number_format($percent, 1) . '%';
                    })
                    ->badge()
                    ->color(function ($record): string {
                        $cost = $record->supplierProducts->min('price_byn');
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
                    ->toggleable(),

                TextColumn::make('suppliers_list')
                    ->label('Поставщик')
                    ->getStateUsing(fn ($record): string => $record->supplierProducts
                        ->map(fn ($sp) => $sp->supplier?->name)
                        ->filter()
                        ->unique()
                        ->implode(', ') ?: '—'
                    )
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas(
                        'supplierProducts', fn ($q) => $q->whereHas(
                            'supplier', fn ($q2) => $q2->where('name', 'like', "%{$search}%")
                        )
                    ))
                    ->toggleable(),

                TextColumn::make('price_old')
                    ->label('Старая цена')
                    ->sortable()
                    ->formatStateUsing(fn($state) => $state ? number_format($state, 2) . ' BYN' : '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('currency')
                    ->label('Валюта')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('in_stock')
                    ->label('Наличие')
                    ->getStateUsing(fn($record) => $record->in_stock || $record->supplierProducts->sum('stock_quantity') > 0)
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),

                IconColumn::make('is_archived')
                    ->label('Архив')
                    ->boolean()
                    ->trueIcon('heroicon-o-archive-box')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray'),

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
                    ->html(),

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
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Обновлён')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Активность'),

                TernaryFilter::make('is_archived')
                    ->label('Архив')
                    ->placeholder('Все')
                    ->trueLabel('Только архив')
                    ->falseLabel('Без архива')
                    ->default(false),
                TernaryFilter::make('in_stock')
                    ->label('Наличие'),

                TernaryFilter::make('is_featured')
                    ->label('Хит продаж'),

                TernaryFilter::make('is_new')
                    ->label('Новинки'),

                TernaryFilter::make('is_sale')
                    ->label('Акции'),

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
                        ? $query->whereHas('supplierProducts', fn ($q) => $q->where('supplier_id', $data['value']))
                        : $query
                    ),

                Filter::make('without_photo')
                    ->label('Без фото')
                    ->query(fn(Builder $query) => $query->where(function ($q) {
                        $q->whereNull('images')
                          ->orWhere('images', '[]')
                          ->orWhere('images', '""')
                          ->orWhereRaw("JSON_LENGTH(images) = 0");
                    }))
                    ->toggle(),

                Filter::make('without_price')
                    ->label('Без цены')
                    ->query(fn(Builder $query) => $query->where('price', 0)->orWhereNull('price'))
                    ->toggle(),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('preview_source_enrichment')
                        ->label('Проверить из ссылки')
                        ->icon('heroicon-o-magnifying-glass')
                        ->color(fn (Product $record): string => self::hasProductImages($record) ? 'gray' : 'warning')
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
                BulkActionGroup::make([
                    BulkAction::make('activate')
                        ->label('Активировать')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn(Collection $records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('deactivate')
                        ->label('Деактивировать')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->action(fn(Collection $records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('set_in_stock')
                        ->label('В наличии')
                        ->icon('heroicon-o-archive-box')
                        ->color('success')
                        ->action(fn(Collection $records) => $records->each->update(['in_stock' => true]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('set_out_of_stock')
                        ->label('Нет в наличии')
                        ->icon('heroicon-o-archive-box-x-mark')
                        ->color('danger')
                        ->action(fn(Collection $records) => $records->each->update(['in_stock' => false]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('mark_featured')
                        ->label('Отметить хитом')
                        ->icon('heroicon-o-fire')
                        ->color('warning')
                        ->action(fn(Collection $records) => $records->each->update(['is_featured' => true]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('unmark_featured')
                        ->label('Снять хит')
                        ->icon('heroicon-o-fire')
                        ->color('gray')
                        ->action(fn(Collection $records) => $records->each->update(['is_featured' => false]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('mark_new')
                        ->label('Отметить новинкой')
                        ->icon('heroicon-o-sparkles')
                        ->color('info')
                        ->action(fn(Collection $records) => $records->each->update(['is_new' => true]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('unmark_new')
                        ->label('Снять новинку')
                        ->icon('heroicon-o-sparkles')
                        ->color('gray')
                        ->action(fn(Collection $records) => $records->each->update(['is_new' => false]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('mark_sale')
                        ->label('Отметить акцией')
                        ->icon('heroicon-o-tag')
                        ->color('danger')
                        ->action(fn(Collection $records) => $records->each->update(['is_sale' => true]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('unmark_sale')
                        ->label('Снять акцию')
                        ->icon('heroicon-o-tag')
                        ->color('gray')
                        ->action(fn(Collection $records) => $records->each->update(['is_sale' => false]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('archive')
                        ->label('Архивировать')
                        ->icon('heroicon-o-archive-box')
                        ->color('warning')
                        ->action(fn(Collection $records) => $records->each->update(['is_archived' => true]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('unarchive')
                        ->label('Разархивировать')
                        ->icon('heroicon-o-archive-box-x-mark')
                        ->color('success')
                        ->action(fn(Collection $records) => $records->each->update(['is_archived' => false]))
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
                        ->action(fn(Collection $records, array $data) => $records->each->update([
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
                        ->action(fn(Collection $records, array $data) => $records->each->update([
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
                                ->options(fn() => User::query()
                                    ->where('role', 'supplier')
                                    ->orderBy('name')
                                    ->pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->nullable(),
                        ])
                        ->action(fn(Collection $records, array $data) => $records->each->update([
                            'supplier_id' => $data['supplier_id'] ?? null,
                        ]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('export_csv')
                        ->label('Экспорт цен CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('gray')
                        ->action(function (Collection $records) {
                            $filename = 'products_' . now()->format('Ymd_His') . '.csv';
                            $headers = [
                                'Content-Type'        => 'text/csv; charset=UTF-8',
                                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                            ];

                            $callback = function () use ($records) {
                                $out = fopen('php://output', 'w');
                                fputs($out, "\xEF\xBB\xBF"); // BOM для Excel

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
                ]),
            ]);
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
                ->default(true),
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
            'replace_images' => (bool) ($data['replace_images'] ?? true),
            'update_specs' => (bool) ($data['update_specs'] ?? true),
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
            ->body('Товаров в задаче: ' . count($productIds) . ".\nРезультат появится в уведомлениях после выполнения очереди."));
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
            ->body('Товаров в задаче: ' . count($productIds) . ".\nРезультат и команды проверки появятся в уведомлениях."));
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

        if (!$categorySlug || !$record->slug) {
            return null;
        }

        return url('/' . $categorySlug . '/' . $record->slug);
    }
}

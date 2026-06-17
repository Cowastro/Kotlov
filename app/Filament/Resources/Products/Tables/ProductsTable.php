<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ProductSourceEnricher;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

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
                    ->limit(40)
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

                    BulkAction::make('enrich_from_source_url')
                        ->label('Обновить из ссылки')
                        ->icon('heroicon-o-link')
                        ->color('success')
                        ->form([
                            TextInput::make('source_url')
                                ->label('Ссылка на карточку товара')
                                ->url()
                                ->required()
                                ->placeholder('https://example.com/product/...'),
                            Toggle::make('preview_only')
                                ->label('Только проверить, без записи')
                                ->helperText('Покажет, что найдено. Для обновления товара снимите этот переключатель.')
                                ->default(true),
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
                                ->label('Обновить описание')
                                ->default(true),
                            Toggle::make('update_service')
                                ->label('Обновить сервис')
                                ->default(false),
                        ])
                        ->requiresConfirmation()
                        ->modalHeading('Обновить выбранные товары из ссылки')
                        ->modalDescription('Система попробует взять с указанной страницы фото, характеристики и описание. Лучше применять к одному товару или к одинаковым дублям.')
                        ->action(function (Collection $records, array $data): void {
                            $enricher = app(ProductSourceEnricher::class);
                            $previewOnly = (bool) ($data['preview_only'] ?? true);
                            $options = [
                                'preview_only' => $previewOnly,
                                'update_images' => (bool) ($data['update_images'] ?? true),
                                'replace_images' => (bool) ($data['replace_images'] ?? true),
                                'update_specs' => (bool) ($data['update_specs'] ?? true),
                                'update_content' => (bool) ($data['update_content'] ?? true),
                                'update_service' => (bool) ($data['update_service'] ?? false),
                            ];
                            $processed = 0;
                            $errors = [];
                            $preview = null;
                            $totals = [
                                'images_found' => 0,
                                'images_saved' => 0,
                                'specs_found' => 0,
                                'attribute_values_saved' => 0,
                                'service_found' => 0,
                                'content_found' => 0,
                                'short_description_found' => 0,
                            ];

                            foreach ($records as $record) {
                                try {
                                    $result = $enricher->enrich($record, (string) $data['source_url'], $options);
                                    foreach ($totals as $key => $value) {
                                        $totals[$key] += (int) ($result[$key] ?? 0);
                                    }
                                    $preview ??= $result['preview'] ?? null;
                                    foreach (($result['errors'] ?? []) as $error) {
                                        $errors[] = ($record->sku ?: $record->id) . ': ' . $error;
                                    }
                                    $processed++;
                                } catch (\Throwable $e) {
                                    $errors[] = ($record->sku ?: $record->id) . ': ' . $e->getMessage();
                                }
                            }

                            if ($previewOnly) {
                                $summary = [
                                    'Режим проверки: база не изменялась.',
                                    'Фото найдено: ' . $totals['images_found'],
                                    'Описание найдено: полное ' . $totals['content_found'] . ', короткое ' . $totals['short_description_found'],
                                    'Характеристики найдены: ' . $totals['specs_found'],
                                    'Сервис найдено строк: ' . $totals['service_found'],
                                ];
                                if (is_array($preview) && filled($preview['description'] ?? '')) {
                                    $summary[] = '';
                                    $summary[] = 'Фрагмент описания:';
                                    $summary[] = Str::limit((string) $preview['description'], 500);
                                }
                                if (is_array($preview) && ($preview['specs'] ?? []) !== []) {
                                    $summary[] = '';
                                    $summary[] = 'Первые характеристики:';
                                    foreach (array_slice($preview['specs'], 0, 5) as $spec) {
                                        $summary[] = '- ' . ($spec['key'] ?? '') . ': ' . ($spec['value'] ?? '');
                                    }
                                }
                            } else {
                                $summary = [
                                    'Фото: найдено ' . $totals['images_found'] . ', сохранено ' . $totals['images_saved'],
                                    'Описание: полное ' . $totals['content_found'] . ', короткое ' . $totals['short_description_found'],
                                    'Характеристики: найдено ' . $totals['specs_found'] . ', записано в атрибуты ' . $totals['attribute_values_saved'],
                                    'Сервис: найдено строк ' . $totals['service_found'],
                                ];
                            }

                            if ($errors !== []) {
                                $summary[] = '';
                                $summary[] = 'Ошибки:';
                                array_push($summary, ...array_slice($errors, 0, 5));
                            }

                            $notification = Notification::make()
                                ->title(($previewOnly ? 'Проверка завершена: ' : 'Обновлено товаров: ') . $processed)
                                ->body(implode("\n", $summary));

                            if ($previewOnly && $errors === [] && $processed > 0) {
                                $token = Str::random(48);
                                $applyOptions = $options;
                                $applyOptions['preview_only'] = false;

                                Cache::store('file')->put('product-source-enrichment:' . $token, [
                                    'user_id' => auth()->id(),
                                    'product_ids' => $records->pluck('id')->values()->all(),
                                    'source_url' => (string) $data['source_url'],
                                    'options' => $applyOptions,
                                ], now()->addMinutes(30));

                                $notification
                                    ->persistent()
                                    ->actions([
                                        Action::make('apply')
                                            ->label('Обновить')
                                            ->button()
                                            ->color('success')
                                            ->url(route('admin.product-source-enrichment.apply', ['token' => $token])),
                                        Action::make('cancel')
                                            ->label('Не обновлять')
                                            ->color('gray')
                                            ->url(route('admin.product-source-enrichment.cancel', ['token' => $token])),
                                    ]);
                            }

                            if ($errors === []) {
                                $notification->success();
                            } else {
                                $notification->warning();
                            }

                            $notification->send();
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

    private static function productUrl(object $record): ?string
    {
        $categorySlug = $record->category?->slug;

        if (!$categorySlug || !$record->slug) {
            return null;
        }

        return url('/' . $categorySlug . '/' . $record->slug);
    }
}

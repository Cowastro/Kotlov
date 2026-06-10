<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\User;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select as FormSelect;
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
                    ->limit(58)
                    ->description(fn($record) => collect([
                        $record->sku ? 'SKU: ' . $record->sku : null,
                        $record->slug ? '/' . $record->slug : null,
                    ])->filter()->implode(' · ')),

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
                    ->toggleable(isToggledHiddenByDefault: true),

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

                TextColumn::make('supplier.name')
                    ->label('Поставщик')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('price')
                    ->label('Цена')
                    ->sortable()
                    ->formatStateUsing(fn($state, $record) => number_format((float) $state, 2) . ' ' . ($record->currency ?: 'BYN')),

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

                TextColumn::make('updated_at')
                    ->label('Обновлён')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->paginated([25, 50, 100])
            ->modifyQueryUsing(fn(Builder $query) => $query->where('is_archived', false))
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Активность'),

                Filter::make('show_archived')
                    ->label('Включить архивные')
                    ->toggle()
                    ->indicateUsing(fn() => null)
                    ->query(fn(Builder $query) => tap($query, function ($q) {
                        // Убираем WHERE is_archived = false добавленный modifyQueryUsing
                        $inner = $q->getQuery();
                        $wheres   = $inner->wheres ?? [];
                        $bindings = $inner->bindings['where'] ?? [];
                        $newWheres   = [];
                        $newBindings = [];
                        $bindingIdx  = 0;
                        foreach ($wheres as $where) {
                            $isTarget = ($where['type'] ?? '') === 'Basic'
                                && in_array($where['column'] ?? '', ['is_archived', 'products.is_archived'])
                                && $where['value'] === false;
                            if (!$isTarget) {
                                $newWheres[] = $where;
                                if (($where['type'] ?? '') === 'Basic') {
                                    if (isset($bindings[$bindingIdx])) {
                                        $newBindings[] = $bindings[$bindingIdx];
                                    }
                                }
                            }
                            if (($where['type'] ?? '') === 'Basic') {
                                $bindingIdx++;
                            }
                        }
                        $inner->wheres = array_values($newWheres);
                        $inner->bindings['where'] = array_values($newBindings);
                    })),

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

                SelectFilter::make('supplier_id')
                    ->label('Поставщик')
                    ->relationship('supplier', 'name', fn($query) => $query->where('role', 'supplier'))
                    ->searchable(),

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
                ViewAction::make(),
                EditAction::make(),
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

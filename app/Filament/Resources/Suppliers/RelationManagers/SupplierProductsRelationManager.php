<?php

namespace App\Filament\Resources\Suppliers\RelationManagers;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Models\SupplierProduct;
use App\Services\SupplierProductAvailabilityService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SupplierProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'supplierProducts';

    protected static ?string $title = 'Предложения и привязка товаров';

    protected static ?string $label = 'Предложение';

    protected static ?string $pluralLabel = 'Предложения поставщика';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['product.brand']))
            ->columns([
                TextColumn::make('supplier_name')
                    ->label('Предложение поставщика')
                    ->description(fn (SupplierProduct $record): string => collect([
                        $record->supplier_article ? 'Арт. '.$record->supplier_article : null,
                        data_get($record->raw, 'model'),
                    ])->filter()->implode(' · '))
                    ->searchable(['supplier_name', 'supplier_article', 'supplier_article_normalized', 'raw->maker_code'])
                    ->wrap(),
                TextColumn::make('price_byn')
                    ->label('Опт')
                    ->money('BYN')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('raw.retail')
                    ->label('РРЦ')
                    ->money('BYN')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('stock_status')
                    ->label('Наличие / срок')
                    ->state(fn (SupplierProduct $record): string => match ($record->stock_status) {
                        SupplierProduct::STOCK_STATUS_ALWAYS_AVAILABLE => 'Минск · в наличии',
                        'preorder' => 'До '.((int) $record->delivery_days ?: 10).' раб. дней',
                        default => $record->stock_text ?: 'Не указано',
                    })
                    ->badge()
                    ->color(fn (SupplierProduct $record): string => match ($record->stock_status) {
                        SupplierProduct::STOCK_STATUS_ALWAYS_AVAILABLE => 'success',
                        'preorder' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('analogs')
                    ->label('Аналоги')
                    ->state(fn (SupplierProduct $record): string => collect([
                        data_get($record->raw, 'analogs.grundfos') ? 'Grundfos: '.implode(', ', data_get($record->raw, 'analogs.grundfos', [])) : null,
                        data_get($record->raw, 'analogs.wilo') ? 'Wilo: '.implode(', ', data_get($record->raw, 'analogs.wilo', [])) : null,
                    ])->filter()->implode(' · '))
                    ->limit(70)
                    ->tooltip(fn (SupplierProduct $record): string => collect([
                        data_get($record->raw, 'analogs.grundfos') ? 'Grundfos: '.implode(', ', data_get($record->raw, 'analogs.grundfos', [])) : null,
                        data_get($record->raw, 'analogs.wilo') ? 'Wilo: '.implode(', ', data_get($record->raw, 'analogs.wilo', [])) : null,
                    ])->filter()->implode(' · '))
                    ->placeholder('—'),
                TextColumn::make('product.name')
                    ->label('Карточка сайта')
                    ->description(fn (SupplierProduct $record): ?string => $record->product?->sku)
                    ->url(fn (SupplierProduct $record): ?string => $record->product
                        ? ProductResource::getUrl('edit', ['record' => $record->product])
                        : null)
                    ->color(fn (SupplierProduct $record): string => $record->product ? 'success' : 'warning')
                    ->placeholder('Не привязано')
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('match_status')
                    ->label('Привязка')
                    ->options([
                        'unmatched' => 'Не привязано',
                        'matched' => 'Привязано',
                    ])
                    ->default('unmatched'),
                SelectFilter::make('stock_status')
                    ->label('Поставка')
                    ->options([
                        SupplierProduct::STOCK_STATUS_ALWAYS_AVAILABLE => 'Минск · в наличии',
                        'preorder' => 'До 10 рабочих дней',
                    ]),
            ])
            ->recordActions([
                Action::make('linkProduct')
                    ->label(fn (SupplierProduct $record): string => $record->product_id ? 'Изменить привязку' : 'Привязать')
                    ->icon('heroicon-o-link')
                    ->color(fn (SupplierProduct $record): string => $record->product_id ? 'gray' : 'primary')
                    ->form([
                        Select::make('product_id')
                            ->label('Карточка kotlov.by')
                            ->default(fn (SupplierProduct $record): ?int => $record->product_id)
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => Product::query()
                                ->where(function ($query) use ($search): void {
                                    $query->where('name', 'like', '%'.$search.'%')
                                        ->orWhere('sku', 'like', '%'.$search.'%');
                                })
                                ->orderBy('name')
                                ->limit(50)
                                ->get(['id', 'sku', 'name'])
                                ->mapWithKeys(fn (Product $product): array => [
                                    $product->id => collect([$product->sku, $product->name])->filter()->implode(' · '),
                                ])
                                ->all())
                            ->getOptionLabelUsing(function (mixed $value): ?string {
                                $product = Product::query()->find($value, ['id', 'sku', 'name']);

                                return $product
                                    ? collect([$product->sku, $product->name])->filter()->implode(' · ')
                                    : null;
                            })
                            ->required(),
                    ])
                    ->modalHeading('Привязать предложение поставщика')
                    ->modalDescription('Цена, наличие, срок поставки и аналоги будут использоваться в выбранной карточке сайта.')
                    ->action(function (SupplierProduct $record, array $data): void {
                        $previousProductId = $record->product_id;
                        $product = Product::query()->findOrFail((int) $data['product_id']);
                        $record->update([
                            'product_id' => $product->id,
                            'product_sku' => $product->sku,
                            'match_status' => 'matched',
                            'match_confidence' => 'manual_admin',
                        ]);
                        app(SupplierProductAvailabilityService::class)->refreshMany(array_filter([
                            $previousProductId,
                            $product->id,
                        ]));

                        Notification::make()
                            ->title('Предложение привязано')
                            ->body($product->name)
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('supplier_name')
            ->emptyStateHeading('Предложений по этому фильтру нет')
            ->emptyStateDescription('Переключите фильтр привязки или выполните импорт прайса поставщика.');
    }
}

<?php

namespace App\Filament\Supplier\Resources\IntegrationProducts;

use App\Filament\Supplier\Resources\IntegrationProducts\Pages\ListIntegrationProducts;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IntegrationProductResource extends Resource
{
    protected static ?string $model = IntegrationProduct::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static ?string $navigationLabel = 'Товары из 1С / API';

    protected static ?string $modelLabel = 'товар интеграции';

    protected static ?string $pluralModelLabel = 'Товары из 1С / API';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return 'Интеграции';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source.name')
                    ->label('Источник')
                    ->badge()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Товар поставщика')
                    ->searchable()
                    ->wrap()
                    ->description(fn (IntegrationProduct $record): string => collect([
                        $record->external_sku ? 'Арт. '.$record->external_sku : null,
                        $record->external_code ? 'Код 1С '.$record->external_code : null,
                    ])->filter()->implode(' · ')),
                TextColumn::make('product.name')
                    ->label('Карточка KOTLOV')
                    ->placeholder('Не привязан')
                    ->wrap()
                    ->url(fn (IntegrationProduct $record): ?string => self::productUrl($record))
                    ->openUrlInNewTab(),
                TextColumn::make('match_status')
                    ->label('Привязка')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'matched' => 'Привязан',
                        'suggested' => 'Есть предложение',
                        'ambiguous' => 'Нужна проверка',
                        'ignored' => 'Не для сайта',
                        default => 'Не найден',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'matched' => 'success',
                        'suggested' => 'info',
                        'ambiguous' => 'warning',
                        'ignored' => 'gray',
                        default => 'danger',
                    }),
                TextColumn::make('partner_price')
                    ->label('Цена партнёру, BYN')
                    ->state(fn (IntegrationProduct $record): ?float => $record->normalizedPriceByn())
                    ->money('BYN')
                    ->placeholder('Не рассчитана')
                    ->alignRight()
                    ->description(fn (IntegrationProduct $record): ?string => $record->price === null
                        ? null
                        : 'Исходная: '.number_format((float) $record->price, 2, ',', ' ').' '
                            .$record->effectivePriceCurrency().' '
                            .($record->effectivePriceTaxMode() === IntegrationSource::PRICE_TAX_INCLUSIVE
                                ? 'с НДС'
                                : 'без НДС · +'.number_format($record->effectiveVatRate(), 0).'%')),
                TextColumn::make('stock_quantity')
                    ->label('Остаток')
                    ->state(fn (IntegrationProduct $record): ?string => $record->stock_quantity === null
                        ? null
                        : $record->formattedStockQuantity())
                    ->placeholder('Не передан')
                    ->alignRight(),
                TextColumn::make('last_seen_at')
                    ->label('Получен')
                    ->dateTime('d.m.Y H:i', 'Europe/Minsk')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('integration_source_id')
                    ->label('Источник')
                    ->options(fn (): array => self::allowedSources()->pluck('name', 'id')->all()),
                SelectFilter::make('match_status')
                    ->label('Статус привязки')
                    ->options([
                        'matched' => 'Привязан',
                        'suggested' => 'Есть предложение',
                        'ambiguous' => 'Нужна проверка',
                        'unmatched' => 'Не найден',
                        'ignored' => 'Не для сайта',
                    ]),
                TernaryFilter::make('stock_quantity')
                    ->label('Положительный остаток')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where('stock_quantity', '>', 0),
                        false: fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query
                            ->whereNull('stock_quantity')
                            ->orWhere('stock_quantity', '<=', 0)),
                    ),
            ])
            ->defaultSort('last_seen_at', 'desc')
            ->poll('60s');
    }

    public static function getEloquentQuery(): Builder
    {
        $sourceIds = self::allowedSources()->pluck('id');

        return parent::getEloquentQuery()
            ->with(['source', 'product.category'])
            ->whereIn('integration_source_id', $sourceIds);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIntegrationProducts::route('/'),
        ];
    }

    private static function allowedSources(): Builder
    {
        $supplierIds = auth()->user()?->suppliers()->pluck('suppliers.id') ?? collect();

        return IntegrationSource::query()->whereIn('supplier_id', $supplierIds);
    }

    private static function productUrl(IntegrationProduct $record): ?string
    {
        $categorySlug = $record->product?->category?->slug;
        $productSlug = $record->product?->slug;

        return $categorySlug && $productSlug ? url('/'.$categorySlug.'/'.$productSlug) : null;
    }
}

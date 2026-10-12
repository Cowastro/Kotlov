<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Filament\Resources\IntegrationProducts\IntegrationProductResource;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class IntegrationProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'integrationProducts';

    protected static ?string $title = 'Данные 1С: цены и остатки';

    protected static ?string $label = 'Позиция 1С';

    protected static ?string $pluralLabel = 'Данные 1С';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->with(['source.supplier', 'product'])
                ->orderByDesc('last_seen_at'))
            ->columns([
                TextColumn::make('source.supplier.name')
                    ->label('Поставщик')
                    ->placeholder('Не указан')
                    ->searchable(),

                TextColumn::make('source.name')
                    ->label('Источник')
                    ->description(fn (IntegrationProduct $record): ?string => $record->source?->driver
                        ? 'Канал: '.$record->source->driver
                        : null)
                    ->badge()
                    ->color('info')
                    ->searchable(),

                TextColumn::make('name')
                    ->label('Позиция в 1С')
                    ->description(fn (IntegrationProduct $record): string => collect([
                        filled($record->external_code) ? 'код '.$record->external_code : null,
                        filled($record->external_sku) ? 'арт. '.$record->external_sku : null,
                    ])->filter()->implode(' · ') ?: $record->external_id)
                    ->limit(55)
                    ->tooltip(fn (IntegrationProduct $record): string => $record->name)
                    ->wrap()
                    ->searchable(['name', 'external_code', 'external_sku', 'external_id']),

                TextColumn::make('source_price')
                    ->label('Цена источника 1С')
                    ->state(fn (IntegrationProduct $record): ?float => $record->price !== null
                        ? (float) $record->price
                        : null)
                    ->formatStateUsing(fn ($state, IntegrationProduct $record): string => $state === null
                        ? '—'
                        : number_format((float) $state, 2, ',', ' ').' '.$record->effectivePriceCurrency())
                    ->description(fn (IntegrationProduct $record): ?string => (float) $record->price > 0
                        ? ($record->effectivePriceTaxMode() === IntegrationSource::PRICE_TAX_INCLUSIVE
                            ? 'передана с НДС'
                            : 'передана без НДС · +'.number_format($record->effectiveVatRate(), 0).'%')
                        : null),

                TextColumn::make('wholesale_price')
                    ->label('Опт / цена клиенту')
                    ->state(fn (IntegrationProduct $record): ?float => $record->normalizedPriceByn())
                    ->formatStateUsing(fn ($state): string => $state === null
                        ? '—'
                        : number_format((float) $state, 2, ',', ' ').' BYN')
                    ->description('нормализовано в BYN с НДС')
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('price_byn', $direction)),

                TextColumn::make('product.price')
                    ->label('Розница сайта')
                    ->formatStateUsing(fn ($state, IntegrationProduct $record): string => $state === null || (float) $state <= 0
                        ? '—'
                        : number_format((float) $state, 2, ',', ' ').' '.($record->product?->currency ?: 'BYN')),

                TextColumn::make('margin')
                    ->label('Маржа к рознице')
                    ->state(function (IntegrationProduct $record): string {
                        $wholesale = $record->normalizedPriceByn() ?? 0.0;
                        $retail = (float) ($record->product?->price ?? 0);

                        if ($wholesale <= 0 || $retail <= 0) {
                            return '—';
                        }

                        $amount = $retail - $wholesale;
                        $percent = ($amount / $retail) * 100;

                        return number_format($amount, 2, ',', ' ').' BYN / '.number_format($percent, 1, ',', ' ').'%';
                    })
                    ->badge()
                    ->color(function (IntegrationProduct $record): string {
                        $wholesale = $record->normalizedPriceByn() ?? 0.0;
                        $retail = (float) ($record->product?->price ?? 0);

                        if ($wholesale <= 0 || $retail <= 0) {
                            return 'gray';
                        }

                        $percent = (($retail - $wholesale) / $retail) * 100;

                        return match (true) {
                            $percent <= 0 => 'danger',
                            $percent < 10 => 'warning',
                            default => 'success',
                        };
                    }),

                TextColumn::make('stock_quantity')
                    ->label('Количество 1С')
                    ->state(fn (IntegrationProduct $record): string => $record->stock_quantity === null
                        ? 'Не передано'
                        : $record->formattedStockQuantity())
                    ->badge()
                    ->color(fn (IntegrationProduct $record): string => match (true) {
                        $record->stock_quantity === null => 'gray',
                        (float) $record->stock_quantity > 0 => 'success',
                        default => 'danger',
                    })
                    ->sortable(),

                TextColumn::make('match_status')
                    ->label('Привязка')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'matched' => 'Привязан',
                        'suggested' => 'Предложение',
                        'ambiguous' => 'Несколько вариантов',
                        'ignored' => 'Игнорируется',
                        default => 'Не найден',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'matched' => 'success',
                        'suggested' => 'info',
                        'ambiguous' => 'warning',
                        'ignored' => 'gray',
                        default => 'danger',
                    }),

                TextColumn::make('last_seen_at')
                    ->label('Обновлено из 1С')
                    ->dateTime('d.m.Y H:i', 'Europe/Minsk')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('openIntegrationProduct')
                    ->label('Открыть привязку')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (IntegrationProduct $record): string => IntegrationProductResource::getUrl('edit', [
                        'record' => $record,
                    ], panel: 'admin')),
            ])
            ->emptyStateHeading('Для карточки нет привязанной позиции 1С')
            ->emptyStateDescription('После сопоставления в разделе «Привязка товаров» здесь появятся поставщик, источник, цены и остаток из 1С.');
    }
}

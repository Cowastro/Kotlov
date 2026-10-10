<?php

namespace App\Filament\Supplier\Resources\IntegrationExchangeRuns;

use App\Filament\Supplier\Resources\IntegrationExchangeRuns\Pages\ListIntegrationExchangeRuns;
use App\Models\IntegrationExchangeRun;
use App\Models\IntegrationSource;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IntegrationExchangeRunResource extends Resource
{
    protected static ?string $model = IntegrationExchangeRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static ?string $navigationLabel = 'Журнал обмена';

    protected static ?string $modelLabel = 'сеанс обмена';

    protected static ?string $pluralModelLabel = 'Журнал обмена';

    protected static ?int $navigationSort = 2;

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
                TextColumn::make('started_at')
                    ->label('Начало')
                    ->dateTime('d.m.Y H:i:s', 'Europe/Minsk')
                    ->since()
                    ->sortable(),
                TextColumn::make('source.name')
                    ->label('Источник')
                    ->badge()
                    ->sortable(),
                TextColumn::make('direction')
                    ->label('Направление')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'inbound' ? '1С / API → KOTLOV' : 'KOTLOV → 1С / API')
                    ->color(fn (string $state): string => $state === 'inbound' ? 'info' : 'warning'),
                TextColumn::make('operation')
                    ->label('Данные')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'catalog' => 'Каталог, цены и остатки',
                        'orders' => 'Заказы',
                        'order_statuses' => 'Статусы заказов',
                        default => $state,
                    }),
                TextColumn::make('status')
                    ->label('Результат')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'success' => 'Успешно',
                        'failed' => 'Ошибка',
                        default => 'Выполняется',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        default => 'info',
                    }),
                TextColumn::make('items_received')
                    ->label('Товары: получено')
                    ->numeric()
                    ->alignRight()
                    ->description(fn (IntegrationExchangeRun $record): ?string => $record->operation === 'catalog'
                        ? 'новых '.number_format((int) $record->items_created, 0, ',', ' ')
                            .' · обновлено '.number_format((int) $record->items_updated, 0, ',', ' ')
                            .((int) $record->items_skipped > 0
                                ? ' · пропущено '.number_format((int) $record->items_skipped, 0, ',', ' ')
                                : '')
                        : null),
                TextColumn::make('orders_count')
                    ->label('Заказов')
                    ->numeric()
                    ->alignRight(),
                TextColumn::make('duration_ms')
                    ->label('Время')
                    ->formatStateUsing(fn (?int $state): string => $state === null
                        ? '—'
                        : number_format($state / 1000, 1, ',', ' ').' с')
                    ->alignRight(),
                TextColumn::make('error_message')
                    ->label('Ошибка')
                    ->placeholder('—')
                    ->color('danger')
                    ->limit(80)
                    ->tooltip(fn (IntegrationExchangeRun $record): ?string => $record->error_message)
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('integration_source_id')
                    ->label('Источник')
                    ->options(fn (): array => self::allowedSources()->pluck('name', 'id')->all()),
                SelectFilter::make('direction')
                    ->label('Направление')
                    ->options([
                        'inbound' => '1С / API → KOTLOV',
                        'outbound' => 'KOTLOV → 1С / API',
                    ]),
                SelectFilter::make('operation')
                    ->label('Данные')
                    ->options([
                        'catalog' => 'Каталог, цены и остатки',
                        'orders' => 'Заказы',
                        'order_statuses' => 'Статусы заказов',
                    ]),
                SelectFilter::make('status')
                    ->label('Результат')
                    ->options([
                        'running' => 'Выполняется',
                        'success' => 'Успешно',
                        'failed' => 'Ошибка',
                    ]),
            ])
            ->defaultSort('started_at', 'desc')
            ->poll('30s');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('source')
            ->whereIn('integration_source_id', self::allowedSources()->select('id'));
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
            'index' => ListIntegrationExchangeRuns::route('/'),
        ];
    }

    private static function allowedSources(): Builder
    {
        $supplierIds = auth()->user()?->suppliers()->pluck('suppliers.id') ?? collect();

        return IntegrationSource::query()->whereIn('supplier_id', $supplierIds);
    }
}

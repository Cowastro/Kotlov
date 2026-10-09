<?php

namespace App\Filament\Resources\IntegrationExchangeRuns;

use App\Filament\Resources\IntegrationExchangeRuns\Pages\ListIntegrationExchangeRuns;
use App\Models\IntegrationExchangeRun;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class IntegrationExchangeRunResource extends Resource
{
    protected static ?string $model = IntegrationExchangeRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static ?string $navigationLabel = 'Журнал обмена';

    protected static ?string $modelLabel = 'сеанс обмена';

    protected static ?string $pluralModelLabel = 'Журнал обмена';

    protected static ?int $navigationSort = 12;

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
                TextColumn::make('started_at')->label('Начало')
                    ->dateTime('d.m.Y H:i:s', 'Europe/Minsk')->sortable(),
                TextColumn::make('source.name')->label('Источник')->searchable()->sortable(),
                TextColumn::make('direction')->label('Направление')->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'inbound' ? '1С → сайт' : 'Сайт → 1С')
                    ->color(fn (string $state): string => $state === 'inbound' ? 'info' : 'warning'),
                TextColumn::make('operation')->label('Данные')->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'catalog' => 'Каталог, цены и остатки',
                        'orders' => 'Заказы',
                        'order_statuses' => 'Статусы заказов',
                        default => $state,
                    }),
                TextColumn::make('status')->label('Статус')->badge()
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
                TextColumn::make('items_received')->label('Получено')->numeric()->alignRight(),
                TextColumn::make('items_created')->label('Новых')->numeric()->alignRight()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray'),
                TextColumn::make('items_updated')->label('Обновлено')->numeric()->alignRight(),
                TextColumn::make('stock_zeroed')->label('Снято с наличия')
                    ->state(fn (IntegrationExchangeRun $record): int => (int) data_get($record->summary, 'stock_snapshot.zeroed', 0))
                    ->numeric()->alignRight()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray'),
                TextColumn::make('orders_count')->label('Заказов')->numeric()->alignRight(),
                TextColumn::make('conflicts_count')->label('Конфликтов')
                    ->state(fn (IntegrationExchangeRun $record): int => (int) data_get($record->summary, 'conflicts', 0))
                    ->numeric()->alignRight()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('unknown_statuses_count')->label('Не распознано')
                    ->state(fn (IntegrationExchangeRun $record): int => (int) data_get($record->summary, 'unknown_statuses', 0))
                    ->numeric()->alignRight()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray'),
                TextColumn::make('files_count')->label('Частей файла')->numeric()->alignRight()->toggleable(),
                TextColumn::make('bytes_received')->label('Объём')
                    ->formatStateUsing(fn (int $state): string => self::formatBytes($state))
                    ->alignRight()->toggleable(),
                TextColumn::make('duration_ms')->label('Время')
                    ->formatStateUsing(fn (?int $state): string => $state === null
                        ? '—'
                        : number_format($state / 1000, 1, ',', ' ').' с')
                    ->alignRight(),
                TextColumn::make('error_message')->label('Ошибка')->limit(80)
                    ->tooltip(fn (IntegrationExchangeRun $record): ?string => $record->error_message)
                    ->placeholder('—')->color('danger')->wrap(),
            ])
            ->filters([
                SelectFilter::make('integration_source_id')->label('Источник')
                    ->relationship('source', 'name')->searchable()->preload(),
                SelectFilter::make('direction')->label('Направление')->options([
                    'inbound' => '1С → сайт',
                    'outbound' => 'Сайт → 1С',
                ]),
                SelectFilter::make('operation')->label('Данные')->options([
                    'catalog' => 'Каталог, цены и остатки',
                    'orders' => 'Заказы',
                    'order_statuses' => 'Статусы заказов',
                ]),
                SelectFilter::make('status')->label('Статус')->options([
                    'running' => 'Выполняется',
                    'success' => 'Успешно',
                    'failed' => 'Ошибка',
                ]),
            ])
            ->defaultSort('started_at', 'desc')
            ->poll('30s');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListIntegrationExchangeRuns::route('/')];
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' Б';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1, ',', ' ').' КБ';
        }

        return number_format($bytes / 1024 / 1024, 1, ',', ' ').' МБ';
    }
}

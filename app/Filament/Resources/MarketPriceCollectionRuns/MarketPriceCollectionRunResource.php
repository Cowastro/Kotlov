<?php

namespace App\Filament\Resources\MarketPriceCollectionRuns;

use App\Filament\Resources\MarketPriceCollectionRuns\Pages\ListMarketPriceCollectionRuns;
use App\Models\MarketPriceCollectionRun;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MarketPriceCollectionRunResource extends Resource
{
    protected static ?string $model = MarketPriceCollectionRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $navigationLabel = 'Журнал сбора рынка';

    protected static ?string $modelLabel = 'запуск сбора рынка';

    protected static ?string $pluralModelLabel = 'Журнал сбора рынка';

    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): ?string
    {
        return 'Аналитика';
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
                TextColumn::make('trigger')->label('Запуск')->badge()
                    ->formatStateUsing(fn (string $state): string => MarketPriceCollectionRun::TRIGGERS[$state] ?? $state),
                TextColumn::make('status')->label('Результат')->badge()
                    ->formatStateUsing(fn (string $state): string => MarketPriceCollectionRun::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'warning' => 'warning',
                        'failed', 'blocked' => 'danger',
                        default => 'info',
                    }),
                TextColumn::make('requested_count')->label('Запросы')->numeric()->alignRight(),
                TextColumn::make('recorded_count')->label('Записано')->numeric()->alignRight(),
                TextColumn::make('skipped_count')->label('Пропущено')->numeric()->alignRight(),
                TextColumn::make('error_count')->label('Ошибки')->numeric()->alignRight()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('warning_count')->label('Предупреждения')->numeric()->alignRight()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray'),
                TextColumn::make('duration')->label('Время')
                    ->state(fn (MarketPriceCollectionRun $record): string => $record->finished_at
                        ? number_format($record->started_at->diffInMilliseconds($record->finished_at) / 1000, 1, ',', ' ').' с'
                        : 'Выполняется')
                    ->alignRight(),
                TextColumn::make('summary')->label('Итог')->limit(70)->placeholder('—')->wrap(),
            ])
            ->filters([
                SelectFilter::make('market_price_source_id')->label('Источник')
                    ->relationship('source', 'name')->searchable()->preload(),
                SelectFilter::make('status')->label('Результат')->options(MarketPriceCollectionRun::STATUSES),
                SelectFilter::make('trigger')->label('Способ запуска')->options(MarketPriceCollectionRun::TRIGGERS),
            ])
            ->recordActions([
                Action::make('details')
                    ->label('Подробнее')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->modalHeading(fn (MarketPriceCollectionRun $record): string => 'Сбор: '.$record->source->name)
                    ->modalWidth('5xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Закрыть')
                    ->modalContent(fn (MarketPriceCollectionRun $record) => view(
                        'filament.market.collection-run-details',
                        ['run' => $record->loadMissing(['source', 'createdBy'])],
                    )),
            ])
            ->emptyStateHeading('Сбор рынка ещё не запускался')
            ->emptyStateDescription('Здесь будут видны успешные запуски, ограничения, ошибки и блокировки правил безопасности.')
            ->defaultSort('started_at', 'desc')
            ->poll('30s');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListMarketPriceCollectionRuns::route('/')];
    }
}

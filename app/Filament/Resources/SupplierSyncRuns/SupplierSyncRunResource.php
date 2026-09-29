<?php

namespace App\Filament\Resources\SupplierSyncRuns;

use App\Filament\Resources\SupplierSyncRuns\Pages\ListSupplierSyncRuns;
use App\Models\SupplierSyncRun;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SupplierSyncRunResource extends Resource
{
    protected static ?string $model = SupplierSyncRun::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;
    protected static ?string $navigationLabel = 'Журнал синхронизаций';
    protected static ?string $modelLabel = 'запуск синхронизации';
    protected static ?string $pluralModelLabel = 'Журнал синхронизаций';
    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): ?string
    {
        return 'Каталог';
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
                    ->label('Дата и время')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),

                TextColumn::make('command')
                    ->label('Синхронизация')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->description(fn (SupplierSyncRun $record): ?string => $record->supplier_names),

                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'success' => 'Успешно',
                        'failed' => 'Ошибка команды',
                        'journal_error' => 'Ошибка журнала',
                        'running' => 'Выполняется',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'running' => 'info',
                        default => 'danger',
                    }),

                TextColumn::make('changes_count')
                    ->label('Изменений')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray')
                    ->sortable(),

                TextColumn::make('price_changes_count')
                    ->label('Цены')
                    ->alignRight()
                    ->sortable(),

                TextColumn::make('stock_changes_count')
                    ->label('Остатки')
                    ->alignRight()
                    ->sortable(),

                TextColumn::make('duration_ms')
                    ->label('Время')
                    ->formatStateUsing(fn (?int $state): string => $state === null
                        ? '—'
                        : number_format($state / 1000, 1, ',', ' ') . ' с')
                    ->alignRight(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        'success' => 'Успешно',
                        'failed' => 'Ошибка команды',
                        'journal_error' => 'Ошибка журнала',
                        'running' => 'Выполняется',
                    ]),
                SelectFilter::make('command')
                    ->label('Команда')
                    ->options(fn (): array => SupplierSyncRun::query()
                        ->select('command')
                        ->distinct()
                        ->orderBy('command')
                        ->pluck('command', 'command')
                        ->all()),
            ])
            ->defaultSort('started_at', 'desc')
            ->recordActions([
                Action::make('changes')
                    ->label('Изменения')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->modalHeading(fn (SupplierSyncRun $record): string => 'Изменения: ' . $record->command)
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Закрыть')
                    ->modalContent(fn (SupplierSyncRun $record) => view(
                        'filament.resources.supplier-sync-runs.changes',
                        [
                            'run' => $record,
                            'changes' => $record->changes()->latest('created_at')->limit(300)->get(),
                            'total' => $record->changes()->count(),
                        ]
                    )),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupplierSyncRuns::route('/'),
        ];
    }
}

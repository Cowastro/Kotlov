<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\IntegrationExchangeRuns\IntegrationExchangeRunResource;
use App\Models\IntegrationExchangeRun;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentIntegrationRuns extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Последние сеансы обмена')
            ->description('Каталог, цены, остатки и заказы по всем подключениям.')
            ->query(fn (): Builder => IntegrationExchangeRun::query()->with('source')->latest('started_at'))
            ->columns([
                TextColumn::make('started_at')->label('Начало')
                    ->dateTime('d.m.Y H:i:s', 'Europe/Minsk'),
                TextColumn::make('source.name')->label('Источник'),
                TextColumn::make('direction')->label('Направление')->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'inbound' ? '1С → сайт' : 'Сайт → 1С')
                    ->color(fn (string $state): string => $state === 'inbound' ? 'info' : 'warning'),
                TextColumn::make('operation')->label('Данные')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'orders' => 'Заказы',
                        'order_statuses' => 'Статусы заказов',
                        default => 'Каталог',
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
                TextColumn::make('items_received')->label('Товаров')->numeric()->alignRight(),
                TextColumn::make('orders_count')->label('Заказов')->numeric()->alignRight(),
                TextColumn::make('duration_ms')->label('Время')
                    ->formatStateUsing(fn (?int $state): string => $state === null
                        ? '—'
                        : number_format($state / 1000, 1, ',', ' ').' с')
                    ->alignRight(),
            ])
            ->recordUrl(fn (IntegrationExchangeRun $record): string => IntegrationExchangeRunResource::getUrl('index'))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->poll('30s');
    }
}

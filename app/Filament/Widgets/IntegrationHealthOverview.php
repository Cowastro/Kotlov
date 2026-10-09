<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\IntegrationExchangeRuns\IntegrationExchangeRunResource;
use App\Filament\Resources\IntegrationProducts\IntegrationProductResource;
use App\Filament\Resources\IntegrationSources\IntegrationSourceResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\IntegrationExchangeRun;
use App\Services\Integrations\IntegrationOperationsSummary;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class IntegrationHealthOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Сегодня требует внимания';

    protected ?string $description = 'Живое состояние 1С, заказов и товарных привязок. Данные обновляются автоматически.';

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $service = app(IntegrationOperationsSummary::class);
        $summary = $service->snapshot();
        $latestRun = $summary['latest_run'];
        $lastSuccess = $summary['last_success'];

        return [
            Stat::make('Обмен интеграций', $service->healthLabel($summary['health']))
                ->description($this->healthDescription($summary, $lastSuccess))
                ->descriptionIcon(Heroicon::OutlinedArrowPathRoundedSquare)
                ->color($service->healthColor($summary['health']))
                ->url(IntegrationExchangeRunResource::getUrl('index')),
            Stat::make('Заказы ожидают 1С', number_format($summary['awaiting_orders'], 0, ',', ' '))
                ->description($summary['awaiting_orders'] > 0
                    ? 'Нужно получить и подтвердить в 1С'
                    : 'Все заказы переданы')
                ->descriptionIcon(Heroicon::OutlinedShoppingCart)
                ->color($summary['awaiting_orders'] > 0 ? 'warning' : 'success')
                ->url(OrderResource::getUrl('index')),
            Stat::make('Товары требуют решения', number_format($summary['attention_products'], 0, ',', ' '))
                ->description('Есть остаток, но нет подтверждённой привязки')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($summary['attention_products'] > 0 ? 'warning' : 'success')
                ->url(IntegrationProductResource::getUrl('index')),
            Stat::make('Активные источники', number_format($summary['active_sources'], 0, ',', ' '))
                ->description($summary['failed_runs_24h'] > 0
                    ? 'Ошибок за 24 часа: '.$summary['failed_runs_24h']
                    : ($summary['active_sources'] > 0
                        ? 'Работают: '.$summary['healthy_sources'].' · требуют внимания: '.$summary['attention_sources']
                        : ($latestRun ? 'Последний сеанс: '.$latestRun->operation : 'Нет активных подключений')))
                ->descriptionIcon(Heroicon::OutlinedSignal)
                ->color($summary['failed_runs_24h'] > 0 ? 'danger' : 'info')
                ->url(IntegrationSourceResource::getUrl('index')),
        ];
    }

    /** @param array<string, mixed> $summary */
    private function healthDescription(array $summary, ?IntegrationExchangeRun $lastSuccess): string
    {
        if ($summary['attention_sources'] > 0) {
            $names = collect($summary['attention_source_names'])->take(2)->implode(', ');
            $more = $summary['attention_sources'] > 2 ? ' +'.($summary['attention_sources'] - 2) : '';

            return 'Требуют внимания: '.$names.$more;
        }

        if ($summary['running_sources'] > 0) {
            return 'Сейчас выполняется обмен';
        }

        return $lastSuccess?->finished_at
            ? 'Последний успех '.$lastSuccess->finished_at->timezone('Europe/Minsk')->format('d.m.Y H:i')
            : 'Успешных сеансов пока нет';
    }
}

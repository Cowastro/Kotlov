<?php

namespace App\Filament\Supplier\Widgets;

use App\Filament\Supplier\Resources\IntegrationExchangeRuns\IntegrationExchangeRunResource;
use App\Filament\Supplier\Resources\IntegrationIssues\IntegrationIssueResource;
use App\Filament\Supplier\Resources\IntegrationProducts\IntegrationProductResource;
use App\Filament\Supplier\Resources\Orders\OrderResource;
use App\Services\SupplierIntegrationSummary;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SupplierIntegrationOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $supplierIds = auth()->user()?->suppliers()->pluck('suppliers.id') ?? collect();
        $summary = app(SupplierIntegrationSummary::class)->forSupplierIds($supplierIds);

        return [
            Stat::make('Подключения', $summary['source_count'])
                ->description('Активно: '.$summary['active_source_count'])
                ->color($summary['source_count'] === $summary['active_source_count'] ? 'success' : 'warning'),
            Stat::make('Обмен', $this->healthLabel($summary['health']))
                ->description($this->healthDescription($summary))
                ->color($this->healthColor($summary['health']))
                ->url(IntegrationExchangeRunResource::getUrl('index', panel: 'supplier')),
            Stat::make('Заказы', $summary['active_orders'])
                ->description('К передаче: '.$summary['pending_order_deliveries']
                    .' · ждут ответа: '.$summary['awaiting_order_responses']
                    .' · ошибок: '.$summary['failed_order_deliveries'])
                ->color(match (true) {
                    $summary['failed_order_deliveries'] > 0 => 'danger',
                    $summary['pending_order_deliveries'] > 0 || $summary['awaiting_order_responses'] > 0 => 'warning',
                    default => 'success',
                })
                ->url(OrderResource::getUrl('index', panel: 'supplier')),
            Stat::make('Товары из интеграций', $summary['total'])
                ->description('В наличии: '.$summary['in_stock'])
                ->url(IntegrationProductResource::getUrl('index', panel: 'supplier')),
            Stat::make('Привязано к KOTLOV', $summary['linked'])
                ->description('Не привязано: '.$summary['unlinked'])
                ->color($summary['unlinked'] > 0 ? 'warning' : 'success')
                ->url(IntegrationProductResource::getUrl('index', panel: 'supplier')),
            Stat::make('Проблемы данных', $summary['open_issues'])
                ->description('Без цены: '.$summary['missing_price'])
                ->color($summary['open_issues'] > 0 || $summary['missing_price'] > 0 ? 'danger' : 'success')
                ->url(IntegrationIssueResource::getUrl('index', panel: 'supplier')),
        ];
    }

    private function healthLabel(string $health): string
    {
        return match ($health) {
            'healthy' => 'Работает',
            'running' => 'Выполняется',
            'failed' => 'Ошибка',
            'stale' => 'Просрочен',
            'inactive' => 'Отключён',
            'unknown' => 'Нет успешного цикла',
            default => 'Не подключён',
        };
    }

    /** @param array<string, mixed> $summary */
    private function healthDescription(array $summary): string
    {
        if ($summary['last_success_at'] === null) {
            return 'Ожидается первый контролируемый обмен';
        }

        return 'Последний успешный: '.$summary['last_success_at']
            ->timezone('Europe/Minsk')
            ->format('d.m.Y H:i');
    }

    private function healthColor(string $health): string
    {
        return match ($health) {
            'healthy' => 'success',
            'running' => 'info',
            'failed' => 'danger',
            default => 'warning',
        };
    }
}

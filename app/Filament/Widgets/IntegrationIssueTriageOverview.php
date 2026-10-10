<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\IntegrationIssues\IntegrationIssueResource;
use App\Services\Integrations\IntegrationIssueTriageSummary;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class IntegrationIssueTriageOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Порядок работы';

    protected ?string $description = 'Начните с обмена, заказов и отсутствующих цен; затем подтверждайте подготовленные товарные связи.';

    protected ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int|array
    {
        return [
            'md' => 2,
            'xl' => 4,
        ];
    }

    protected function getStats(): array
    {
        $summary = app(IntegrationIssueTriageSummary::class)->snapshot((int) auth()->id());
        $format = fn (int $value): string => number_format($value, 0, ',', ' ');

        return [
            Stat::make('Сначала исправить', $format($summary['priority']))
                ->description('Цены: '.$format($summary['missing_price']).' · заказы: '.$format($summary['orders']).' · обмен: '.$format($summary['exchange']))
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($summary['priority'] > 0 ? 'danger' : 'success')
                ->url(IntegrationIssueResource::getUrl('index', ['tab' => 'priority'])),
            Stat::make('Можно привязать', $format($summary['ready_to_link']))
                ->description('Цена уже есть — требуется решение администратора')
                ->descriptionIcon(Heroicon::OutlinedLink)
                ->color($summary['ready_to_link'] > 0 ? 'warning' : 'success')
                ->url(IntegrationIssueResource::getUrl('index', ['tab' => 'ready-to-link'])),
            Stat::make('Готовые рекомендации', $format($summary['recommended']))
                ->description('Предложено точное или близкое соответствие')
                ->descriptionIcon(Heroicon::OutlinedLightBulb)
                ->color($summary['recommended'] > 0 ? 'info' : 'success')
                ->url(IntegrationIssueResource::getUrl('index', ['tab' => 'recommended'])),
            Stat::make('Мои задачи', $format($summary['mine']))
                ->description($summary['possible_duplicates'] > 0
                    ? 'Возможных дублей: '.$format($summary['possible_duplicates'])
                    : 'Возможных дублей нет')
                ->descriptionIcon(Heroicon::OutlinedUserCircle)
                ->color($summary['mine'] > 0 ? 'info' : 'gray')
                ->url(IntegrationIssueResource::getUrl('index', ['tab' => 'mine'])),
        ];
    }
}

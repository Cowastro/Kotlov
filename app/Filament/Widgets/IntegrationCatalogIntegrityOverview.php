<?php

namespace App\Filament\Widgets;

use App\Services\Integrations\IntegrationCatalogSummary;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class IntegrationCatalogIntegrityOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Целостность выгрузки';

    protected ?string $description = 'Показатели буферного каталога. Счётчики вкладок ниже могут пересекаться и не складываются.';

    protected ?string $pollingInterval = '60s';

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
        $summary = app(IntegrationCatalogSummary::class)->snapshot();
        $format = fn (int $value): string => number_format($value, 0, ',', ' ');
        $lastSeen = $summary['last_seen_at']
            ? $summary['last_seen_at']->timezone('Europe/Minsk')->format('d.m.Y H:i')
            : 'обмена ещё не было';
        $needsDecision = $summary['suggested'] + $summary['ambiguous'] + $summary['unmatched'];
        $priceProblems = $summary['zero_price'] + $summary['missing_price'];

        return [
            Stat::make('Вся база интеграции', $format($summary['total']))
                ->description($format($summary['in_stock']).' показано ниже · '.$format($summary['without_stock']).' без остатка скрыто · '.$lastSeen)
                ->descriptionIcon(Heroicon::OutlinedArrowDownTray)
                ->color('info'),
            Stat::make('Контроль дублей', $format($summary['unique_external_ids']).' уникальных из '.$format($summary['total']))
                ->description(match (true) {
                    $summary['duplicates'] > 0 => 'Повторяющихся ID: '.$format($summary['duplicates']),
                    $summary['identity_collision_groups'] > 0 => 'ID уникальны · возможных дублей по реквизитам: '.$format($summary['identity_collision_groups']),
                    default => 'Дублей нет · проверены ID, артикулы и штрихкоды',
                })
                ->descriptionIcon($summary['duplicates'] === 0 && $summary['identity_collision_groups'] === 0
                    ? Heroicon::OutlinedShieldCheck
                    : Heroicon::OutlinedExclamationTriangle)
                ->color(match (true) {
                    $summary['duplicates'] > 0 => 'danger',
                    $summary['identity_collision_groups'] > 0 => 'warning',
                    default => 'success',
                }),
            Stat::make('Цены готовы', $format($summary['positive_price']))
                ->description('Не передана: '.$format($summary['missing_price']).' · нулевая: '.$format($summary['zero_price']))
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color($priceProblems > 0 ? 'danger' : 'success'),
            Stat::make('Привязано к сайту', $format($summary['matched']))
                ->description('Предложений: '.$format($summary['suggested']).' · проверить: '.$format($summary['ambiguous']).' · не найдено: '.$format($summary['unmatched']))
                ->descriptionIcon(Heroicon::OutlinedLink)
                ->color($needsDecision > 0 ? 'warning' : 'success'),
        ];
    }
}

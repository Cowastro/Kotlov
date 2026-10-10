<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\IntegrationExchangeRuns\IntegrationExchangeRunResource;
use App\Filament\Resources\IntegrationProducts\IntegrationProductResource;
use App\Models\IntegrationExchangeRun;
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
            'xl' => 5,
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
        $stockRange = $summary['stock_min'] === null
            ? 'нет данных'
            : $this->formatQuantity($summary['stock_min']).'–'.$this->formatQuantity($summary['stock_max']).' шт.';
        $latestCatalogRun = IntegrationExchangeRun::query()
            ->with('source')
            ->where('direction', 'inbound')
            ->where('operation', 'catalog')
            ->latest('started_at')
            ->first();

        return [
            Stat::make('Вся номенклатура 1С', $format($summary['total']))
                ->description($summary['all_stock_positive']
                    ? 'Все позиции помечены 1С как доступные · диапазон '.$stockRange.' · проверьте склад выгрузки'
                    : $format($summary['in_stock']).' в наличии · '.$format($summary['without_stock']).' без остатка скрыто · диапазон '.$stockRange.' · '.$lastSeen)
                ->descriptionIcon($summary['all_stock_positive']
                    ? Heroicon::OutlinedExclamationTriangle
                    : Heroicon::OutlinedArrowDownTray)
                ->color($summary['all_stock_positive'] ? 'warning' : 'info'),
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
                })
                ->url($summary['identity_collision_groups'] > 0
                    ? IntegrationProductResource::getUrl('index', ['tab' => 'identity_collisions'])
                    : null),
            Stat::make('Цены готовы', $format($summary['positive_price']))
                ->description('Не передана: '.$format($summary['missing_price']).' · нулевая: '.$format($summary['zero_price']))
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color($priceProblems > 0 ? 'danger' : 'success'),
            Stat::make('Привязано к сайту', $format($summary['matched']))
                ->description('Предложений: '.$format($summary['suggested']).' · проверить: '.$format($summary['ambiguous']).' · не найдено: '.$format($summary['unmatched']))
                ->descriptionIcon(Heroicon::OutlinedLink)
                ->color($needsDecision > 0 ? 'warning' : 'success'),
            Stat::make('Последний импорт', $latestCatalogRun?->started_at
                ? $latestCatalogRun->started_at->timezone('Europe/Minsk')->format('d.m.Y H:i')
                : 'Нет записи')
                ->description($latestCatalogRun
                    ? collect([
                        $latestCatalogRun->source?->partnerName(),
                        'получено '.$format((int) $latestCatalogRun->items_received),
                        'новых '.$format((int) $latestCatalogRun->items_created),
                        'обновлено '.$format((int) $latestCatalogRun->items_updated),
                    ])->filter()->implode(' · ')
                    : ($summary['total'] > 0
                        ? 'Текущие '.$format($summary['total']).' строк относятся к прежней загрузке'
                        : 'Ожидается первый обмен'))
                ->descriptionIcon(match ($latestCatalogRun?->status) {
                    'success' => Heroicon::OutlinedCheckCircle,
                    'failed' => Heroicon::OutlinedExclamationTriangle,
                    default => Heroicon::OutlinedClock,
                })
                ->color(match ($latestCatalogRun?->status) {
                    'success' => 'success',
                    'failed' => 'danger',
                    default => 'gray',
                })
                ->url(IntegrationExchangeRunResource::getUrl('index')),
        ];
    }

    private function formatQuantity(?float $quantity): string
    {
        if ($quantity === null) {
            return '—';
        }

        return abs($quantity - round($quantity)) < 0.0005
            ? number_format($quantity, 0, ',', ' ')
            : rtrim(rtrim(number_format($quantity, 3, ',', ' '), '0'), ',');
    }
}

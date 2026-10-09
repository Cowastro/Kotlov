<?php

namespace App\Filament\Supplier\Widgets;

use App\Services\SupplierPortalSummary;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SupplierCatalogOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $supplierIds = auth()->user()?->suppliers()->pluck('suppliers.id') ?? collect();
        $summary = app(SupplierPortalSummary::class)->forSupplierIds($supplierIds);

        return [
            Stat::make('Позиций', $summary['total'])
                ->description('В назначенных вам каталогах'),
            Stat::make('В наличии', $summary['in_stock'])
                ->color('success'),
            Stat::make('Не привязаны', $summary['unlinked'])
                ->description('Нужна проверка сопоставления')
                ->color($summary['unlinked'] > 0 ? 'warning' : 'success'),
            Stat::make('Без цены', $summary['missing_price'])
                ->description('Цена отсутствует или равна нулю')
                ->color($summary['missing_price'] > 0 ? 'danger' : 'success'),
            Stat::make('Актуальность', $this->healthLabel($summary['health']))
                ->description($this->healthDescription($summary))
                ->color(match ($summary['health']) {
                    'healthy' => 'success',
                    'stale' => 'danger',
                    default => 'warning',
                }),
        ];
    }

    private function healthLabel(string $health): string
    {
        return match ($health) {
            'healthy' => 'Данные актуальны',
            'stale' => 'Есть устаревшие',
            'never' => 'Нет отметки времени',
            default => 'Каталог пуст',
        };
    }

    /** @param array{stale:int,last_synced_at:mixed} $summary */
    private function healthDescription(array $summary): string
    {
        if ($summary['last_synced_at'] === null) {
            return 'Ожидается первая синхронизация';
        }

        $lastSynced = $summary['last_synced_at']->timezone('Europe/Minsk');
        $time = $lastSynced->format('d.m.Y H:i');

        if ($summary['stale'] > 0) {
            return 'Старше 24 ч: '.$summary['stale'].' · Последняя: '.$time;
        }

        return 'Последняя: '.$time.' · '.$lastSynced->diffForHumans();
    }
}

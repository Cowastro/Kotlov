<?php

namespace App\Filament\Widgets;

use App\Models\PublicAiSelectionRun;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AiSelectionOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $today = PublicAiSelectionRun::query()->where('created_at', '>=', now()->startOfDay());
        $started = (clone $today)->count();
        $noResults = (clone $today)->where('status', 'no_results')->count();
        $opened = (clone $today)->whereNotNull('product_opened_at')->count();
        $contacts = (clone $today)->whereNotNull('contact_submitted_at')->count();

        return [
            Stat::make('Подборов сегодня', $started)->description('Все запросы посетителей')->color('info'),
            Stat::make('Без результата', $noResults)->description('Нужно проверить ассортимент')->color($noResults ? 'danger' : 'success'),
            Stat::make('Открыли товар', $opened)->description($started ? round($opened / $started * 100).'% от подборов' : 'Пока нет данных')->color('warning'),
            Stat::make('Оставили контакт', $contacts)->description($started ? round($contacts / $started * 100).'% конверсия' : 'Пока нет данных')->color('success'),
        ];
    }
}

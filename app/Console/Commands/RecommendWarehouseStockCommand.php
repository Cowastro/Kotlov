<?php

namespace App\Console\Commands;

use App\Services\Orders\OrderStockRecommendationService;
use Illuminate\Console\Command;

class RecommendWarehouseStockCommand extends Command
{
    protected $signature = 'orders:recommend-stock
        {--days=180 : Recent demand window in days}
        {--top=30 : Number of recommendations to show}
        {--source=onec : Own warehouse integration source code}';

    protected $description = 'Read-only stock recommendations from non-cancelled order history';

    public function handle(OrderStockRecommendationService $service): int
    {
        $days = max(30, (int) $this->option('days'));
        $top = max(1, min(200, (int) $this->option('top')));
        $source = trim((string) $this->option('source')) ?: 'onec';
        $rows = $service->recommendations($days, $source)
            ->filter(fn (array $row): bool => $row['recommended_purchase'] > 0)
            ->take($top)
            ->map(fn (array $row): array => [
                'SKU' => $row['sku'] ?: '—',
                'Товар' => mb_strimwidth((string) $row['name'], 0, 56, '…'),
                'Заказов' => $row['orders_all'],
                'Продано' => $row['quantity_all'],
                "За {$days} дн." => $row['quantity_recent'],
                'Последний' => $row['last_ordered_at']?->timezone('Europe/Minsk')->format('d.m.Y') ?? '—',
                'Склад 1С' => number_format($row['current_own_stock'], 0, '.', ' '),
                'Цель' => $row['target_stock'],
                'Рекоменд.' => $row['recommended_purchase'],
                'Выручка' => number_format($row['revenue_all'], 2, '.', ' '),
            ]);

        $this->info('Рекомендации рассчитаны без изменения заказов, остатков и карточек.');
        $this->line('Цель: максимальная разовая покупка или примерно два месяца недавнего спроса.');

        if ($rows->isEmpty()) {
            $this->warn('Нет позиций с рекомендуемым пополнением.');

            return self::SUCCESS;
        }

        $this->table(array_keys($rows->first()), $rows->all());

        return self::SUCCESS;
    }
}

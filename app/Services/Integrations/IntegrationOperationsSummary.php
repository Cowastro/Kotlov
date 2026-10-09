<?php

namespace App\Services\Integrations;

use App\Models\IntegrationExchangeRun;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Order;
use Carbon\CarbonInterface;

class IntegrationOperationsSummary
{
    public function __construct(private readonly IntegrationMonitoringWindow $monitoringWindow) {}

    /** @return array<string, mixed> */
    public function snapshot(?CarbonInterface $now = null): array
    {
        $now ??= now();
        $latestRun = IntegrationExchangeRun::query()
            ->with('source')
            ->latest('started_at')
            ->first();
        $lastSuccess = IntegrationExchangeRun::query()
            ->where('status', 'success')
            ->latest('finished_at')
            ->first();

        $health = match (true) {
            $latestRun?->status === 'failed' => 'failed',
            $latestRun?->status === 'running' => 'running',
            ! $lastSuccess => 'unknown',
            $lastSuccess->finished_at?->lt($now->copy()->subMinutes(15)) => 'stale',
            default => 'healthy',
        };

        return [
            'health' => $health,
            'latest_run' => $latestRun,
            'last_success' => $lastSuccess,
            'active_sources' => IntegrationSource::query()->where('is_active', true)->count(),
            'failed_runs_24h' => IntegrationExchangeRun::query()
                ->where('status', 'failed')
                ->where('started_at', '>=', $now->copy()->subDay())
                ->count(),
            'awaiting_orders' => Order::query()
                ->whereNull('onec_exported_at')
                ->when(
                    $this->monitoringWindow->ordersStartAt(),
                    fn ($query, CarbonInterface $startAt) => $query->where('created_at', '>=', $startAt),
                )
                ->count(),
            'attention_products' => IntegrationProduct::query()
                ->inStock()
                ->whereIn('match_status', ['suggested', 'ambiguous', 'unmatched'])
                ->count(),
        ];
    }

    public function healthLabel(string $health): string
    {
        return match ($health) {
            'healthy' => 'Работает',
            'running' => 'Выполняется',
            'failed' => 'Ошибка',
            'stale' => 'Задержка',
            default => 'Нет данных',
        };
    }

    public function healthColor(string $health): string
    {
        return match ($health) {
            'healthy' => 'success',
            'running' => 'info',
            'failed' => 'danger',
            default => 'warning',
        };
    }
}

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
        $activeSources = IntegrationSource::query()
            ->where('is_active', true)
            ->with(['latestExchangeRun', 'latestSuccessfulExchangeRun'])
            ->orderBy('name')
            ->get();
        $sourceHealths = $activeSources
            ->map(fn (IntegrationSource $source): array => [
                'id' => $source->id,
                'code' => $source->code,
                'name' => $source->name,
                'health' => $this->sourceHealth($source, $now),
                'last_run_at' => $source->latestExchangeRun?->started_at,
                'last_success_at' => $source->latestSuccessfulExchangeRun?->finished_at,
            ])
            ->values();
        $latestRun = IntegrationExchangeRun::query()
            ->with('source')
            ->latest('started_at')
            ->first();
        $lastSuccess = IntegrationExchangeRun::query()
            ->where('status', 'success')
            ->latest('finished_at')
            ->first();

        $health = $this->aggregateHealth($sourceHealths->pluck('health')->all());
        $attentionSources = $sourceHealths
            ->whereIn('health', ['failed', 'stale', 'unknown'])
            ->values();

        return [
            'health' => $health,
            'source_healths' => $sourceHealths->all(),
            'healthy_sources' => $sourceHealths->where('health', 'healthy')->count(),
            'running_sources' => $sourceHealths->where('health', 'running')->count(),
            'attention_sources' => $attentionSources->count(),
            'attention_source_names' => $attentionSources->pluck('name')->all(),
            'latest_run' => $latestRun,
            'last_success' => $lastSuccess,
            'active_sources' => $activeSources->count(),
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

    public function sourceHealth(IntegrationSource $source, ?CarbonInterface $now = null): string
    {
        $now ??= now();

        return match (true) {
            $source->latestExchangeRun?->status === 'failed' => 'failed',
            $source->latestExchangeRun?->status === 'running' => 'running',
            ! $source->latestSuccessfulExchangeRun => 'unknown',
            $source->latestSuccessfulExchangeRun->finished_at?->lt(
                $now->copy()->subMinutes($source->staleAfterMinutes())
            ) => 'stale',
            default => 'healthy',
        };
    }

    /** @param array<int, string> $healths */
    private function aggregateHealth(array $healths): string
    {
        foreach (['failed', 'stale', 'unknown', 'running'] as $state) {
            if (in_array($state, $healths, true)) {
                return $state;
            }
        }

        return $healths === [] ? 'unknown' : 'healthy';
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

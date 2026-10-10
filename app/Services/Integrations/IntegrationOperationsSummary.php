<?php

namespace App\Services\Integrations;

use App\Models\IntegrationExchangeRun;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class IntegrationOperationsSummary
{
    public function __construct(
        private readonly IntegrationFlowHealth $flowHealth,
        private readonly IntegrationMonitorHealth $monitorHealth,
        private readonly OrderIntegrationMonitoring $orderMonitoring,
    ) {}

    /** @return array<string, mixed> */
    public function snapshot(?CarbonInterface $now = null): array
    {
        $now ??= now();
        $activeSources = IntegrationSource::query()
            ->where('is_active', true)
            ->with(['latestExchangeRun', 'latestSuccessfulExchangeRun'])
            ->orderBy('name')
            ->get();
        $orderRoutes = $activeSources
            ->filter(fn (IntegrationSource $source): bool => $source->exportsOrders())
            ->mapWithKeys(fn (IntegrationSource $source): array => [
                $source->id => $this->orderMonitoring->snapshot($source, $now),
            ]);
        $sourceHealths = $activeSources
            ->map(function (IntegrationSource $source) use ($now, $orderRoutes): array {
                $flowSnapshot = $this->flowHealth->snapshot($source, $now);
                $orders = $orderRoutes->get($source->id, [
                    'pending_routes' => 0,
                    'awaiting_responses' => 0,
                    'delayed_routes' => 0,
                    'overdue_responses' => 0,
                ]);

                return [
                    'id' => $source->id,
                    'code' => $source->code,
                    'name' => $source->name,
                    'health' => $flowSnapshot['health'],
                    'flows' => $flowSnapshot['flows'],
                    'last_run_at' => $source->latestExchangeRun?->started_at,
                    'last_success_at' => $source->latestSuccessfulExchangeRun?->finished_at,
                    'pending_order_routes' => $orders['pending_routes'],
                    'awaiting_order_responses' => $orders['awaiting_responses'],
                    'delayed_order_routes' => $orders['delayed_routes'],
                    'overdue_order_responses' => $orders['overdue_responses'],
                ];
            })
            ->values();
        $latestRun = IntegrationExchangeRun::query()
            ->with('source')
            ->latest('started_at')
            ->first();
        $lastSuccess = IntegrationExchangeRun::query()
            ->where('status', 'success')
            ->latest('finished_at')
            ->first();
        $stagedProducts = IntegrationProduct::query()
            ->whereIn('integration_source_id', $activeSources->pluck('id'));
        $stagedProductsCount = (clone $stagedProducts)->count();
        $stagedUniqueProductsCount = DB::query()
            ->fromSub(
                (clone $stagedProducts)
                    ->select(['integration_source_id', 'external_id'])
                    ->distinct(),
                'integration_operations_unique_products',
            )
            ->count();
        $latestStagedAt = IntegrationProduct::query()
            ->whereIn('integration_source_id', $activeSources->pluck('id'))
            ->whereNotNull('last_seen_at')
            ->max('last_seen_at');

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
            'staged_products_count' => $stagedProductsCount,
            'staged_unique_products_count' => $stagedUniqueProductsCount,
            'staged_duplicate_products_count' => max(0, $stagedProductsCount - $stagedUniqueProductsCount),
            'latest_staged_at' => $latestStagedAt ? CarbonImmutable::parse($latestStagedAt) : null,
            'has_unjournaled_staging' => ! $latestRun && $stagedProductsCount > 0,
            'active_sources' => $activeSources->count(),
            'failed_runs_24h' => IntegrationExchangeRun::query()
                ->where('status', 'failed')
                ->where('started_at', '>=', $now->copy()->subDay())
                ->count(),
            'awaiting_orders' => $orderRoutes
                ->flatMap(fn (array $snapshot): array => $snapshot['pending_order_ids'])
                ->unique()
                ->count(),
            'awaiting_order_routes' => $orderRoutes->sum('pending_routes'),
            'awaiting_order_responses' => $orderRoutes->sum('awaiting_responses'),
            'delayed_order_routes' => $orderRoutes->sum('delayed_routes'),
            'overdue_order_responses' => $orderRoutes->sum('overdue_responses'),
            'attention_products' => IntegrationProduct::query()
                ->inStock()
                ->whereIn('match_status', ['suggested', 'ambiguous', 'unmatched'])
                ->count(),
            'issue_monitor' => $this->monitorHealth->snapshot($now),
        ];
    }

    public function sourceHealth(IntegrationSource $source, ?CarbonInterface $now = null): string
    {
        $now ??= now();

        return $this->flowHealth->snapshot($source, $now)['health'];
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

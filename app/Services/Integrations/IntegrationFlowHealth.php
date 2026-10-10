<?php

namespace App\Services\Integrations;

use App\Models\IntegrationExchangeRun;
use App\Models\IntegrationSource;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class IntegrationFlowHealth
{
    /** @return array{health:string,flows:array<string, array<string, mixed>>} */
    public function snapshot(IntegrationSource $source, ?CarbonInterface $now = null): array
    {
        $now = CarbonImmutable::instance($now ?? now());
        $runs = $source->exchangeRuns()
            ->whereIn('operation', ['catalog', 'orders', 'order_statuses'])
            ->latest('started_at')
            ->get();
        $flows = collect($this->flowDefinitions($source))
            ->mapWithKeys(function (array $definition, string $key) use ($runs, $now): array {
                $matchingRuns = $runs
                    ->where('direction', $definition['direction'])
                    ->where('operation', $definition['operation']);
                $latestRun = $matchingRuns->first();
                $latestSuccess = $matchingRuns->firstWhere('status', 'success');
                $status = $definition['expected'] || in_array($latestRun?->status, ['failed', 'running'], true)
                    ? $this->flowStatus($latestRun, $latestSuccess, $definition['stale_after_minutes'], $now)
                    : 'disabled';

                return [$key => [
                    ...$definition,
                    'key' => $key,
                    'status' => $status,
                    'latest_run' => $latestRun,
                    'latest_success' => $latestSuccess,
                ]];
            })
            ->all();
        $expectedStatuses = collect($flows)
            ->filter(fn (array $flow): bool => $flow['expected']
                || in_array($flow['status'], ['failed', 'running'], true))
            ->pluck('status')
            ->all();

        return [
            'health' => $this->aggregate($expectedStatuses),
            'flows' => $flows,
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function flowDefinitions(IntegrationSource $source): array
    {
        $ordersExpected = $source->exportsOrders();

        return [
            'catalog' => [
                'label' => 'Каталог, цены и остатки',
                'direction' => 'inbound',
                'operation' => 'catalog',
                'expected' => true,
                'stale_after_minutes' => max($source->staleAfterMinutes(), $source->catalogIntervalMinutes() * 2),
            ],
            'orders' => [
                'label' => 'Получение заказов в 1С',
                'direction' => 'outbound',
                'operation' => 'orders',
                'expected' => $ordersExpected,
                'stale_after_minutes' => max($source->staleAfterMinutes(), $source->orderIntervalMinutes() * 3),
            ],
            'order_statuses' => [
                'label' => 'Возврат статусов заказов',
                'direction' => 'inbound',
                'operation' => 'order_statuses',
                'expected' => $ordersExpected,
                'stale_after_minutes' => max($source->staleAfterMinutes(), $source->orderIntervalMinutes() * 3),
            ],
        ];
    }

    private function flowStatus(
        ?IntegrationExchangeRun $latestRun,
        ?IntegrationExchangeRun $latestSuccess,
        int $staleAfterMinutes,
        CarbonInterface $now,
    ): string
    {
        return match (true) {
            $latestRun?->status === 'failed' => 'failed',
            $latestRun?->status === 'running' => 'running',
            ! $latestSuccess => 'unknown',
            $latestSuccess->finished_at?->lt($now->copy()->subMinutes($staleAfterMinutes)) => 'stale',
            default => 'healthy',
        };
    }

    /** @param array<int, string> $statuses */
    private function aggregate(array $statuses): string
    {
        foreach (['failed', 'stale', 'unknown', 'running'] as $state) {
            if (in_array($state, $statuses, true)) {
                return $state;
            }
        }

        return $statuses === [] ? 'unknown' : 'healthy';
    }
}

<?php

namespace App\Services\Integrations;

use App\Models\IntegrationSource;
use App\Models\Order;
use App\Models\OrderIntegrationDelivery;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class OrderIntegrationMonitoring
{
    public function __construct(
        private readonly OrderIntegrationRouter $orderRouter,
        private readonly IntegrationMonitoringWindow $monitoringWindow,
    ) {}

    /** @return Builder<Order> */
    public function pendingDispatchQuery(IntegrationSource $source): Builder
    {
        return $this->orderRouter
            ->eligibleOrders($source)
            ->when(
                $this->monitoringWindow->ordersStartAtFor($source),
                fn (Builder $query, CarbonInterface $startAt): Builder => $query->where('created_at', '>=', $startAt),
            );
    }

    /** @return Builder<Order> */
    public function delayedDispatchQuery(IntegrationSource $source, CarbonInterface $now): Builder
    {
        $cutoff = $now->copy()->subMinutes($source->orderDispatchDelayMinutes());

        return $this->pendingDispatchQuery($source)
            ->where('created_at', '<=', $cutoff)
            ->whereDoesntHave('integrationDeliveries', fn (Builder $query): Builder => $query
                ->where('integration_source_id', $source->id)
                ->where('last_attempted_at', '>', $cutoff));
    }

    /** @return Builder<OrderIntegrationDelivery> */
    public function awaitingResponseQuery(IntegrationSource $source, ?CarbonInterface $now = null): Builder
    {
        return OrderIntegrationDelivery::query()
            ->where('integration_source_id', $source->id)
            ->whereNotNull('exported_at')
            ->whereNull('status_received_at')
            ->when(
                $now,
                fn (Builder $query, CarbonInterface $now): Builder => $query->where(
                    'exported_at',
                    '<=',
                    $now->copy()->subMinutes($source->orderResponseTimeoutMinutes()),
                ),
            );
    }

    /** @return Builder<Order>|null */
    public function legacyAwaitingResponseQuery(IntegrationSource $source, ?CarbonInterface $now = null): ?Builder
    {
        if ($source->code !== 'onec') {
            return null;
        }

        return Order::query()
            ->whereNotNull('onec_exported_at')
            ->whereNull('onec_status_received_at')
            ->whereDoesntHave('integrationDeliveries', fn (Builder $query): Builder => $query
                ->where('integration_source_id', $source->id))
            ->when(
                $now,
                fn (Builder $query, CarbonInterface $now): Builder => $query->where(
                    'onec_exported_at',
                    '<=',
                    $now->copy()->subMinutes($source->orderResponseTimeoutMinutes()),
                ),
            );
    }

    /** @return array{pending_order_ids:array<int,int>,pending_routes:int,awaiting_responses:int,delayed_routes:int,overdue_responses:int} */
    public function snapshot(IntegrationSource $source, ?CarbonInterface $now = null): array
    {
        $now ??= now();
        $pendingOrderIds = $this->pendingDispatchQuery($source)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $legacyAwaiting = $this->legacyAwaitingResponseQuery($source);
        $legacyOverdue = $this->legacyAwaitingResponseQuery($source, $now);

        return [
            'pending_order_ids' => $pendingOrderIds,
            'pending_routes' => count($pendingOrderIds),
            'awaiting_responses' => $this->awaitingResponseQuery($source)->count()
                + ($legacyAwaiting?->count() ?? 0),
            'delayed_routes' => $this->delayedDispatchQuery($source, $now)->count(),
            'overdue_responses' => $this->awaitingResponseQuery($source, $now)->count()
                + ($legacyOverdue?->count() ?? 0),
        ];
    }
}

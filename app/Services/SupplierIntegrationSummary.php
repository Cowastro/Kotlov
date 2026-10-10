<?php

namespace App\Services;

use App\Models\IntegrationExchangeRun;
use App\Models\IntegrationIssue;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Order;
use App\Models\OrderIntegrationDelivery;
use App\Services\Integrations\IntegrationFlowHealth;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class SupplierIntegrationSummary
{
    public function __construct(private readonly IntegrationFlowHealth $flowHealth) {}

    /**
     * @param  iterable<int, int|string>  $supplierIds
     * @return array<string, mixed>
     */
    public function forSupplierIds(iterable $supplierIds, ?CarbonInterface $now = null): array
    {
        $ids = collect($supplierIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
        $now = CarbonImmutable::instance($now ?? now());
        $sources = IntegrationSource::query()
            ->whereIn('supplier_id', $ids)
            ->orderBy('name')
            ->get();
        $sourceIds = $sources->pluck('id');
        $products = IntegrationProduct::query()->whereIn('integration_source_id', $sourceIds);
        $orders = Order::query()
            ->whereHas('items.integrationProduct', fn ($query) => $query
                ->whereIn('integration_source_id', $sourceIds));
        $deliveries = OrderIntegrationDelivery::query()
            ->whereIn('integration_source_id', $sourceIds);
        $statuses = $sources
            ->map(fn (IntegrationSource $source): string => $source->is_active
                ? $this->flowHealth->snapshot($source, $now)['health']
                : 'inactive')
            ->all();
        $latestSuccessValue = IntegrationExchangeRun::query()
            ->whereIn('integration_source_id', $sourceIds)
            ->where('status', 'success')
            ->max('finished_at');

        return [
            'sources' => $sources,
            'source_count' => $sources->count(),
            'active_source_count' => $sources->where('is_active', true)->count(),
            'total' => (clone $products)->count(),
            'in_stock' => (clone $products)->inStock()->count(),
            'linked' => (clone $products)->whereNotNull('product_id')->count(),
            'unlinked' => (clone $products)->whereNull('product_id')->count(),
            'missing_price' => (clone $products)
                ->where(fn ($query) => $query->whereNull('price')->orWhere('price', '<=', 0))
                ->count(),
            'open_issues' => IntegrationIssue::query()
                ->whereIn('integration_source_id', $sourceIds)
                ->open()
                ->count(),
            'active_orders' => (clone $orders)
                ->whereIn('status', ['new', 'confirmed', 'processing'])
                ->count(),
            'pending_order_deliveries' => (clone $deliveries)
                ->where('status', OrderIntegrationDelivery::STATUS_PENDING)
                ->count(),
            'awaiting_order_responses' => (clone $deliveries)
                ->where('status', OrderIntegrationDelivery::STATUS_SENT)
                ->count(),
            'failed_order_deliveries' => (clone $deliveries)
                ->where('status', OrderIntegrationDelivery::STATUS_FAILED)
                ->count(),
            'health' => $this->aggregateHealth($statuses),
            'last_success_at' => filled($latestSuccessValue)
                ? CarbonImmutable::parse($latestSuccessValue)
                : null,
        ];
    }

    /** @param array<int, string> $statuses */
    private function aggregateHealth(array $statuses): string
    {
        foreach (['failed', 'inactive', 'stale', 'unknown', 'running'] as $status) {
            if (in_array($status, $statuses, true)) {
                return $status;
            }
        }

        return $statuses === [] ? 'empty' : 'healthy';
    }
}

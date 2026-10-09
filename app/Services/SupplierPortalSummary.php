<?php

namespace App\Services;

use App\Models\SupplierProduct;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class SupplierPortalSummary
{
    /**
     * @param  iterable<int, int|string>  $supplierIds
     * @return array{
     *     total:int,
     *     in_stock:int,
     *     unlinked:int,
     *     missing_price:int,
     *     stale:int,
     *     last_synced_at:?CarbonImmutable,
     *     health:string
     * }
     */
    public function forSupplierIds(iterable $supplierIds, ?CarbonInterface $now = null): array
    {
        $ids = collect($supplierIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
        $now = CarbonImmutable::instance($now ?? now());
        $products = SupplierProduct::query()->forSupplierIds($ids);
        $total = (clone $products)->count();
        $lastSyncedValue = (clone $products)->max('last_synced_at');
        $lastSyncedAt = filled($lastSyncedValue)
            ? CarbonImmutable::parse($lastSyncedValue)
            : null;
        $stale = $total === 0
            ? 0
            : (clone $products)->stale($now)->count();

        return [
            'total' => $total,
            'in_stock' => (clone $products)->available()->count(),
            'unlinked' => (clone $products)->whereNull('product_id')->count(),
            'missing_price' => (clone $products)
                ->where(fn ($query) => $query
                    ->whereNull('price_byn')
                    ->orWhere('price_byn', '<=', 0))
                ->count(),
            'stale' => $stale,
            'last_synced_at' => $lastSyncedAt,
            'health' => match (true) {
                $total === 0 => 'empty',
                $lastSyncedAt === null => 'never',
                $stale > 0 => 'stale',
                default => 'healthy',
            },
        ];
    }
}

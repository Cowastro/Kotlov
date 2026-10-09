<?php

namespace App\Services\Integrations;

use App\Models\IntegrationProduct;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class IntegrationCatalogSummary
{
    public function __construct(private readonly IntegrationIdentityCollisionFinder $collisionFinder) {}

    /**
     * @return array{
     *     total:int,
     *     unique_external_ids:int,
     *     duplicates:int,
     *     identity_collision_groups:int,
     *     identity_collision_products:int,
     *     source_count:int,
     *     in_stock:int,
     *     without_stock:int,
     *     positive_price:int,
     *     zero_price:int,
     *     missing_price:int,
     *     matched:int,
     *     suggested:int,
     *     ambiguous:int,
     *     unmatched:int,
     *     ignored:int,
     *     last_seen_at:?Carbon
     * }
     */
    public function snapshot(?int $sourceId = null): array
    {
        $products = IntegrationProduct::query()
            ->when($sourceId, fn (Builder $query): Builder => $query->where('integration_source_id', $sourceId));

        $total = (clone $products)->count();
        $uniqueKeys = (clone $products)
            ->select(['integration_source_id', 'external_id'])
            ->distinct();
        $uniqueExternalIds = DB::query()->fromSub($uniqueKeys, 'integration_catalog_unique_keys')->count();

        $statusCounts = (clone $products)
            ->selectRaw('match_status, COUNT(*) as aggregate')
            ->groupBy('match_status')
            ->pluck('aggregate', 'match_status');
        $lastSeenAt = (clone $products)->max('last_seen_at');
        $identityCollisions = $this->collisionFinder->find($sourceId);
        $identityCollisionProductIds = collect($identityCollisions)
            ->flatMap(fn (array $collision): array => array_column($collision['products'], 'id'))
            ->unique()
            ->count();

        return [
            'total' => $total,
            'unique_external_ids' => $uniqueExternalIds,
            'duplicates' => max(0, $total - $uniqueExternalIds),
            'identity_collision_groups' => count($identityCollisions),
            'identity_collision_products' => $identityCollisionProductIds,
            'source_count' => (clone $products)->distinct()->count('integration_source_id'),
            'in_stock' => (clone $products)->inStock()->count(),
            'without_stock' => (clone $products)->where(fn (Builder $query): Builder => $query
                ->whereNull('stock_quantity')
                ->orWhere('stock_quantity', '<=', 0))->count(),
            'positive_price' => (clone $products)->where('price', '>', 0)->count(),
            'zero_price' => (clone $products)->whereNotNull('price')->where('price', '<=', 0)->count(),
            'missing_price' => (clone $products)->whereNull('price')->count(),
            'matched' => (int) ($statusCounts['matched'] ?? 0),
            'suggested' => (int) ($statusCounts['suggested'] ?? 0),
            'ambiguous' => (int) ($statusCounts['ambiguous'] ?? 0),
            'unmatched' => (int) ($statusCounts['unmatched'] ?? 0),
            'ignored' => (int) ($statusCounts['ignored'] ?? 0),
            'last_seen_at' => $lastSeenAt
                ? Carbon::parse((string) $lastSeenAt)
                : null,
        ];
    }
}

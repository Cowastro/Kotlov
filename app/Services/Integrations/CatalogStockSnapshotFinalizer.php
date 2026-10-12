<?php

namespace App\Services\Integrations;

use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Services\SupplierProductAvailabilityService;
use Carbon\CarbonInterface;

class CatalogStockSnapshotFinalizer
{
    /**
     * Mark offers omitted from a completed full CommerceML snapshot as out of stock.
     *
     * This must only run after 1C sends mode=complete. Individual CommerceML files
     * can be chunked, so finalizing after mode=import would incorrectly zero items
     * that are present in a later part of the same exchange.
     */
    public function finalize(IntegrationSource $source, CarbonInterface $snapshotStartedAt): int
    {
        $omitted = IntegrationProduct::query()
            ->whereBelongsTo($source, 'source')
            ->where(function ($query) use ($snapshotStartedAt): void {
                $query->whereNull('last_offer_seen_at')
                    ->orWhere('last_offer_seen_at', '<', $snapshotStartedAt);
            });

        $zeroed = (clone $omitted)->where('stock_quantity', '>', 0)->count();
        $productIds = (clone $omitted)
            ->where('match_status', 'matched')
            ->whereNotNull('product_id')
            ->pluck('product_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->all();

        $omitted->update([
            'stock_quantity' => 0,
            'stock_confirmed_at' => now(),
        ]);

        app(SupplierProductAvailabilityService::class)->refreshMany($productIds);

        return $zeroed;
    }
}

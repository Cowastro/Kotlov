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

        // A completed 1C upload may contain only a subset of the catalogue.
        // Absence from that subset is weaker evidence than an explicit zero in
        // an offer, so keep the last confirmed positive balance for its full
        // validity window before treating the omitted row as unavailable.
        $expiredOmitted = (clone $omitted)->where(function ($query) use ($source): void {
            $query->whereNull('stock_confirmed_at')
                ->orWhere('stock_confirmed_at', '<', now()->subMinutes($source->stockValidityMinutes()));
        });

        $zeroed = (clone $expiredOmitted)->where('stock_quantity', '>', 0)->count();
        $productIds = (clone $expiredOmitted)
            ->where('match_status', 'matched')
            ->whereNotNull('product_id')
            ->pluck('product_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->all();

        $expiredOmitted->update([
            'stock_quantity' => 0,
            'stock_confirmed_at' => now(),
        ]);

        app(SupplierProductAvailabilityService::class)->refreshMany($productIds);

        return $zeroed;
    }
}

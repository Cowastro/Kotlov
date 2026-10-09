<?php

namespace App\Services\Integrations;

use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
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
        return IntegrationProduct::query()
            ->whereBelongsTo($source, 'source')
            ->where('stock_quantity', '>', 0)
            ->where(function ($query) use ($snapshotStartedAt): void {
                $query->whereNull('last_offer_seen_at')
                    ->orWhere('last_offer_seen_at', '<', $snapshotStartedAt);
            })
            ->update(['stock_quantity' => 0]);
    }
}

<?php

namespace App\Services\Integrations\OneCBridge;

use App\Models\IntegrationProduct;
use App\Models\OneCItem;

/** Publish the selling warehouse's free stock to existing, exact 1C links. */
class OneCStockProjection
{
    public function refresh(): int
    {
        $changed = 0;
        $productIds = [];
        $links = IntegrationProduct::query()->whereHas('source', fn ($q) => $q->where('code', 'onec'))
            ->get(['id', 'external_id', 'product_id', 'match_status', 'stock_confirmed_at'])
            ->groupBy(fn ($row) => strtolower(explode('#', $row->external_id)[0]));
        OneCItem::query()->whereNotNull('stock_synced_at')->where('stock_synced_at', '>', now()->subHours(3))
            ->where('is_folder', false)->where('deletion_mark', false)
            ->chunkById(300, function ($items) use (&$changed, &$productIds, $links): void {
                foreach ($items as $item) {
                    $rows = $links->get(strtolower($item->ref_key), collect());
                    // A parent total cannot be assigned to an individual characteristic.
                    // Ambiguous links must be resolved by a manager, never by stock sync.
                    if ($rows->contains(fn ($row) => str_contains($row->external_id, '#'))
                        || $rows->where('match_status', '!=', 'ignored')->pluck('product_id')->filter()->unique()->count() > 1) {
                        continue;
                    }
                    foreach ($rows as $row) {
                        if ($row->match_status === 'ignored' || ! $row->product_id) {
                            continue;
                        }
                        // Only stock fields: purchase prices, bindings and order snapshots stay intact.
                        $changed += IntegrationProduct::query()->whereKey($row->id)->update([
                            'stock_quantity' => OneCStock::free($item),
                            'stock_confirmed_at' => $item->stock_synced_at,
                        ]);
                        $productIds[] = $row->product_id;
                    }
                }
            });
        app(\App\Services\SupplierProductAvailabilityService::class)->refreshMany($productIds);

        return $changed;
    }
}

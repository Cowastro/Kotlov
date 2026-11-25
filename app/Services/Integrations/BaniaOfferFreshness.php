<?php

namespace App\Services\Integrations;

use App\Models\Product;
use App\Models\SupplierProduct;
use Illuminate\Support\Facades\DB;

/** Historical prices remain stored for audit, but an expired BANIA-only offer cannot be sold as current. */
class BaniaOfferFreshness
{
    public const MAX_AGE_HOURS = 48;

    public function quarantine(int $supplierId): int
    {
        $count = 0;
        SupplierProduct::query()->where('supplier_id', $supplierId)
            ->where(fn ($q) => $q->where('last_synced_at', '<', now()->subHours(self::MAX_AGE_HOURS))->orWhereNull('last_synced_at'))
            ->orderBy('id')->chunkById(100, function ($offers) use (&$count, $supplierId): void {
                foreach ($offers as $offer) {
                    DB::transaction(function () use ($offer, $supplierId): void {
                        $offer = SupplierProduct::query()->lockForUpdate()->findOrFail($offer->id);
                        $raw = $offer->raw ?? [];
                        if (! isset($raw['bania_sync_hold'])) {
                            $raw['bania_sync_hold'] = ['at' => now()->toIso8601String(), 'price_hold_owned' => false,
                                'previous_stock_quantity' => $offer->stock_quantity, 'previous_stock_status' => $offer->stock_status];
                        }
                        $product = $offer->product_id ? Product::query()->lockForUpdate()->find($offer->product_id) : null;
                        if ($product && ! $product->requires_price_confirmation && ! $product->manual_stock_management
                            && $product->manual_price_updated_at === null && ! $this->hasAnotherChannel($product->id, $supplierId)) {
                            $product->forceFill(['requires_price_confirmation' => true])->saveQuietly();
                            $raw['bania_sync_hold']['price_hold_owned'] = true;
                        }
                        // Keep the original observation date; now() would falsely confirm the old supplier file.
                        $offer->forceFill(['in_stock' => false, 'stock_quantity' => null, 'stock_status' => 'unknown',
                            'stock_text' => 'Данные поставщика устарели — наличие требует уточнения', 'raw' => $raw])->saveQuietly();
                        if ($product && ! $product->manual_stock_management && ! $this->hasAnotherChannel($product->id, $supplierId)) {
                            $product->forceFill(['in_stock' => false, 'stock_qty' => null, 'availability_status' => Product::AVAILABILITY_CHECK])->saveQuietly();
                        }
                    });
                    $count++;
                }
            });

        return $count;
    }

    /** Only our own automatic hold may be released by a freshly checked retail row. */
    public function confirmRetail(int $offerId): void
    {
        $offer = SupplierProduct::query()->find($offerId);
        $raw = $offer?->raw ?? [];
        if (! $offer || ! isset($raw['bania_sync_hold'])) {
            return;
        }
        if (data_get($raw, 'bania_sync_hold.price_hold_owned') && $offer->product_id) {
            Product::query()->whereKey($offer->product_id)->whereNull('manual_price_updated_at')
                ->update(['requires_price_confirmation' => false]);
        }
        $raw['bania_last_retail_verified_at'] = now()->toIso8601String();
        unset($raw['bania_sync_hold']);
        $offer->forceFill(['raw' => $raw])->saveQuietly();
    }

    private function hasAnotherChannel(int $productId, int $supplierId): bool
    {
        return DB::table('supplier_products as sp')->join('suppliers as s', 's.id', '=', 'sp.supplier_id')
            ->where('sp.product_id', $productId)->where('sp.supplier_id', '!=', $supplierId)->where('s.is_active', true)->exists()
            || DB::table('integration_products as ip')->join('integration_sources as s', 's.id', '=', 'ip.integration_source_id')
                ->where('ip.product_id', $productId)->where('ip.match_status', 'matched')->where('s.is_active', true)->exists();
    }
}

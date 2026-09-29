<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class SupplierProductAvailabilityService
{
    public function refresh(int $productId, $now = null): void
    {
        $this->refreshMany([$productId], $now);
    }

    /** @param array<int, int|string> $productIds */
    public function refreshMany(array $productIds, $now = null): void
    {
        $productIds = array_values(array_unique(array_filter(
            array_map('intval', $productIds),
            fn (int $id): bool => $id > 0
        )));

        if ($productIds === []) {
            return;
        }

        $now ??= now();
        $stockByProduct = DB::table('supplier_products as sp')
            ->join('suppliers as s', 's.id', '=', 'sp.supplier_id')
            ->where('s.is_active', true)
            ->whereIn('sp.product_id', $productIds)
            ->groupBy('sp.product_id')
            ->selectRaw('sp.product_id')
            ->selectRaw('MAX(CASE WHEN sp.in_stock = 1 THEN 1 ELSE 0 END) as confirmed_in_stock')
            ->selectRaw('SUM(CASE WHEN sp.stock_quantity IS NOT NULL THEN 1 ELSE 0 END) as known_quantity_count')
            ->selectRaw('SUM(CASE WHEN sp.stock_quantity IS NOT NULL THEN sp.stock_quantity ELSE 0 END) as total_quantity')
            ->get()
            ->keyBy('product_id');

        foreach ($productIds as $productId) {
            $stock = $stockByProduct->get($productId);
            $confirmedInStock = $stock !== null && (bool) $stock->confirmed_in_stock;
            $stockQty = $stock !== null && (int) $stock->known_quantity_count > 0
                ? max(0, (int) $stock->total_quantity)
                : null;

            DB::table('products')->where('id', $productId)->update([
                'in_stock' => $confirmedInStock,
                'stock_qty' => $stockQty,
                'availability_status' => $confirmedInStock
                    ? Product::AVAILABILITY_IN_STOCK
                    : Product::AVAILABILITY_CHECK,
                'updated_at' => $now,
            ]);
        }
    }
}

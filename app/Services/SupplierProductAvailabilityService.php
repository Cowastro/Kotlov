<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        if (! Schema::hasColumns('products', ['in_stock', 'stock_qty', 'availability_status'])) {
            return;
        }

        $now ??= now();
        $products = DB::table('products')
            ->whereIn('id', $productIds)
            ->get(['id', 'in_stock', 'stock_qty', 'availability_status'])
            ->keyBy('id');
        $stockByProduct = Schema::hasTable('supplier_products') && Schema::hasTable('suppliers')
            ? DB::table('supplier_products as sp')
                ->join('suppliers as s', 's.id', '=', 'sp.supplier_id')
                ->where('s.is_active', true)
                ->whereIn('sp.product_id', $productIds)
                ->groupBy('sp.product_id')
                ->selectRaw('sp.product_id')
                ->selectRaw('MAX(CASE WHEN sp.in_stock = 1 THEN 1 ELSE 0 END) as confirmed_in_stock')
                ->selectRaw('SUM(CASE WHEN sp.stock_quantity IS NOT NULL THEN 1 ELSE 0 END) as known_quantity_count')
                ->selectRaw('SUM(CASE WHEN sp.stock_quantity IS NOT NULL THEN sp.stock_quantity ELSE 0 END) as total_quantity')
                ->get()
                ->keyBy('product_id')
            : collect();
        $integrationStockByProduct = DB::table('integration_products as ip')
            ->join('integration_sources as source', 'source.id', '=', 'ip.integration_source_id')
            ->where('source.is_active', true)
            ->where('source.update_stock', true)
            ->where('ip.match_status', 'matched')
            ->whereIn('ip.product_id', $productIds)
            ->groupBy('ip.product_id')
            ->selectRaw('ip.product_id')
            ->selectRaw('SUM(CASE WHEN ip.stock_quantity IS NOT NULL THEN 1 ELSE 0 END) as known_quantity_count')
            ->selectRaw('SUM(CASE WHEN ip.stock_quantity IS NOT NULL THEN ip.stock_quantity ELSE 0 END) as total_quantity')
            ->get()
            ->keyBy('product_id');

        foreach ($productIds as $productId) {
            $product = $products->get($productId);
            if ($product === null) {
                continue;
            }

            $integrationStock = $integrationStockByProduct->get($productId);
            $legacyStock = $stockByProduct->get($productId);
            if ($integrationStock === null && $legacyStock === null) {
                continue;
            }

            if ($integrationStock !== null) {
                $stockQty = $this->summaryStockQty(
                    $integrationStock->known_quantity_count,
                    $integrationStock->total_quantity,
                );
                $confirmedInStock = $stockQty !== null && $stockQty > 0;
            } else {
                $stockQty = $legacyStock !== null
                    ? $this->summaryStockQty($legacyStock->known_quantity_count, $legacyStock->total_quantity)
                    : null;
                $confirmedInStock = $legacyStock !== null && (bool) $legacyStock->confirmed_in_stock;
            }
            $availability = $confirmedInStock
                ? Product::AVAILABILITY_IN_STOCK
                : Product::AVAILABILITY_CHECK;

            $currentQty = $product->stock_qty === null ? null : (int) $product->stock_qty;
            if ((bool) $product->in_stock === $confirmedInStock
                && $currentQty === $stockQty
                && (string) $product->availability_status === $availability) {
                continue;
            }

            DB::table('products')->where('id', $productId)->update([
                'in_stock' => $confirmedInStock,
                'stock_qty' => $stockQty,
                'availability_status' => $availability,
                'updated_at' => $now,
            ]);
        }
    }

    private function summaryStockQty(mixed $knownQuantityCount, mixed $totalQuantity): ?int
    {
        if ((int) $knownQuantityCount <= 0) {
            return null;
        }

        $total = (float) $totalQuantity;
        if ($total <= 0) {
            return 0;
        }

        // products.stock_qty is an integer summary, while supplier channels
        // can contain fractional units (for example, a partly used sheet).
        // Keep the exact amount on the channel and preserve positive
        // availability in the product summary instead of truncating it to 0.
        return max(1, (int) $total);
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('integration_products')
            || ! Schema::hasTable('supplier_products')
            || ! Schema::hasTable('suppliers')) {
            return;
        }

        $item = DB::table('integration_products')
            ->where('external_code', 'УТ-00009075')
            ->where('match_status', 'matched')
            ->whereNotNull('product_id')
            ->first();
        $purchasePrice = (float) ($item->price_byn ?? 0);
        if (! $item || $purchasePrice <= 0) {
            return;
        }

        $supplierIds = DB::table('suppliers')
            ->where('name', 'ООО «СанБизнесГруп»')
            ->pluck('id');
        if ($supplierIds->isEmpty()) {
            return;
        }

        $stockQuantity = (float) ($item->stock_quantity ?? 0);
        DB::table('supplier_products')
            ->where('product_id', $item->product_id)
            ->whereIn('supplier_id', $supplierIds)
            ->update([
                'price' => $purchasePrice,
                'currency' => 'BYN',
                'currency_rate' => 1,
                'price_byn' => $purchasePrice,
                'stock_quantity' => $stockQuantity,
                'in_stock' => $stockQuantity > 0,
                'stock_status' => $stockQuantity > 0 ? 'in_stock' : 'out_of_stock',
                'stock_text' => $stockQuantity > 0 ? 'В наличии' : 'Нет в наличии',
                'last_stock_synced_at' => $item->stock_confirmed_at ?? $item->last_seen_at ?? now(),
                'last_synced_at' => $item->last_seen_at ?? now(),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // The former duplicate values were stale and must not be restored.
    }
};

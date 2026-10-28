<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('integration_products')
            || ! Schema::hasTable('integration_sources')
            || ! Schema::hasTable('supplier_products')) {
            return;
        }

        $item = DB::table('integration_products')
            ->where('external_code', 'УТ-00009075')
            ->where('match_status', 'matched')
            ->whereNotNull('product_id')
            ->first();
        if (! $item) {
            return;
        }

        $source = DB::table('integration_sources')->find($item->integration_source_id);
        if (! $source?->supplier_id) {
            return;
        }

        $purchasePrice = (float) ($item->price_byn ?? 0);
        $stockQuantity = (float) ($item->stock_quantity ?? 0);
        if ($purchasePrice <= 0) {
            return;
        }

        DB::table('supplier_products')
            ->where('product_id', $item->product_id)
            ->where('supplier_id', $source->supplier_id)
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
        // Source data is authoritative; restoring the stale offer is unsafe.
    }
};

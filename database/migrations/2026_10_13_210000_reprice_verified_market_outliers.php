<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prices verified against current Belarusian market offers on 2026-10-13.
     * Supplier-linked products are deliberately left to their import channels.
     */
    public function up(): void
    {
        if (! Schema::hasTable('products')
            || ! Schema::hasTable('supplier_products')
            || ! Schema::hasTable('integration_products')
            || ! Schema::hasTable('integration_sources')) {
            return;
        }

        $marketPrices = [
            'PS-000.18166' => 129.00,  // Ferroli 46360440
            'PS-009.243' => 1099.00,   // Electrolux EFP/P-3020LS
            'PS-007.915' => 80.73,     // FERRUM ППУ Ф280
        ];

        foreach ($marketPrices as $sku => $price) {
            $this->activeUnlinkedProduct($sku)->update([
                'price' => $price,
                'in_stock' => false,
                'stock_qty' => null,
                'availability_status' => 'check',
                'updated_at' => now(),
            ]);
        }
    }

    private function activeUnlinkedProduct(string $sku)
    {
        return DB::table('products')
            ->where('sku', $sku)
            ->where('is_active', true)
            ->where('is_archived', false)
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('supplier_products')
                ->whereColumn('supplier_products.product_id', 'products.id'))
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('integration_products')
                ->join('integration_sources', 'integration_sources.id', '=', 'integration_products.integration_source_id')
                ->whereColumn('integration_products.product_id', 'products.id')
                ->where('integration_products.match_status', 'matched')
                ->where('integration_sources.is_active', true));
    }

    public function down(): void
    {
        // Verified catalogue corrections are intentionally not rolled back.
    }
};

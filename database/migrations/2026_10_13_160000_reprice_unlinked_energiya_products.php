<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Market check performed on 2026-10-13 against the official Energy
     * distributor and a Belarusian retailer. Only cards without any supplier
     * channel are corrected here; linked supplier prices remain authoritative.
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
            'PS-005.684' => 632.17, // Энергия Гарант-500
            'PS-007.061' => 842.58, // Энергия Гарант-750
            'PS-007.027' => 934.21, // Энергия PRO 500 12V
        ];

        foreach ($marketPrices as $sku => $price) {
            $this->unlinkedProduct($sku)->update([
                'price' => $price,
                'in_stock' => false,
                'stock_qty' => null,
                'availability_status' => 'check',
                'updated_at' => now(),
            ]);
        }

        // ПН-750 Н is absent from the current official catalogue. Keep the
        // card available for enquiries but do not expose an unverifiable price.
        $this->unlinkedProduct('PS-005.700')->update([
            'price' => 0,
            'in_stock' => false,
            'stock_qty' => null,
            'availability_status' => 'check',
            'updated_at' => now(),
        ]);
    }

    private function unlinkedProduct(string $sku)
    {
        return DB::table('products')
            ->where('sku', $sku)
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
        // Market catalogue corrections are intentionally not rolled back.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The «Феррит 1 мм» channel stored the maker's recommended retail as the supplier cost, so the
 * admin showed «опт = розница, маржа 0%». The cost of these parts is unknown: the channel becomes
 * retail-only (no cost) and still counts as the assigned supplier.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supplier_products')) {
            return;
        }

        DB::table('supplier_products')->where('supplier_article', 'like', 'TISF1-%')->update([
            'price' => null,
            'price_byn' => null,
            'match_confidence' => 'manual_price_list',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // The official retail stays in raw.official_retail_rub.
    }
};

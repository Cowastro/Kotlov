<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CANONICAL_SKU = 'PS-007.378';
    private const LEGACY_SKU = 'KOTLOV-000344';

    public function up(): void
    {
        if (! Schema::hasTable('products')
            || ! DB::table('products')->where('sku', self::CANONICAL_SKU)->exists()) {
            return;
        }

        DB::table('products')->where('sku', self::LEGACY_SKU)->update([
            'is_active' => false,
            'is_archived' => true,
            'in_stock' => false,
            'availability_status' => Product::AVAILABILITY_OUT_OF_STOCK,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Legacy catalogue duplicates are intentionally not restored.
    }
};

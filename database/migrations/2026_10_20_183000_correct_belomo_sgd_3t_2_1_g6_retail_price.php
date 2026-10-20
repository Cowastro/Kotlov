<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SKU = 'KOTLOV-000001';

    public function up(): void
    {
        DB::table('products')
            ->where('sku', self::SKU)
            ->update([
                'price' => 245.00,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('products')
            ->where('sku', self::SKU)
            ->update([
                'price' => 203.30,
                'updated_at' => now(),
            ]);
    }
};

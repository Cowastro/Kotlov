<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Darco CZ2 — черная сталь, 2 товара ошибочно на "Нержавеющая сталь"
        $ids = DB::table('products')
            ->where('name', 'LIKE', '%Darco%')
            ->pluck('id');

        DB::table('product_attribute_values')
            ->where('attribute_id', 441)
            ->where('option_id', 817) // Нержавеющая сталь
            ->whereIn('product_id', $ids)
            ->update(['option_id' => 818, 'updated_at' => now()]); // Черная сталь
    }

    public function down(): void
    {
        $ids = DB::table('products')->where('name', 'LIKE', '%Darco%')->pluck('id');
        DB::table('product_attribute_values')
            ->where('attribute_id', 441)->where('option_id', 818)
            ->whereIn('product_id', $ids)
            ->update(['option_id' => 817]);
    }
};

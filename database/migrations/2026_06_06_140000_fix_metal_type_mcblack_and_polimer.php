<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Добавить опцию "MC Black (окрашенная нержавейка)"
        $optMcBlack = DB::table('attribute_options')->insertGetId([
            'attribute_id' => 441,
            'name'         => 'MC Black',
            'sort_order'   => 5,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // ── 2. Переместить все MC Black товары с любой нержавейки → новая опция
        $mcBlackIds = DB::table('products')
            ->where('name', 'LIKE', '%MC Black%')
            ->pluck('id');

        if ($mcBlackIds->isNotEmpty()) {
            DB::table('product_attribute_values')
                ->where('attribute_id', 441)
                ->whereIn('option_id', [817, 819, 820]) // нержавейка (без марки, 430, 304)
                ->whereIn('product_id', $mcBlackIds)
                ->update(['option_id' => $optMcBlack, 'updated_at' => now()]);
        }

        // ── 3. Крепления полимер — убрать атрибут "Тип металла" (это пластик)
        $polimerIds = DB::table('products')
            ->where('name', 'LIKE', '%полимер%')
            ->pluck('id');

        if ($polimerIds->isNotEmpty()) {
            DB::table('product_attribute_values')
                ->where('attribute_id', 441)
                ->whereIn('product_id', $polimerIds)
                ->delete();
        }
    }

    public function down(): void
    {
        $optMcBlack = DB::table('attribute_options')
            ->where('attribute_id', 441)
            ->where('name', 'MC Black')
            ->value('id');

        if ($optMcBlack) {
            // Вернуть MC Black товары на "Нержавеющая сталь" (817)
            DB::table('product_attribute_values')
                ->where('attribute_id', 441)
                ->where('option_id', $optMcBlack)
                ->update(['option_id' => 817]);

            DB::table('attribute_options')->where('id', $optMcBlack)->delete();
        }
    }
};

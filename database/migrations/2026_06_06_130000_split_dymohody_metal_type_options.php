<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Переименовать "Низколегированная сталь" → "Черная сталь"
        DB::table('attribute_options')->where('id', 818)->update([
            'name'       => 'Черная сталь',
            'updated_at' => now(),
        ]);

        // ── 2. Добавить опции: Нержавеющая сталь 430 и Нержавеющая сталь 304
        $opt430 = DB::table('attribute_options')->insertGetId([
            'attribute_id' => 441,
            'name'         => 'Нержавеющая сталь 430',
            'sort_order'   => 2,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $opt304 = DB::table('attribute_options')->insertGetId([
            'attribute_id' => 441,
            'name'         => 'Нержавеющая сталь 304',
            'sort_order'   => 3,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // Сдвинуть Черная сталь на sort_order=4 (была 2)
        DB::table('attribute_options')->where('id', 818)->update(['sort_order' => 4]);

        // ── 3. Перевязать товары с option 817 (Нержавеющая сталь) на 430/304 по имени

        // Нержавеющая 430: содержит "430" в названии
        $products430 = DB::table('products')
            ->where('name', 'LIKE', '%430%')
            ->pluck('id');

        if ($products430->isNotEmpty()) {
            DB::table('product_attribute_values')
                ->where('attribute_id', 441)
                ->where('option_id', 817)
                ->whereIn('product_id', $products430)
                ->update(['option_id' => $opt430, 'updated_at' => now()]);
        }

        // Нержавеющая 304: содержит "304" или "AISI 304" в названии
        $products304 = DB::table('products')
            ->where(function ($q) {
                $q->where('name', 'LIKE', '%304%')
                  ->orWhere('name', 'LIKE', '%AISI 304%');
            })
            ->pluck('id');

        if ($products304->isNotEmpty()) {
            DB::table('product_attribute_values')
                ->where('attribute_id', 441)
                ->where('option_id', 817)
                ->whereIn('product_id', $products304)
                ->update(['option_id' => $opt304, 'updated_at' => now()]);
        }

        // Оставшиеся с option 817 — "Нержавеющая сталь" без маркировки (Ferrum, Darco и пр.)
        // Переименовать 817 для ясности → "Нержавеющая сталь (другие марки)"
        DB::table('attribute_options')->where('id', 817)->update([
            'name'       => 'Нержавеющая сталь',
            'sort_order' => 1,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Вернуть названия опций
        DB::table('attribute_options')->where('id', 817)->update(['name' => 'Нержавеющая сталь', 'sort_order' => 1]);
        DB::table('attribute_options')->where('id', 818)->update(['name' => 'Низколегированная сталь', 'sort_order' => 2]);

        // Вернуть товары 430 и 304 обратно на option 817
        $opts = DB::table('attribute_options')
            ->where('attribute_id', 441)
            ->whereIn('name', ['Нержавеющая сталь 430', 'Нержавеющая сталь 304'])
            ->pluck('id');

        DB::table('product_attribute_values')
            ->where('attribute_id', 441)
            ->whereIn('option_id', $opts)
            ->update(['option_id' => 817]);

        DB::table('attribute_options')->whereIn('id', $opts)->delete();
    }
};

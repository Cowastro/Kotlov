<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Очистка верхнего уровня меню.
 *
 * Шаг 1. Скрыть "Категория 288" (id=288) — пустышка, 0 товаров.
 * Шаг 2. Скрыть "Монтажные комплекты систем отопления" (id=283) — 2 товара, не тянет на top-level.
 * Шаг 3. "Радиаторы отопления" (id=252) → переместить под "Отопление" (id=56), sort_order=90.
 *
 * Только UPDATE, никаких DELETE.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Шаг 1. Скрыть "Категория 288"
        DB::table('categories')
            ->where('id', 288)
            ->update(['is_active' => 0, 'updated_at' => now()]);

        // Шаг 2. Скрыть "Монтажные комплекты систем отопления"
        DB::table('categories')
            ->where('id', 283)
            ->update(['is_active' => 0, 'updated_at' => now()]);

        // Шаг 3. "Радиаторы отопления" → под "Отопление" (id=56)
        DB::table('categories')
            ->where('id', 252)
            ->update([
                'parent_id'  => 56,
                'sort_order' => 90,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Восстановить видимость
        DB::table('categories')
            ->where('id', 288)
            ->update(['is_active' => 1, 'updated_at' => now()]);

        DB::table('categories')
            ->where('id', 283)
            ->update(['is_active' => 1, 'updated_at' => now()]);

        // Вернуть Радиаторы на верхний уровень
        DB::table('categories')
            ->where('id', 252)
            ->update([
                'parent_id'  => 0,
                'sort_order' => 10,
                'updated_at' => now(),
            ]);
    }
};

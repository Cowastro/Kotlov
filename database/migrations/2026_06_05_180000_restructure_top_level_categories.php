<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Реструктуризация верхнего уровня каталога.
 *
 * Шаги:
 *   1. Создать "Бани и сауны" (top-level)
 *   2. Создать "Водоснабжение" (top-level)
 *   3. Поднять "Тепловые насосы" (286) на верхний уровень
 *   4. Поднять и переименовать "Для отопления" (56) → "Отопление"
 *   5. Переместить "Для бани" (52) под "Бани и сауны"
 *   6. Переместить "Для печей и каминов" (73) под "Бани и сауны" и переименовать
 *   7. Переместить "Насосы" (246) под "Водоснабжение"
 *   8. Выставить sort_order верхнего уровня
 *
 * Никаких DELETE/DROP — только INSERT и UPDATE.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Шаг 1. Создать категорию "Бани и сауны" ──────────────────────────
        $baniId = DB::table('categories')->insertGetId([
            'name'       => 'Бани и сауны',
            'slug'       => 'bani-i-sauny',
            'parent_id'  => 0,
            'sort_order' => 60,
            'is_active'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ── Шаг 2. Создать категорию "Водоснабжение" ─────────────────────────
        $vodoId = DB::table('categories')->insertGetId([
            'name'       => 'Водоснабжение',
            'slug'       => 'vodosnabzhenie',
            'parent_id'  => 0,
            'sort_order' => 70,
            'is_active'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ── Шаг 3. "Тепловые насосы" (id=286): parent 49 → 0 ─────────────────
        DB::table('categories')
            ->where('id', 286)
            ->update([
                'parent_id'  => 0,
                'sort_order' => 20,
                'updated_at' => now(),
            ]);

        // ── Шаг 4. "Для отопления" (id=56): parent 131 → 0, переименовать ────
        DB::table('categories')
            ->where('id', 56)
            ->update([
                'name'       => 'Отопление',
                'slug'       => 'otoplenie',
                'parent_id'  => 0,
                'sort_order' => 50,
                'updated_at' => now(),
            ]);

        // ── Шаг 5. "Для бани" (id=52): parent 113 → Бани и сауны ────────────
        DB::table('categories')
            ->where('id', 52)
            ->update([
                'name'       => 'Печи для бани',
                'parent_id'  => $baniId,
                'sort_order' => 10,
                'updated_at' => now(),
            ]);

        // ── Шаг 6. "Для печей и каминов" (id=73): parent 131 → Бани и сауны ─
        DB::table('categories')
            ->where('id', 73)
            ->update([
                'name'       => 'Аксессуары для бани',
                'parent_id'  => $baniId,
                'sort_order' => 20,
                'updated_at' => now(),
            ]);

        // ── Шаг 7. "Насосы" (id=246): parent 0 → Водоснабжение ──────────────
        DB::table('categories')
            ->where('id', 246)
            ->update([
                'parent_id'  => $vodoId,
                'sort_order' => 10,
                'updated_at' => now(),
            ]);

        // ── Шаг 8. Выставить sort_order верхнего уровня ──────────────────────
        $sortMap = [
            49  => 10,  // Котлы
            286 => 20,  // Тепловые насосы (уже обновлён выше, дублируем для надёжности)
            51  => 30,  // Камины
            113 => 40,  // Печи
            56  => 50,  // Отопление (уже обновлён выше)
            // $baniId => 60  — задан при INSERT
            // $vodoId => 70  — задан при INSERT
        ];

        foreach ($sortMap as $catId => $sortOrder) {
            DB::table('categories')
                ->where('id', $catId)
                ->update(['sort_order' => $sortOrder, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // ── Откат: восстановить исходные parent_id, имена, sort_order ─────────

        // Тепловые насосы (286) → обратно под Котлы
        DB::table('categories')
            ->where('id', 286)
            ->update(['parent_id' => 49, 'sort_order' => 10, 'updated_at' => now()]);

        // Отопление (56) → обратно "Для отопления", parent=131
        DB::table('categories')
            ->where('id', 56)
            ->update([
                'name'       => 'Для отопления',
                'slug'       => 'otoplenie-parts',
                'parent_id'  => 131,
                'sort_order' => 2,
                'updated_at' => now(),
            ]);

        // Для бани (52) → обратно под Печи (113)
        DB::table('categories')
            ->where('id', 52)
            ->update([
                'name'       => 'Для бани',
                'parent_id'  => 113,
                'sort_order' => 4,
                'updated_at' => now(),
            ]);

        // Для печей и каминов (73) → обратно parent=131
        DB::table('categories')
            ->where('id', 73)
            ->update([
                'name'       => 'Для печей и каминов',
                'parent_id'  => 131,
                'sort_order' => 6,
                'updated_at' => now(),
            ]);

        // Насосы (246) → обратно на верхний уровень
        DB::table('categories')
            ->where('id', 246)
            ->update(['parent_id' => 0, 'sort_order' => 9, 'updated_at' => now()]);

        // Удалить созданные категории (безопасно — товаров там нет)
        DB::table('categories')->where('slug', 'bani-i-sauny')->delete();
        DB::table('categories')->where('slug', 'vodosnabzhenie')->delete();
    }
};

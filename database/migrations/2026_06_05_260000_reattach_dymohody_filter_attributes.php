<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Перепривязать фильтр-атрибуты дымоходов с деактивированной cat=78
 * на top-level cat=303 (Дымоходы).
 *
 * Атрибуты уже имеют in_filter=1 и заполненные PAV-данные (89-100%).
 * Достаточно изменить category_id — фильтры появятся на всех страницах
 * раздела Дымоходы (контроллер использует collectCategoryAndDescendantIds).
 *
 *   435 — Диаметр
 *   437 — Длина трубы
 *   439 — Толщина металла
 *   441 — Тип металла (Нержавеющая / Низколегированная)
 *
 * Атрибут 434 "Тип элемента" — оставляем на cat=78 (теперь неактивна),
 * т.к. заменён отдельными категориями.
 */
return new class extends Migration
{
    public function up(): void
    {
        $dymId = DB::table('categories')->where('slug', 'dymohody')->value('id'); // 303

        DB::table('attributes')
            ->whereIn('id', [435, 437, 439, 441])
            ->update([
                'category_id' => $dymId,
                'in_filter'   => 1,
                'in_product'  => 1,
                'updated_at'  => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('attributes')
            ->whereIn('id', [435, 437, 439, 441])
            ->update([
                'category_id' => 78,
                'updated_at'  => now(),
            ]);
    }
};

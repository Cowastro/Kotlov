<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Убираем промежуточный узел "Насосы" (id=246):
    // все его дочерние категории поднимаем прямо под Водоснабжение (id=302)
    // id=246 скрываем (не удаляем, чтобы не ломать FK)

    public function up(): void
    {
        // Активные с товарами — поднять под 302 с правильным sort_order
        $map = [
            248 => 10, // Циркуляционные (150)
            249 => 20, // Поверхностные (45)
            250 => 30, // Погружные (44)
            251 => 40, // Насосные станции (62)
            198 => 50, // Скважинные (20)
        ];

        foreach ($map as $id => $sort) {
            DB::table('categories')->where('id', $id)->update([
                'parent_id'  => 302,
                'sort_order' => $sort,
                'updated_at' => now(),
            ]);
        }

        // Пустые/скрытые — тоже переносим под 302 но скрываем
        DB::table('categories')
            ->whereIn('id', [247, 262, 263, 264, 265, 266])
            ->update(['parent_id' => 302, 'is_active' => 0, 'updated_at' => now()]);

        // Узел-контейнер "Насосы" (id=246) — скрыть
        DB::table('categories')->where('id', 246)->update([
            'is_active'  => 0,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $ids = [248, 249, 250, 251, 198, 247, 262, 263, 264, 265, 266];
        DB::table('categories')->whereIn('id', $ids)->update([
            'parent_id' => 246, 'updated_at' => now(),
        ]);
        DB::table('categories')->where('id', 246)->update([
            'is_active' => 1, 'updated_at' => now(),
        ]);
    }
};

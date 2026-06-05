<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Новый порядок:
        // 10 Котлы
        // 20 Печи
        // 30 Камины
        // 40 Бани и сауны
        // 50 Дымоходы
        // 60 Отопление
        // 70 Водонагреватели
        // 80 Тепловые насосы
        // 90 Пеллетные горелки
        // 100 Насосы (бывш. Водоснабжение)

        $order = [
            49  => 10,   // Котлы
            113 => 20,   // Печи
            51  => 30,   // Камины
            301 => 40,   // Бани и сауны
            303 => 50,   // Дымоходы
            56  => 60,   // Отопление
            50  => 70,   // Водонагреватели
            286 => 80,   // Тепловые насосы
            297 => 90,   // Пеллетные горелки
            302 => 100,  // Насосы
        ];

        foreach ($order as $id => $sort) {
            DB::table('categories')->where('id', $id)->update([
                'sort_order' => $sort,
                'updated_at' => now(),
            ]);
        }

        // Переименовать Водоснабжение → Насосы
        DB::table('categories')->where('id', 302)->update([
            'name'       => 'Насосы',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $order = [49=>10, 50=>15, 286=>20, 297=>25, 51=>30, 113=>40, 303=>45, 56=>55, 301=>65, 302=>75];
        foreach ($order as $id => $sort) {
            DB::table('categories')->where('id', $id)->update(['sort_order' => $sort, 'updated_at' => now()]);
        }
        DB::table('categories')->where('id', 302)->update(['name' => 'Водоснабжение', 'updated_at' => now()]);
    }
};

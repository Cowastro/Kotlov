<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Приводит структуру категорий в соответствие с навигацией хедера.
 *
 * Проблемы которые решает:
 * 1. Категории из хедера отсутствуют в БД (dymohody, otoplenie, pelletnye-gorelki, dlya-bani, vodosnabzhenie, klimat)
 * 2. id=52 (dlya-bani) отсутствует, но её дочерние категории ссылаются на parent_id=52
 * 3. vodonagrevateli (id=50) лежит в котлах (parent=49) — должна быть корневой
 * 4. teplovyie-nasosyi (id=286) — слаг не совпадает с хедером teplovye-nasosy
 */
return new class extends Migration
{
    public function up(): void
    {
        // -------------------------------------------------------
        // 1. Восстанавливаем id=52 (Для бани) — её дочерние уже есть
        // -------------------------------------------------------
        DB::table('categories')->insertOrIgnore([
            'id'         => 52,
            'parent_id'  => 0,
            'name'       => 'Для бани',
            'slug'       => 'dlya-bani',
            'h1'         => 'Оборудование для бани и сауны',
            'sort_order' => 70,
            'is_active'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // -------------------------------------------------------
        // 2. Добавляем недостающие корневые категории
        // -------------------------------------------------------
        $newRoots = [
            [
                'id'         => 300,
                'parent_id'  => 0,
                'name'       => 'Дымоходы',
                'slug'       => 'dymohody',
                'h1'         => 'Дымоходы для котлов, печей и каминов',
                'sort_order' => 80,
                'is_active'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id'         => 301,
                'parent_id'  => 0,
                'name'       => 'Отопление',
                'slug'       => 'otoplenie',
                'h1'         => 'Оборудование для отопления',
                'sort_order' => 40,
                'is_active'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id'         => 302,
                'parent_id'  => 0,
                'name'       => 'Пеллетные горелки',
                'slug'       => 'pelletnye-gorelki',
                'h1'         => 'Пеллетные горелки и автоматика',
                'sort_order' => 20,
                'is_active'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id'         => 303,
                'parent_id'  => 0,
                'name'       => 'Водоснабжение',
                'slug'       => 'vodosnabzhenie',
                'h1'         => 'Оборудование для водоснабжения',
                'sort_order' => 90,
                'is_active'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id'         => 304,
                'parent_id'  => 0,
                'name'       => 'Климат',
                'slug'       => 'klimat',
                'h1'         => 'Климатическое оборудование',
                'sort_order' => 100,
                'is_active'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($newRoots as $cat) {
            DB::table('categories')->insertOrIgnore($cat);
        }

        // -------------------------------------------------------
        // 3. Перемещаем vodonagrevateli (id=50) из котлов → корень
        //    Было: parent_id=49 (Котлы)
        //    Стало: parent_id=0 (корневая)
        // -------------------------------------------------------
        DB::table('categories')->where('id', 50)->update([
            'parent_id'  => 0,
            'sort_order' => 30,
            'updated_at' => now(),
        ]);

        // -------------------------------------------------------
        // 4. Исправляем слаг teplovyie-nasosyi → teplovye-nasosy
        //    Перемещаем в корень (было parent=49 Котлы)
        // -------------------------------------------------------
        DB::table('categories')->where('id', 286)->update([
            'slug'       => 'teplovye-nasosy',
            'parent_id'  => 0,
            'sort_order' => 15,
            'updated_at' => now(),
        ]);

        // -------------------------------------------------------
        // 5. Обновляем sort_order корневых для правильного порядка в меню
        //    Котлы / Тепловые насосы / Пеллетные горелки / Водонагреватели /
        //    Отопление / Печи / Для бани / Камины / Дымоходы / Водоснабжение / Климат
        // -------------------------------------------------------
        $sortMap = [
            49  => 10,  // Котлы
            286 => 15,  // Тепловые насосы
            302 => 20,  // Пеллетные горелки
            50  => 30,  // Водонагреватели
            301 => 40,  // Отопление
            113 => 50,  // Печи
            52  => 60,  // Для бани
            51  => 65,  // Камины
            300 => 80,  // Дымоходы
            246 => 85,  // Насосы
            303 => 90,  // Водоснабжение
            304 => 100, // Климат
        ];

        foreach ($sortMap as $id => $sort) {
            DB::table('categories')->where('id', $id)->update([
                'sort_order' => $sort,
                'updated_at' => now(),
            ]);
        }

        // -------------------------------------------------------
        // 6. SEO редиректы — старые URL → новые
        //    teplovyie-nasosyi → teplovye-nasosy
        // -------------------------------------------------------
        DB::table('redirects')->insertOrIgnore([
            ['from_url' => '/teplovyie-nasosyi', 'to_url' => '/teplovye-nasosy', 'status_code' => 301, 'created_at' => now(), 'updated_at' => now()],
            // Старые URL водонагревателей (были в котлах)
            ['from_url' => '/kotly/vodonagrevateli', 'to_url' => '/vodonagrevateli', 'status_code' => 301, 'created_at' => now(), 'updated_at' => now()],
            ['from_url' => '/kotly/teplovyie-nasosyi', 'to_url' => '/teplovye-nasosy', 'status_code' => 301, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        // Откат: возвращаем старую структуру
        DB::table('categories')->whereIn('id', [52, 300, 301, 302, 303, 304])->delete();

        DB::table('categories')->where('id', 50)->update([
            'parent_id' => 49,
        ]);

        DB::table('categories')->where('id', 286)->update([
            'slug'      => 'teplovyie-nasosyi',
            'parent_id' => 49,
        ]);

        DB::table('redirects')
            ->whereIn('from_url', [
                '/teplovyie-nasosyi',
                '/kotly/vodonagrevateli',
                '/kotly/teplovyie-nasosyi',
            ])
            ->delete();
    }
};

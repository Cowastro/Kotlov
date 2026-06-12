<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Subcategories for Климат (id=304).
 *
 * Matches Rusklimat CSV categories:
 *   wall_mounted_air_conditioners  → 305
 *   mobile_air_conditioners        → 306
 *   cassette_air_conditioners      → 307
 *   duct_air_conditioners          → 308
 *   floor_and_ceiling_...          → 309
 *   мультисплит / multi-split      → 310
 */
return new class extends Migration
{
    public function up(): void
    {
        $subs = [
            [
                'id'         => 305,
                'parent_id'  => 304,
                'name'       => 'Сплит-системы',
                'slug'       => 'split-sistemy',
                'h1'         => 'Сплит-системы настенные',
                'sort_order' => 10,
                'is_active'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id'         => 306,
                'parent_id'  => 304,
                'name'       => 'Мобильные кондиционеры',
                'slug'       => 'mobilnye-kondicionery',
                'h1'         => 'Мобильные кондиционеры',
                'sort_order' => 20,
                'is_active'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id'         => 307,
                'parent_id'  => 304,
                'name'       => 'Кассетные кондиционеры',
                'slug'       => 'kassetnye-kondicionery',
                'h1'         => 'Кассетные кондиционеры',
                'sort_order' => 30,
                'is_active'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id'         => 308,
                'parent_id'  => 304,
                'name'       => 'Канальные кондиционеры',
                'slug'       => 'kanalnye-kondicionery',
                'h1'         => 'Канальные кондиционеры',
                'sort_order' => 40,
                'is_active'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id'         => 309,
                'parent_id'  => 304,
                'name'       => 'Напольно-потолочные кондиционеры',
                'slug'       => 'napolno-potolochnye-kondicionery',
                'h1'         => 'Напольно-потолочные кондиционеры',
                'sort_order' => 50,
                'is_active'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id'         => 310,
                'parent_id'  => 304,
                'name'       => 'Мультисплит-системы',
                'slug'       => 'multisplit-sistemy',
                'h1'         => 'Мультисплит-системы',
                'sort_order' => 60,
                'is_active'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($subs as $cat) {
            DB::table('categories')->insertOrIgnore($cat);
        }
    }

    public function down(): void
    {
        DB::table('categories')->whereIn('id', [305, 306, 307, 308, 309, 310])->delete();
    }
};

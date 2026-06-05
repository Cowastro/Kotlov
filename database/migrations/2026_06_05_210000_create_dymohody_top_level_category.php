<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Создать верхнеуровневую группу "Дымоходы".
 *
 * Шаг 1. Создать категорию "Дымоходы" (top-level, sort=45 — между Печами(40) и Отоплением(50)).
 * Шаг 2. Перенести id=78  "Дымоходы"            (parent=73 → новая) + исправить slug и name.
 * Шаг 3. Перенести id=57  "Дымоходы коаксиальные" (parent=56 → новая).
 * Шаг 4. Подключить id=230 "Дымоходы для бани"   (parent=229 — не сущ. → новая).
 * Шаг 5. Подключить id=232 "Дымоходы для каминов" (parent=229 → новая).
 * Шаг 6. Скрыть    id=237 "Дымоходы для печей"  (0 товаров, parent=229 → is_active=0).
 * Шаг 7. Сдвинуть sort_order Отопления(56) и всего что ≥45 чтобы освободить место.
 * Шаг 8. 301-редиректы для изменившихся URL.
 *
 * Только INSERT + UPDATE, никаких DELETE.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Шаг 7 сначала: раздвинуть sort_order ─────────────────────────────
        // Отопление: 50→55, Бани: 60→65, Водоснабжение: 70→75
        DB::table('categories')->where('id', 56)->update(['sort_order' => 55, 'updated_at' => now()]);
        DB::table('categories')->where('slug', 'bani-i-sauny')->update(['sort_order' => 65, 'updated_at' => now()]);
        DB::table('categories')->where('slug', 'vodosnabzhenie')->update(['sort_order' => 75, 'updated_at' => now()]);

        // ── Шаг 1. Создать "Дымоходы" top-level ──────────────────────────────
        $dymId = DB::table('categories')->insertGetId([
            'name'       => 'Дымоходы',
            'slug'       => 'dymohody',
            'parent_id'  => 0,
            'sort_order' => 45,
            'is_active'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ── Шаг 2. id=78: перенести + исправить slug и убрать пробел из name ─
        DB::table('categories')
            ->where('id', 78)
            ->update([
                'name'       => 'Дымоходы',
                'slug'       => 'dymohody-nerzhaveyushchie',
                'parent_id'  => $dymId,
                'sort_order' => 10,
                'updated_at' => now(),
            ]);

        // ── Шаг 3. id=57: перенести из Отопления в Дымоходы ─────────────────
        DB::table('categories')
            ->where('id', 57)
            ->update([
                'parent_id'  => $dymId,
                'sort_order' => 20,
                'updated_at' => now(),
            ]);

        // ── Шаг 4. id=230: подключить к живому parent ────────────────────────
        DB::table('categories')
            ->where('id', 230)
            ->update([
                'parent_id'  => $dymId,
                'sort_order' => 30,
                'is_active'  => 1,
                'updated_at' => now(),
            ]);

        // ── Шаг 5. id=232: подключить к живому parent ────────────────────────
        DB::table('categories')
            ->where('id', 232)
            ->update([
                'parent_id'  => $dymId,
                'sort_order' => 40,
                'updated_at' => now(),
            ]);

        // ── Шаг 6. id=237: скрыть (0 товаров) ───────────────────────────────
        DB::table('categories')
            ->where('id', 237)
            ->update([
                'parent_id'  => $dymId,
                'is_active'  => 0,
                'updated_at' => now(),
            ]);

        // ── Шаг 8. 301-редиректы ─────────────────────────────────────────────
        $redirects = [
            // id=78 slug изменился
            [
                'from_url'    => '/dymoxody_dlia_bani',
                'to_url'      => '/dymohody-nerzhaveyushchie',
                'status_code' => 301,
                'is_active'   => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            // id=57 URL изменился (был в /otoplenie/..., теперь /dymohody/...)
            [
                'from_url'    => '/koaxial-dymoxod',
                'to_url'      => '/dymohody-nerzhaveyushchie',
                'status_code' => 301,
                'is_active'   => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ];

        foreach ($redirects as $redirect) {
            DB::table('redirects')->insertOrIgnore($redirect);
        }
    }

    public function down(): void
    {
        // Вернуть sort_order
        DB::table('categories')->where('id', 56)->update(['sort_order' => 50, 'updated_at' => now()]);
        DB::table('categories')->where('slug', 'bani-i-sauny')->update(['sort_order' => 60, 'updated_at' => now()]);
        DB::table('categories')->where('slug', 'vodosnabzhenie')->update(['sort_order' => 70, 'updated_at' => now()]);

        // Вернуть id=78 в Аксессуары для бани
        DB::table('categories')->where('id', 78)->update([
            'name' => 'Дымоходы ', 'slug' => 'dymoxody_dlia_bani',
            'parent_id' => 73, 'sort_order' => 0, 'updated_at' => now(),
        ]);

        // Вернуть id=57 в Отопление
        DB::table('categories')->where('id', 57)->update([
            'parent_id' => 56, 'sort_order' => 2, 'updated_at' => now(),
        ]);

        // Вернуть осиротевшие к несуществующему parent (как было)
        DB::table('categories')->whereIn('id', [230, 232, 237])->update([
            'parent_id' => 229, 'updated_at' => now(),
        ]);
        DB::table('categories')->where('id', 230)->update(['is_active' => 0]);
        DB::table('categories')->where('id', 237)->update(['is_active' => 1]);

        // Удалить редиректы и созданную категорию
        DB::table('redirects')->whereIn('from_url', ['/dymoxody_dlia_bani', '/koaxial-dymoxod'])->delete();
        DB::table('categories')->where('slug', 'dymohody')->delete();
    }
};

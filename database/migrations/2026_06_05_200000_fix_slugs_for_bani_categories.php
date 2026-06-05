<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Исправить устаревшие слаги после перемещения категорий в "Бани и сауны".
 *
 * Шаг 1. id=52  "Печи для бани":     dlya-bani        → pechi-dlya-bani
 * Шаг 2. id=73  "Аксессуары для бани": pechi-kaminy-parts → aksessuary-dlya-bani
 * Шаг 3. Добавить 301-редиректы со старых URL на новые.
 *
 * Только UPDATE + INSERT, никаких DELETE.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Шаг 1. Обновить slug id=52 ───────────────────────────────────────
        DB::table('categories')
            ->where('id', 52)
            ->update([
                'slug'       => 'pechi-dlya-bani',
                'updated_at' => now(),
            ]);

        // ── Шаг 2. Обновить slug id=73 ───────────────────────────────────────
        DB::table('categories')
            ->where('id', 73)
            ->update([
                'slug'       => 'aksessuary-dlya-bani',
                'updated_at' => now(),
            ]);

        // ── Шаг 3. Добавить 301-редиректы ────────────────────────────────────
        $redirects = [
            // Категория
            [
                'from_url'    => '/dlya-bani',
                'to_url'      => '/pechi-dlya-bani',
                'status_code' => 301,
                'is_active'   => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            // Категория
            [
                'from_url'    => '/pechi-kaminy-parts',
                'to_url'      => '/aksessuary-dlya-bani',
                'status_code' => 301,
                'is_active'   => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ];

        foreach ($redirects as $redirect) {
            // insertOrIgnore чтобы не упасть если уже существует
            DB::table('redirects')->insertOrIgnore($redirect);
        }
    }

    public function down(): void
    {
        // Вернуть старые слаги
        DB::table('categories')
            ->where('id', 52)
            ->update(['slug' => 'dlya-bani', 'updated_at' => now()]);

        DB::table('categories')
            ->where('id', 73)
            ->update(['slug' => 'pechi-kaminy-parts', 'updated_at' => now()]);

        // Удалить добавленные редиректы
        DB::table('redirects')
            ->whereIn('from_url', ['/dlya-bani', '/pechi-kaminy-parts'])
            ->delete();
    }
};

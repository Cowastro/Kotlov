<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Финальный проход: распределить оставшиеся 49 товаров из "Прочие комплектующие".
 *
 * Колена и отводы моно  — Отвод ТиС AISI 304, Отвод LAVA
 * Трубы одностенные     — Труба LAVA чёрный, Труба ТиС MC Black, Труба RP Darco
 * Теплосъёмники         — Каменка, Конвектор, Теплообменник, ППШ, Баки, Титан, Сетка
 * Переходы и адаптеры   — Соединение под керамику Darco, УПШ ТермоВент
 * Крепления и монтаж    — Листы потолочные, Лента примыкания
 * Заглушки и оголовки   — Оголовок ТермоВент
 * Прочие (5 шт)         — Обрезь базальта, Комплект вентиляции, Сэндвич-сетка
 */
return new class extends Migration
{
    public function up(): void
    {
        $pid      = DB::table('categories')->where('slug', 'prochie-dymohod')->value('id');
        $monoKol  = DB::table('categories')->where('slug', 'kolena-mono')->value('id');
        $monoTrub = DB::table('categories')->where('slug', 'truby-mono')->value('id');
        $teplo    = DB::table('categories')->where('slug', 'teplosyomniki')->value('id');
        $perehod  = DB::table('categories')->where('slug', 'perehody-adaptery-dymohod')->value('id');
        $krep     = DB::table('categories')->where('slug', 'krepleniya-dymohod')->value('id');
        $zagl     = DB::table('categories')->where('slug', 'zaglushki-dymohod')->value('id');

        // ── Колена и отводы моно ──────────────────────────────────────────────
        // Отвод ТиС AISI 304, Отвод LAVA
        DB::table('products')
            ->where('category_id', $pid)
            ->where(fn($q) => $q
                ->where('name', 'like', '%Отвод%')
                ->orWhere('name', 'like', '%отвод%')
            )
            ->update(['category_id' => $monoKol, 'updated_at' => now()]);

        // ── Трубы одностенные ─────────────────────────────────────────────────
        // Труба LAVA чёрный 2мм, Труба ТиС MC Black, Труба RP Darco
        DB::table('products')
            ->where('category_id', $pid)
            ->where(fn($q) => $q
                ->where('name', 'like', 'Труба LAVA%')
                ->orWhere('name', 'like', 'Труба Теплов%')
                ->orWhere('name', 'like', 'Труба RP%')
            )
            ->update(['category_id' => $monoTrub, 'updated_at' => now()]);

        // ── Теплосъёмники ─────────────────────────────────────────────────────
        DB::table('products')
            ->where('category_id', $pid)
            ->where(fn($q) => $q
                ->where('name', 'like', '%Каменка%')
                ->orWhere('name', 'like', '%каменка%')
                ->orWhere('name', 'like', '%Конвектор%')
                ->orWhere('name', 'like', '%конвектор%')
                ->orWhere('name', 'like', '%Теплообменник%')
                ->orWhere('name', 'like', '%ППШ%')
                ->orWhere('name', 'like', '%Пароперегреват%')
                ->orWhere('name', 'like', '%Бак-ватерпасс%')
                ->orWhere('name', 'like', '%Бак печной%')
                ->orWhere('name', 'like', '%Титан 8%')
                ->orWhere('name', 'like', '%Дымоход-конвектор%')
                ->orWhere('name', 'like', '%Сетка для камней%')
            )
            ->update(['category_id' => $teplo, 'updated_at' => now()]);

        // ── Переходы и адаптеры ───────────────────────────────────────────────
        // Соединение под керамику Darco, УПШ ТермоВент
        DB::table('products')
            ->where('category_id', $pid)
            ->where(fn($q) => $q
                ->where('name', 'like', '%Соединение под керамич%')
                ->orWhere('name', 'like', '%УПШ%')
            )
            ->update(['category_id' => $perehod, 'updated_at' => now()]);

        // ── Крепления и монтаж ────────────────────────────────────────────────
        // Листы потолочные, Лента примыкания
        DB::table('products')
            ->where('category_id', $pid)
            ->where(fn($q) => $q
                ->where('name', 'like', '%Лист потолочный%')
                ->orWhere('name', 'like', '%Лента для примыкания%')
            )
            ->update(['category_id' => $krep, 'updated_at' => now()]);

        // ── Заглушки и оголовки ───────────────────────────────────────────────
        DB::table('products')
            ->where('category_id', $pid)
            ->where('name', 'like', '%Оголовок%')
            ->update(['category_id' => $zagl, 'updated_at' => now()]);
    }

    public function down(): void
    {
        $pid     = DB::table('categories')->where('slug', 'prochie-dymohod')->value('id');
        $catIds  = DB::table('categories')
            ->whereIn('slug', ['kolena-mono','truby-mono','teplosyomniki',
                               'perehody-adaptery-dymohod','krepleniya-dymohod','zaglushki-dymohod'])
            ->pluck('id');

        // Вернуть конкретные товары обратно в Прочие по названиям
        DB::table('products')
            ->whereIn('category_id', $catIds)
            ->where(fn($q) => $q
                ->where('name', 'like', '%Отвод%')
                ->orWhere('name', 'like', 'Труба LAVA%')
                ->orWhere('name', 'like', 'Труба Теплов%')
                ->orWhere('name', 'like', 'Труба RP%')
                ->orWhere('name', 'like', '%Каменка%')
                ->orWhere('name', 'like', '%Конвектор%')
                ->orWhere('name', 'like', '%Теплообменник%')
                ->orWhere('name', 'like', '%ППШ%')
                ->orWhere('name', 'like', '%Бак-ватерпасс%')
                ->orWhere('name', 'like', '%Бак печной%')
                ->orWhere('name', 'like', '%Титан 8%')
                ->orWhere('name', 'like', '%Дымоход-конвектор%')
                ->orWhere('name', 'like', '%Сетка для камней%')
                ->orWhere('name', 'like', '%Соединение под керамич%')
                ->orWhere('name', 'like', '%УПШ%')
                ->orWhere('name', 'like', '%Лист потолочный%')
                ->orWhere('name', 'like', '%Лента для примыкания%')
                ->orWhere('name', 'like', '%Оголовок%')
            )
            ->update(['category_id' => $pid, 'updated_at' => now()]);
    }
};

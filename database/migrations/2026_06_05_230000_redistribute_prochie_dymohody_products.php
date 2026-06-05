<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Второй проход: перераспределить 334 товара из "Прочие комплектующие"
 * в правильные подкатегории дымоходов.
 *
 * Причина остатка: первая миграция искала тип ("Труба моно"), но не улавливала
 * товары с именами в формате "Тип Бренд характеристика" (Труба Теплов моно,
 * Сэндвич FERRUM, Колено поворотное и т.д.).
 */
return new class extends Migration
{
    public function up(): void
    {
        $prochieId = DB::table('categories')->where('slug', 'prochie-dymohod')->value('id');

        $swTrubyId  = DB::table('categories')->where('slug', 'truby-sendvich')->value('id');
        $swTroyId   = DB::table('categories')->where('slug', 'troyniki-sendvich')->value('id');
        $swKolId    = DB::table('categories')->where('slug', 'kolena-sendvich')->value('id');
        $monoTrubyId = DB::table('categories')->where('slug', 'truby-mono')->value('id');
        $monoTroyId  = DB::table('categories')->where('slug', 'troyniki-mono')->value('id');
        $monoKolId   = DB::table('categories')->where('slug', 'kolena-mono')->value('id');
        $perehodId   = DB::table('categories')->where('slug', 'perehody-adaptery-dymohod')->value('id');
        $krepId      = DB::table('categories')->where('slug', 'krepleniya-dymohod')->value('id');
        $kondId      = DB::table('categories')->where('slug', 'kondensatootvody')->value('id');
        $zaglId      = DB::table('categories')->where('slug', 'zaglushki-dymohod')->value('id');

        // ── СЭНДВИЧ: Трубы ────────────────────────────────────────────────────
        // "Сэндвич FERRUM 1м", "Двухстенная вставка", "Труба Термо ...", "Труба Darco Termo"
        DB::table('products')
            ->where('category_id', $prochieId)
            ->where(fn($q) => $q
                ->where('name', 'like', 'Сэндвич FERRUM%')
                ->orWhere('name', 'like', '%Двухстен%')
                ->orWhere(fn($q2) => $q2
                    ->where('name', 'like', '%Труб%')
                    ->where(fn($q3) => $q3
                        ->where('name', 'like', '%Термо%')
                        ->orWhere('name', 'like', '%термо%')
                        ->orWhere('name', 'like', '%двуст%')
                        ->orWhere('name', 'like', '%2-х%')
                    )
                )
            )
            ->update(['category_id' => $swTrubyId, 'updated_at' => now()]);

        // ── СЭНДВИЧ: Тройники ─────────────────────────────────────────────────
        DB::table('products')
            ->where('category_id', $prochieId)
            ->where(fn($q) => $q
                ->where('name', 'like', '%Тройник%')
                ->orWhere('name', 'like', '%тройник%')
            )
            ->where(fn($q) => $q
                ->where('name', 'like', '%сэндвич%')
                ->orWhere('name', 'like', '%Сэндвич%')
                ->orWhere('name', 'like', '%термо%')
                ->orWhere('name', 'like', '%Термо%')
                ->orWhere('name', 'like', '%двуст%')
            )
            ->update(['category_id' => $swTroyId, 'updated_at' => now()]);

        // ── СЭНДВИЧ: Колена ───────────────────────────────────────────────────
        DB::table('products')
            ->where('category_id', $prochieId)
            ->where(fn($q) => $q
                ->where('name', 'like', '%Колен%')
                ->orWhere('name', 'like', '%колен%')
                ->orWhere('name', 'like', '%Отвод%')
            )
            ->where(fn($q) => $q
                ->where('name', 'like', '%сэндвич%')
                ->orWhere('name', 'like', '%Сэндвич%')
                ->orWhere('name', 'like', '%Термо%')
                ->orWhere('name', 'like', '%термо%')
                ->orWhere('name', 'like', '%двуст%')
            )
            ->update(['category_id' => $swKolId, 'updated_at' => now()]);

        // ── МОНО: Трубы ───────────────────────────────────────────────────────
        // "Труба Теплов и Сухов моно", "Труба Darco", "Одностенная вставка"
        DB::table('products')
            ->where('category_id', $prochieId)
            ->where(fn($q) => $q
                ->where('name', 'like', '%Одностен%')
                ->orWhere('name', 'like', '%одностен%')
                ->orWhere(fn($q2) => $q2
                    ->where('name', 'like', '%Труб%')
                    ->where(fn($q3) => $q3
                        ->where('name', 'like', '%моно%')
                        ->orWhere('name', 'like', '%Моно%')
                        ->orWhere('name', 'like', '%Darco%')
                        ->orWhere('name', 'like', '%Parkanex%')
                    )
                )
            )
            ->update(['category_id' => $monoTrubyId, 'updated_at' => now()]);

        // ── МОНО: Тройники ────────────────────────────────────────────────────
        // "Тройник Теплов и Сухов моно", "Тройник-Д 90", "Тройник-К 135",
        // "Тройник двуходовой", "Тройник Y", "Тройник Darco"
        DB::table('products')
            ->where('category_id', $prochieId)
            ->where(fn($q) => $q
                ->where('name', 'like', '%Тройник%')
                ->orWhere('name', 'like', '%тройник%')
            )
            ->update(['category_id' => $monoTroyId, 'updated_at' => now()]);

        // ── МОНО: Колена и отводы ─────────────────────────────────────────────
        // "Колено поворотное", "Колено угол", "Колено Darco", "Колено фиксированное",
        // "Отвод Теплов и Сухов моно"
        DB::table('products')
            ->where('category_id', $prochieId)
            ->where(fn($q) => $q
                ->where('name', 'like', '%Колен%')
                ->orWhere('name', 'like', '%колен%')
                ->orWhere(fn($q2) => $q2
                    ->where('name', 'like', '%Отвод%')
                    ->where('name', 'like', '%моно%')
                )
            )
            ->update(['category_id' => $monoKolId, 'updated_at' => now()]);

        // ── Переходы и адаптеры ───────────────────────────────────────────────
        // "Старт-сэндвич", "Редукция Darco", "Соединитель Darco"
        DB::table('products')
            ->where('category_id', $prochieId)
            ->where(fn($q) => $q
                ->where('name', 'like', '%Старт-сэндвич%')
                ->orWhere('name', 'like', '%Редукци%')
                ->orWhere('name', 'like', '%редукци%')
                ->orWhere('name', 'like', '%Соединит%')
                ->orWhere('name', 'like', '%соединит%')
                ->orWhere('name', 'like', '%Переход%')
                ->orWhere('name', 'like', '%переход%')
            )
            ->update(['category_id' => $perehodId, 'updated_at' => now()]);

        // ── Крепления и монтаж ────────────────────────────────────────────────
        // "Розета Darco", "Штанга регулируемая", "Радиатор Darco" (декоративный экран)
        DB::table('products')
            ->where('category_id', $prochieId)
            ->where(fn($q) => $q
                ->where('name', 'like', '%Розет%')
                ->orWhere('name', 'like', '%Штанга%')
                ->orWhere('name', 'like', '%Радиатор Darco%') // декоративный экран-радиатор
            )
            ->update(['category_id' => $krepId, 'updated_at' => now()]);

        // ── Заглушки ──────────────────────────────────────────────────────────
        DB::table('products')
            ->where('category_id', $prochieId)
            ->where(fn($q) => $q
                ->where('name', 'like', '%Заглушк%')
                ->orWhere('name', 'like', '%заглушк%')
            )
            ->update(['category_id' => $zaglId, 'updated_at' => now()]);
    }

    public function down(): void
    {
        $prochieId = DB::table('categories')->where('slug', 'prochie-dymohod')->value('id');

        $cats = ['truby-sendvich','troyniki-sendvich','kolena-sendvich',
                 'truby-mono','troyniki-mono','kolena-mono',
                 'perehody-adaptery-dymohod','krepleniya-dymohod',
                 'kondensatootvody','zaglushki-dymohod'];

        $catIds = DB::table('categories')->whereIn('slug', $cats)->pluck('id');

        // Вернуть все товары обратно в Прочие (приблизительный откат)
        DB::table('products')
            ->whereIn('category_id', $catIds)
            ->update(['category_id' => $prochieId, 'updated_at' => now()]);
    }
};

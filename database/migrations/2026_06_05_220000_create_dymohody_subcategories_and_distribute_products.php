<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Создать детальную структуру подкатегорий "Дымоходы" и распределить товары.
 *
 * Структура:
 *   Дымоходы (id=303)
 *   ├── Система Моно (нержавейка одностенная)
 *   │   ├── Трубы одностенные
 *   │   ├── Тройники моно
 *   │   └── Колена и отводы моно
 *   ├── Система Сэндвич (двустенные утеплённые)
 *   │   ├── Трубы сэндвич
 *   │   ├── Тройники сэндвич
 *   │   └── Колена и отводы сэндвич
 *   ├── Шиберы и задвижки
 *   ├── Конденсатоотводы и ревизии
 *   ├── Крепления и монтаж  (хомуты, ППУ, кровельные проходки, фартуки)
 *   ├── Зонты и дефлекторы
 *   ├── Теплосъёмники
 *   ├── Переходы и адаптеры
 *   ├── Заглушки и оголовки
 *   ├── Прочие комплектующие  (catch-all)
 *   └── Коаксиальные дымоходы (id=57, уже на месте)
 */
return new class extends Migration
{
    private int $dymId = 303; // top-level "Дымоходы"

    public function up(): void
    {
        // ══════════════════════════════════════════════════════════════════════
        // 1. СОЗДАТЬ СИСТЕМУ МОНО
        // ══════════════════════════════════════════════════════════════════════
        $monoId = DB::table('categories')->insertGetId([
            'name' => 'Система Моно', 'slug' => 'dymohody-mono',
            'parent_id' => $this->dymId, 'sort_order' => 10, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $monoTrubyId = DB::table('categories')->insertGetId([
            'name' => 'Трубы одностенные', 'slug' => 'truby-mono',
            'parent_id' => $monoId, 'sort_order' => 10, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $monoTroyId = DB::table('categories')->insertGetId([
            'name' => 'Тройники моно', 'slug' => 'troyniki-mono',
            'parent_id' => $monoId, 'sort_order' => 20, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $monoKolId = DB::table('categories')->insertGetId([
            'name' => 'Колена и отводы моно', 'slug' => 'kolena-mono',
            'parent_id' => $monoId, 'sort_order' => 30, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // ══════════════════════════════════════════════════════════════════════
        // 2. СОЗДАТЬ СИСТЕМУ СЭНДВИЧ
        // ══════════════════════════════════════════════════════════════════════
        $swId = DB::table('categories')->insertGetId([
            'name' => 'Система Сэндвич', 'slug' => 'dymohody-sendvich',
            'parent_id' => $this->dymId, 'sort_order' => 20, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $swTrubyId = DB::table('categories')->insertGetId([
            'name' => 'Трубы сэндвич', 'slug' => 'truby-sendvich',
            'parent_id' => $swId, 'sort_order' => 10, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $swTroyId = DB::table('categories')->insertGetId([
            'name' => 'Тройники сэндвич', 'slug' => 'troyniki-sendvich',
            'parent_id' => $swId, 'sort_order' => 20, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $swKolId = DB::table('categories')->insertGetId([
            'name' => 'Колена и отводы сэндвич', 'slug' => 'kolena-sendvich',
            'parent_id' => $swId, 'sort_order' => 30, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // ══════════════════════════════════════════════════════════════════════
        // 3. СОЗДАТЬ ОТДЕЛЬНЫЕ ПОДКАТЕГОРИИ
        // ══════════════════════════════════════════════════════════════════════
        $shibId = DB::table('categories')->insertGetId([
            'name' => 'Шиберы и задвижки', 'slug' => 'shibery-dymohod',
            'parent_id' => $this->dymId, 'sort_order' => 30, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $kondId = DB::table('categories')->insertGetId([
            'name' => 'Конденсатоотводы и ревизии', 'slug' => 'kondensatootvody',
            'parent_id' => $this->dymId, 'sort_order' => 40, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $krepId = DB::table('categories')->insertGetId([
            'name' => 'Крепления и монтаж', 'slug' => 'krepleniya-dymohod',
            'parent_id' => $this->dymId, 'sort_order' => 50, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $zontId = DB::table('categories')->insertGetId([
            'name' => 'Зонты и дефлекторы', 'slug' => 'zonty-deflektory',
            'parent_id' => $this->dymId, 'sort_order' => 60, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $teploId = DB::table('categories')->insertGetId([
            'name' => 'Теплосъёмники', 'slug' => 'teplosyomniki',
            'parent_id' => $this->dymId, 'sort_order' => 70, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $perehodId = DB::table('categories')->insertGetId([
            'name' => 'Переходы и адаптеры', 'slug' => 'perehody-adaptery-dymohod',
            'parent_id' => $this->dymId, 'sort_order' => 80, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $zaglId = DB::table('categories')->insertGetId([
            'name' => 'Заглушки и оголовки', 'slug' => 'zaglushki-dymohod',
            'parent_id' => $this->dymId, 'sort_order' => 90, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $prochieId = DB::table('categories')->insertGetId([
            'name' => 'Прочие комплектующие', 'slug' => 'prochie-dymohod',
            'parent_id' => $this->dymId, 'sort_order' => 100, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // ══════════════════════════════════════════════════════════════════════
        // 4. РАСПРЕДЕЛИТЬ ТОВАРЫ ИЗ id=78
        //    ВАЖНО: порядок имеет значение — от специфичного к общему.
        //    Уже перемещённые товары не затрагиваются (category_id меняется).
        // ══════════════════════════════════════════════════════════════════════

        // ── СЭНДВИЧ: Трубы ───────────────────────────────────────────────────
        DB::table('products')
            ->where('category_id', 78)
            ->where(fn($q) => $q
                ->where('name', 'like', '%утепл%')
                ->orWhere('name', 'like', '%ндвич%') // сэндвич/Сэндвич
            )
            ->where(fn($q) => $q
                ->where('name', 'like', '%Труб%')
                ->orWhere('name', 'like', '%труб%')
            )
            ->update(['category_id' => $swTrubyId, 'updated_at' => now()]);

        // ── СЭНДВИЧ: Тройники ─────────────────────────────────────────────────
        DB::table('products')
            ->where('category_id', 78)
            ->where(fn($q) => $q
                ->where('name', 'like', '%Тройник%')
                ->orWhere('name', 'like', '%тройник%')
            )
            ->where(fn($q) => $q
                ->where('name', 'like', '%утепл%')
                ->orWhere('name', 'like', '%ндвич%')
            )
            ->update(['category_id' => $swTroyId, 'updated_at' => now()]);

        // ── СЭНДВИЧ: Колена и отводы ──────────────────────────────────────────
        DB::table('products')
            ->where('category_id', 78)
            ->where(fn($q) => $q
                ->where('name', 'like', '%ндвич-колено%') // Сэндвич-колено
                ->orWhere('name', 'like', '%ндвич-Колено%')
                ->orWhere(fn($q2) => $q2
                    ->where(fn($q3) => $q3
                        ->where('name', 'like', '%колено%')
                        ->orWhere('name', 'like', '%Колено%')
                        ->orWhere('name', 'like', '%Отвод%')
                        ->orWhere('name', 'like', '%отвод%')
                    )
                    ->where(fn($q3) => $q3
                        ->where('name', 'like', '%утепл%')
                        ->orWhere('name', 'like', '%ндвич%')
                    )
                )
            )
            ->update(['category_id' => $swKolId, 'updated_at' => now()]);

        // ── МОНО: Трубы (Труба моно + Дымоход FERRUM одностенный) ────────────
        DB::table('products')
            ->where('category_id', 78)
            ->where(fn($q) => $q
                ->where('name', 'like', '%Труба моно%')
                ->orWhere('name', 'like', '%труба моно%')
                ->orWhere('name', 'like', '%Труба овал%')
                ->orWhere(fn($q2) => $q2
                    ->where('name', 'like', '%Дымоход%')
                    ->where('name', 'like', '%FERRUM%')
                )
            )
            ->update(['category_id' => $monoTrubyId, 'updated_at' => now()]);

        // ── МОНО: Тройники ────────────────────────────────────────────────────
        DB::table('products')
            ->where('category_id', 78)
            ->where(fn($q) => $q
                ->where('name', 'like', '%Тройник моно%')
                ->orWhere('name', 'like', '%тройник моно%')
                ->orWhere(fn($q2) => $q2
                    ->where('name', 'like', '%Тройник%')
                    ->where('name', 'like', '%нерж%')
                )
            )
            ->update(['category_id' => $monoTroyId, 'updated_at' => now()]);

        // ── МОНО: Колена и отводы ─────────────────────────────────────────────
        DB::table('products')
            ->where('category_id', 78)
            ->where(fn($q) => $q
                ->where('name', 'like', '%Отвод моно%')
                ->orWhere('name', 'like', '%отвод моно%')
                ->orWhere('name', 'like', '%колено моно%')
                ->orWhere('name', 'like', '%Колено моно%')
                ->orWhere(fn($q2) => $q2
                    ->where(fn($q3) => $q3
                        ->where('name', 'like', '%Отвод%')
                        ->orWhere('name', 'like', '%отвод%')
                        ->orWhere('name', 'like', '%Колено%')
                        ->orWhere('name', 'like', '%колено%')
                    )
                    ->where('name', 'like', '%нерж%')
                )
                ->orWhere(fn($q2) => $q2 // Отвод ТиС (Теплов и Сухов) одностенный
                    ->where('name', 'like', '%Отвод%')
                    ->where('name', 'like', '%430%')
                )
            )
            ->update(['category_id' => $monoKolId, 'updated_at' => now()]);

        // ── Шиберы и задвижки ─────────────────────────────────────────────────
        DB::table('products')
            ->where('category_id', 78)
            ->where(fn($q) => $q
                ->where('name', 'like', '%шибер%')
                ->orWhere('name', 'like', '%Шибер%')
                ->orWhere('name', 'like', '%задвиж%')
                ->orWhere('name', 'like', '%заслонк%')
            )
            ->update(['category_id' => $shibId, 'updated_at' => now()]);

        // ── Конденсатоотводы и ревизии ────────────────────────────────────────
        DB::table('products')
            ->where('category_id', 78)
            ->where(fn($q) => $q
                ->where('name', 'like', '%конденсат%')
                ->orWhere('name', 'like', '%Конденсат%')
                ->orWhere('name', 'like', '%ревизи%')
                ->orWhere('name', 'like', '%Ревизи%')
            )
            ->update(['category_id' => $kondId, 'updated_at' => now()]);

        // ── Крепления и монтаж ────────────────────────────────────────────────
        DB::table('products')
            ->where('category_id', 78)
            ->where(fn($q) => $q
                ->where('name', 'like', '%хомут%')
                ->orWhere('name', 'like', '%Хомут%')
                ->orWhere('name', 'like', '%кронштейн%')
                ->orWhere('name', 'like', '%Кронштейн%')
                ->orWhere('name', 'like', '%Крепление%')
                ->orWhere('name', 'like', '%крепление%')
                ->orWhere('name', 'like', '%Площадка монтажная%')
                ->orWhere('name', 'like', '%ППУ%')
                ->orWhere('name', 'like', '%ппу%')
                ->orWhere('name', 'like', '%разделк%')
                ->orWhere('name', 'like', '%Разделк%')
                ->orWhere('name', 'like', '%Потолочно%')
                ->orWhere('name', 'like', '%потолочно%')
                ->orWhere('name', 'like', '%кровель%')
                ->orWhere('name', 'like', '%Кровель%')
                ->orWhere('name', 'like', '%проходк%')
                ->orWhere('name', 'like', '%Проходк%')
                ->orWhere('name', 'like', '%Мастер-флеш%')
                ->orWhere('name', 'like', '%мастер-флеш%')
                ->orWhere('name', 'like', '%Фартук%')
                ->orWhere('name', 'like', '%фартук%')
                ->orWhere('name', 'like', '%Стеновое%')
                ->orWhere('name', 'like', '%Консоль%')
                ->orWhere('name', 'like', '%консоль%')
            )
            ->update(['category_id' => $krepId, 'updated_at' => now()]);

        // ── Зонты и дефлекторы ────────────────────────────────────────────────
        DB::table('products')
            ->where('category_id', 78)
            ->where(fn($q) => $q
                ->where('name', 'like', '%Зонт%')
                ->orWhere('name', 'like', '%зонт%')
                ->orWhere('name', 'like', '%Дефлектор%')
                ->orWhere('name', 'like', '%дефлектор%')
                ->orWhere('name', 'like', '%Насадк%')
                ->orWhere('name', 'like', '%насадк%')
            )
            ->update(['category_id' => $zontId, 'updated_at' => now()]);

        // ── Теплосъёмники ─────────────────────────────────────────────────────
        DB::table('products')
            ->where('category_id', 78)
            ->where(fn($q) => $q
                ->where('name', 'like', '%теплос%')
                ->orWhere('name', 'like', '%Теплос%')
                ->orWhere('name', 'like', '%съемник%')
                ->orWhere('name', 'like', '%Съемник%')
                ->orWhere('name', 'like', '%Отопитель натрубн%')
            )
            ->update(['category_id' => $teploId, 'updated_at' => now()]);

        // ── Переходы и адаптеры ───────────────────────────────────────────────
        DB::table('products')
            ->where('category_id', 78)
            ->where(fn($q) => $q
                ->where('name', 'like', '%переход%')
                ->orWhere('name', 'like', '%Переход%')
                ->orWhere('name', 'like', '%адаптер%')
                ->orWhere('name', 'like', '%Адаптер%')
                ->orWhere('name', 'like', '%Конус%')
                ->orWhere('name', 'like', '%конус%')
                ->orWhere('name', 'like', '%Юбка%')
                ->orWhere('name', 'like', '%юбка%')
            )
            ->update(['category_id' => $perehodId, 'updated_at' => now()]);

        // ── Заглушки и оголовки ───────────────────────────────────────────────
        DB::table('products')
            ->where('category_id', 78)
            ->where(fn($q) => $q
                ->where('name', 'like', '%заглушк%')
                ->orWhere('name', 'like', '%Заглушк%')
                ->orWhere('name', 'like', '%оголовк%')
                ->orWhere('name', 'like', '%Оголовк%')
                ->orWhere('name', 'like', '%крышк%')
                ->orWhere('name', 'like', '%Крышк%')
            )
            ->update(['category_id' => $zaglId, 'updated_at' => now()]);

        // ── Остаток → Прочие комплектующие ───────────────────────────────────
        DB::table('products')
            ->where('category_id', 78)
            ->update(['category_id' => $prochieId, 'updated_at' => now()]);

        // ── Деактивировать пустой id=78 ──────────────────────────────────────
        DB::table('categories')
            ->where('id', 78)
            ->update(['is_active' => 0, 'updated_at' => now()]);

        // ══════════════════════════════════════════════════════════════════════
        // 5. ПЕРЕРАСПРЕДЕЛИТЬ ТОВАРЫ ИЗ id=230 и id=232
        // ══════════════════════════════════════════════════════════════════════

        // id=230: консоли FERRUM → Крепления, конденсатоотвод → Конденсатоотводы
        DB::table('products')
            ->where('category_id', 230)
            ->where(fn($q) => $q
                ->where('name', 'like', '%Консоль%')
                ->orWhere('name', 'like', '%консоль%')
            )
            ->update(['category_id' => $krepId, 'updated_at' => now()]);

        DB::table('products')
            ->where('category_id', 230)
            ->where(fn($q) => $q
                ->where('name', 'like', '%конденсат%')
                ->orWhere('name', 'like', '%Конденсат%')
            )
            ->update(['category_id' => $kondId, 'updated_at' => now()]);

        // id=232: трубы КПД (чёрные) → Трубы одностенные
        DB::table('products')
            ->where('category_id', 232)
            ->update(['category_id' => $monoTrubyId, 'updated_at' => now()]);

        // Деактивировать опустевшие id=230 и id=232
        DB::table('categories')
            ->whereIn('id', [230, 232])
            ->update(['is_active' => 0, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Вернуть все товары обратно в id=78
        $newCatSlugs = [
            'dymohody-mono','truby-mono','troyniki-mono','kolena-mono',
            'dymohody-sendvich','truby-sendvich','troyniki-sendvich','kolena-sendvich',
            'shibery-dymohod','kondensatootvody','krepleniya-dymohod',
            'zonty-deflektory','teplosyomniki','perehody-adaptery-dymohod',
            'zaglushki-dymohod','prochie-dymohod',
        ];

        $newIds = DB::table('categories')
            ->whereIn('slug', $newCatSlugs)
            ->pluck('id');

        DB::table('products')
            ->whereIn('category_id', $newIds)
            ->update(['category_id' => 78, 'updated_at' => now()]);

        // Вернуть товары из id=230 и id=232
        DB::table('products')
            ->where('name', 'like', '%Консоль%')
            ->where('category_id', '!=', 78)
            ->update(['category_id' => 230, 'updated_at' => now()]);

        // Восстановить is_active
        DB::table('categories')->whereIn('id', [78, 230, 232])->update(['is_active' => 1, 'updated_at' => now()]);

        // Удалить созданные категории
        DB::table('categories')->whereIn('slug', $newCatSlugs)->delete();
    }
};

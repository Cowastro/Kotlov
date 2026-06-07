<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Перепривязать attr 434 (Тип элемента) с cat=303 (Насосы) на cat=307 (Дымоходы)
        DB::table('attributes')->where('id', 434)->update([
            'category_id' => 307,
            'updated_at'  => now(),
        ]);

        // ── 2. Производитель дымоходов (attr 961): перенести с cat=78 на cat=307, включить фильтр
        DB::table('attributes')->where('id', 961)->update([
            'category_id' => 307,
            'in_filter'   => 1,
            'in_product'  => 1,
            'updated_at'  => now(),
        ]);

        // ── 3. Коаксиальные дымоходы (cat=57): включить Тип и Производитель в фильтр
        DB::table('attributes')->whereIn('id', [377, 960])->update([
            'in_filter'  => 1,
            'in_product' => 1,
            'updated_at' => now(),
        ]);

        // ── 4. Убрать in_filter у value/check атрибутов — они не работают как фильтры
        //       (CatalogController обрабатывает только type='select')
        //       Значение in_product остаётся — атрибуты видны на карточке товара
        $valueAttrsToFix = [
            974,  // Мощность (value) cat=61 — дубль select 973
            1245, // Форма стекла (value) cat=90
            1260, // Подача воздуха из вне (check) cat=90
            1267, // Вид горения (value) cat=90
            756, 757, 758, // Высота/Глубина/Ширина (value) cat=111
            1196, 1197, 1199, 1200, // Размеры/Цвет/Материал (value) cat=287
        ];
        DB::table('attributes')->whereIn('id', $valueAttrsToFix)->update([
            'in_filter'  => 0,
            'updated_at' => now(),
        ]);
        // Остальные value-атрибуты (тепловые насосы, конвекторы и пр.) — оставляем как есть
        // т.к. для них нужно отдельно решать про диапазоны
    }

    public function down(): void
    {
        DB::table('attributes')->where('id', 434)->update(['category_id' => 303]);
        DB::table('attributes')->where('id', 961)->update(['category_id' => 78, 'in_filter' => 0]);
        DB::table('attributes')->whereIn('id', [377, 960])->update(['in_filter' => 0]);
        DB::table('attributes')->whereIn('id', [974,1245,1260,1267,756,757,758,1196,1197,1199,1200])
            ->update(['in_filter' => 1]);
    }
};

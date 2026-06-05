<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Переместить последние 6 товаров из "Прочие комплектующие":
 *
 * → Трубы одностенные:      11849  КПД ЧЕРНЫЙ Труба 250мм 2мм ф150
 * → Аксессуары для бани:    3802   Сэндвич-сетка Профи (580)
 *                           3803   Сэндвич-сетка Профи (1000)
 *                           11627  Комплект вентиляции ТиС ВЕНТ-П
 *                           11687  Комплект вентиляции ТиС ВЕНТ-С
 *                           11852  Обрезь базальта (МБТР 3кг)
 */
return new class extends Migration
{
    public function up(): void
    {
        $monoTrub    = DB::table('categories')->where('slug', 'truby-mono')->value('id');
        $aksessBani  = DB::table('categories')->where('slug', 'aksessuary-dlya-bani')->value('id'); // id=73

        // КПД ЧЕРНЫЙ → Трубы одностенные
        DB::table('products')
            ->whereIn('id', [11849])
            ->update(['category_id' => $monoTrub, 'updated_at' => now()]);

        // Бани — сетки, вентиляция, базальт
        DB::table('products')
            ->whereIn('id', [3802, 3803, 11627, 11687, 11852])
            ->update(['category_id' => $aksessBani, 'updated_at' => now()]);
    }

    public function down(): void
    {
        $pid = DB::table('categories')->where('slug', 'prochie-dymohod')->value('id');

        DB::table('products')
            ->whereIn('id', [11849, 3802, 3803, 11627, 11687, 11852])
            ->update(['category_id' => $pid, 'updated_at' => now()]);
    }
};

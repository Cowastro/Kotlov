<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Счётчики газа (cat=96): включить Типоразмер в фильтр
        DB::table('attributes')->where('id', 457)->update([
            'in_filter'  => 1,
            'in_product' => 1,
            'updated_at' => now(),
        ]);

        // Рабочее напряжение (cat=60, Циркуляционные насосы) — select, но 1 значение — не включаем
        // Мощность/Напор (type=value) — требуют создания диапазонов, отдельная задача
    }

    public function down(): void
    {
        DB::table('attributes')->where('id', 457)->update([
            'in_filter'  => 0,
            'updated_at' => now(),
        ]);
    }
};

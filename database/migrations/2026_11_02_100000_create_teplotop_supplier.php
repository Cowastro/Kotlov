<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ООО «ТеплоТоп-Бел», the importer of LEMAX: the supplier page with its «Загрузить прайс» button (two files, опт
 * and РРЦ). Contacts are entered in the admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('suppliers') || DB::table('suppliers')->where('code', 'teplotop')->exists()) {
            return;
        }
        DB::table('suppliers')->insert([
            'code' => 'teplotop',
            'name' => 'ООО «ТеплоТоп-Бел» (LEMAX)',
            'currency' => 'BYN',
            'currency_rate' => 1,
            'is_active' => true,
            'notes' => 'Импортёр LEMAX (котлы, колонки, радиаторы), KRATS, EKF и радиаторной арматуры, ttop.by. Прайсы опт и РРЦ — кнопка «Загрузить прайс» на этой странице. Остатки по запросу.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // The supplier keeps its offers and history.
    }
};

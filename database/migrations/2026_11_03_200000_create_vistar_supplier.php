<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * VISTAR, the distributor of BAXI (boilers, flues, accessories), Viena, Reflex/Flamco and a Viessmann sale: the
 * supplier page with its «Загрузить прайс» button. Contacts are entered in the admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('suppliers') || DB::table('suppliers')->where('code', 'vistar')->exists()) {
            return;
        }
        DB::table('suppliers')->insert([
            'code' => 'vistar',
            'name' => 'VISTAR (BAXI)',
            'currency' => 'BYN',
            'currency_rate' => 1,
            'is_active' => true,
            'notes' => 'Дистрибьютор BAXI (котлы, дымоходы, принадлежности), Viena, Reflex/Flamco, распродажа Viessmann. Прайс опт и розница — кнопка «Загрузить прайс» на этой странице.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // The supplier keeps its offers and history.
    }
};

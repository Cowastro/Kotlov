<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «Престиж Тепло», the distributor of Vaillant, Protherm, Kospel, Drazice, Reflex, Flamco, Galmet, Afriso, Meibes,
 * Comisa and Conex Banninger: the supplier page with its «Загрузить прайс» button. Contacts are entered in the admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('suppliers') || DB::table('suppliers')->where('code', 'prestizh_teplo')->exists()) {
            return;
        }
        DB::table('suppliers')->insert([
            'code' => 'prestizh_teplo',
            'name' => 'Престиж Тепло',
            'currency' => 'BYN',
            'currency_rate' => 1,
            'is_active' => true,
            'notes' => 'Дистрибьютор Vaillant, Protherm, Kospel, Drazice, Reflex, Flamco, Galmet, Afriso, Meibes, Comisa, Conex Banninger. Прайс (xlsm, приходит рассылкой со ссылкой на Dropbox) — кнопка «Загрузить прайс» на этой странице.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // The supplier keeps its offers and history.
    }
};

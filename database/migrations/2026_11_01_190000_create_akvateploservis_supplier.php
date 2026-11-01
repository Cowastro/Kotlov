<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ООО «Акватеплосервис», the importer of Galmet and Apamet: the supplier page with its «Загрузить прайс» button.
 * Contacts are entered in the admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('suppliers') || DB::table('suppliers')->where('code', 'akvateploservis')->exists()) {
            return;
        }
        DB::table('suppliers')->insert([
            'code' => 'akvateploservis',
            'name' => 'ООО «Акватеплосервис» (Galmet, Apamet)',
            'currency' => 'BYN',
            'currency_rate' => 1,
            'is_active' => true,
            'notes' => 'Импортёр Galmet (Польша) и Apamet (Турция) в Беларуси, galmet.by. Прайс (опт, РРЦ и остатки) — кнопка «Загрузить прайс» на этой странице.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // The supplier keeps its offers and history.
    }
};

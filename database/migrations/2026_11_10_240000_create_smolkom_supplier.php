<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ООО «Смолком»: Smolcom portals and Dimplex, Royal Flame, Real Flame electric fireplaces. The supplier page has the
 * «Загрузить прайс» and «Обновить с Google Drive» buttons; contacts are entered in the admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('suppliers') || DB::table('suppliers')->where('code', 'smolkom')->exists()) {
            return;
        }
        DB::table('suppliers')->insert([
            'code' => 'smolkom',
            'name' => 'ООО «Смолком» (Smolcom, Dimplex, Royal Flame, Real Flame)',
            'currency' => 'BYN',
            'currency_rate' => 1,
            'is_active' => true,
            'notes' => 'Порталы Smolcom и электрокамины Dimplex, Royal Flame, Real Flame. Прайс с остатками — общий файл Google Drive, забирается каждое утро; вручную — кнопки «Загрузить прайс» и «Обновить с Google Drive».',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // The supplier keeps its offers and history.
    }
};

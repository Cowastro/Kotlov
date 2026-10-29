<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Чистый берег (bereg.by) — stock and prices every morning; the supplier page carries the upload button.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('suppliers') || DB::table('suppliers')->where('code', 'chisty_bereg')->exists()) {
            return;
        }

        DB::table('suppliers')->insert([
            'code' => 'chisty_bereg',
            'name' => 'Чистый берег',
            'currency' => 'BYN',
            'currency_rate' => 1,
            'communication_channels' => json_encode([['type' => 'site', 'value' => 'https://www.bereg.by/']], JSON_UNESCAPED_SLASHES),
            'notes' => 'Инженерная сантехника и отопление, bereg.by. Остатки и цены — ежедневный xls «Остатки и цены Чистого берега», кнопка «Загрузить остатки и цены» на этой странице.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // The supplier stays with its offers.
    }
};

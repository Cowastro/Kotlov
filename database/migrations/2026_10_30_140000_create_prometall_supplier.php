<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ProMetall through ЧТУП «Бани и печи» (banipechi.by): the wholesale price list is uploaded on the supplier page,
 * stock is on request. The manager's phone is entered in the admin, not kept in the repository.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('suppliers') || DB::table('suppliers')->where('code', 'prometall')->exists()) {
            return;
        }

        DB::table('suppliers')->insert([
            'code' => 'prometall',
            'name' => 'ПроМеталл (ЧТУП «Бани и печи»)',
            'currency' => 'BYN',
            'currency_rate' => 1,
            'communication_channels' => json_encode([
                ['type' => 'site', 'value' => 'https://banipechi.by/'],
                ['type' => 'site', 'value' => 'https://prometall.ru/'],
            ], JSON_UNESCAPED_SLASHES),
            'notes' => 'Дилер ProMetall в Беларуси (печи «Атмосфера», «Эйфория», литьё, порталы). Прайс с оптом и РРЦ в BYN — кнопка «Загрузить прайс» на этой странице. Остатки по запросу у менеджера.',
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

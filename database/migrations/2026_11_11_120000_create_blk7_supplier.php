<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ООО «БЛК7» (b2b.blk7.by): Lavoro boilers with the warehouse stock, retail and wholesale prices from the open brand
 * page of their B2B site. Contacts are entered in the admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('suppliers') || DB::table('suppliers')->where('code', 'blk7')->exists()) {
            return;
        }
        DB::table('suppliers')->insert([
            'code' => 'blk7',
            'name' => 'ООО «БЛК7» (Lavoro)',
            'currency' => 'BYN',
            'currency_rate' => 1,
            'is_active' => true,
            'notes' => 'ООО «БЛК7», B2B-сайт b2b.blk7.by. Котлы Lavoro: остаток, розничная и оптовая цена с открытой страницы бренда, обновление каждые 3 часа.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // The supplier keeps its offers and history.
    }
};

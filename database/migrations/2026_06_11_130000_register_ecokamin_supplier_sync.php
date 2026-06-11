<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SUPPLIER_CODE = 'ecokamin';
    private const SYNC_KEY = 'ecokamin_fireboxes';
    private const SOURCE_URL = 'https://ecokamin.ru/catalog/kaminnye_topki/';

    public function up(): void
    {
        $now = now();

        // Курс RUB → BYN заглушка (1): задать реальный в /admin/suppliers до боевого запуска.
        // Не updateOrInsert: при повторном прогоне не затирать настроенный курс.
        if (! DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->exists()) {
            DB::table('suppliers')->insert([
                'code' => self::SUPPLIER_CODE,
                'name' => 'EcoKamin',
                'currency' => 'RUB',
                'currency_rate' => 1,
                'contact' => self::SOURCE_URL,
                'notes' => 'Каминные топки ecokamin.ru (кроме Invicta). Перед боевым запуском задать курс RUB → BYN.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! Schema::hasTable('supplier_syncs')) {
            return;
        }

        DB::table('supplier_syncs')->updateOrInsert(
            ['key' => self::SYNC_KEY],
            [
                'name' => 'EcoKamin',
                'code' => self::SUPPLIER_CODE,
                'title' => 'EcoKamin: каминные топки',
                'description' => 'Обновляет цены и карточки каминных топок с EcoKamin, кроме Invicta.',
                'command' => 'supplier:sync-ecokamin-fireboxes',
                'source_url' => self::SOURCE_URL,
                'image_disk_path' => 'img/products/ecokamin',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('supplier_syncs')) {
            DB::table('supplier_syncs')->where('key', self::SYNC_KEY)->delete();
        }
    }
};

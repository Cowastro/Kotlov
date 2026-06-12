<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SUPPLIER_CODE = 'thermostudio';
    private const SYNC_KEY = 'thermostudio_viessmann_gas_boilers';
    private const SOURCE_URL = 'https://teplo.by/catalog/gazovye-kotly/?jsf=jet-woo-products-grid&tax=product_cat:554';

    public function up(): void
    {
        $now = now();

        if (! DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->exists()) {
            DB::table('suppliers')->insert([
                'code' => self::SUPPLIER_CODE,
                'name' => 'Термостудия',
                'currency' => 'BYN',
                'currency_rate' => 1,
                'contact' => self::SOURCE_URL,
                'notes' => 'Газовые котлы Viessmann с teplo.by. Цены BYN.',
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
                'name' => 'Термостудия Viessmann',
                'code' => self::SUPPLIER_CODE,
                'title' => 'Термостудия: газовые котлы Viessmann',
                'description' => 'Скрапит газовые котлы Viessmann с teplo.by: цены BYN, характеристики, сервис, документы, фото и промо-флаги.',
                'command' => 'supplier:sync-thermostudio-viessmann',
                'source_url' => self::SOURCE_URL,
                'image_disk_path' => 'img/products/thermostudio/viessmann',
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

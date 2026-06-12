<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SUPPLIER_CODE = 'gorodkotlov';
    private const SYNC_KEY = 'gorodkotlov_vaillant_gas_boilers';
    private const SOURCE_URL = 'https://gorodkotlov.by/catalog/gazovye-kotly/vaillant/';

    public function up(): void
    {
        $now = now();

        if (! DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->exists()) {
            DB::table('suppliers')->insert([
                'code' => self::SUPPLIER_CODE,
                'name' => 'Город Котлов',
                'currency' => 'BYN',
                'currency_rate' => 1,
                'contact' => self::SOURCE_URL,
                'notes' => 'Газовые котлы Vaillant с gorodkotlov.by. Цены BYN.',
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
                'name' => 'Город Котлов Vaillant',
                'code' => self::SUPPLIER_CODE,
                'title' => 'Город Котлов: газовые котлы Vaillant',
                'description' => 'Скрапит газовые котлы Vaillant с gorodkotlov.by: цены BYN, характеристики, сервис, документы, фото и промо-флаги.',
                'command' => 'supplier:sync-gorodkotlov-vaillant',
                'source_url' => self::SOURCE_URL,
                'image_disk_path' => 'img/products/gorodkotlov/vaillant',
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

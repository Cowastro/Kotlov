<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SUPPLIER_CODE = 'belkomin';
    private const SYNC_KEY = 'belkomin_tis_boilers';
    private const SOURCE_URL = 'https://www.belkomin.com/katalog/kotly/';

    public function up(): void
    {
        $now = now();

        DB::table('suppliers')->updateOrInsert(
            ['code' => self::SUPPLIER_CODE],
            [
                'name' => 'БелКомин',
                'currency' => 'BYN',
                'currency_rate' => 1,
                'contact' => self::SOURCE_URL,
                'notes' => 'Официальный сайт производителя котлов TIS.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        if (! Schema::hasTable('supplier_syncs')) {
            return;
        }

        DB::table('supplier_syncs')->updateOrInsert(
            ['key' => self::SYNC_KEY],
            [
                'name' => 'БелКомин',
                'code' => self::SUPPLIER_CODE,
                'title' => 'БелКомин: котлы TIS',
                'description' => 'Обновляет цены, характеристики, описания и фотографии твердотопливных котлов TIS.',
                'command' => 'supplier:sync-belkomin-tis-boilers',
                'source_url' => self::SOURCE_URL,
                'image_disk_path' => 'img/products/belkomin-tis',
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

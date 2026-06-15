<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CODE = 'bania';
    private const SYNC_KEY = 'bania_wood_sauna_stoves';
    private const SOURCE_URL = 'https://bania.by/vse-dlia-bani/drovjanye-pechi-dlja-bani';

    public function up(): void
    {
        $now = now();

        DB::table('suppliers')->updateOrInsert(
            ['code' => self::CODE],
            [
                'name' => 'BANIA.by',
                'currency' => 'BYN',
                'currency_rate' => 1,
                'contact' => 'https://bania.by',
                'notes' => 'BANIA.by supplier sync for wood-fired sauna stoves. Currency BYN.',
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
                'name' => 'BANIA.by',
                'code' => self::CODE,
                'title' => 'BANIA.by: wood-fired sauna stoves',
                'description' => 'Scrapes BANIA.by wood-fired sauna stoves, supplier prices, stock, photos and attributes.',
                'command' => 'supplier:scrape-bania',
                'source_url' => self::SOURCE_URL,
                'image_disk_path' => 'img/products/bania',
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

        DB::table('suppliers')->where('code', self::CODE)->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CODE = 'rn-profi';
    private const SYNC_KEY = 'rn_profi_price';
    private const SOURCE_URL = 'https://rn-profi.by/';

    public function up(): void
    {
        $now = now();

        DB::table('suppliers')->updateOrInsert(
            ['code' => self::CODE],
            [
                'name' => 'RN-Profi',
                'currency' => 'BYN',
                'currency_rate' => 1,
                'contact' => self::SOURCE_URL,
                'notes' => 'RN-Profi supplier. Prices and stock are synced from Google Sheets; product content can be enriched from rn-profi.by or teplodvor.by.',
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
                'name' => 'RN-Profi',
                'code' => self::CODE,
                'title' => 'RN-Profi: price and stock',
                'description' => 'Audits and syncs RN-Profi Google price list into supplier_products.',
                'command' => 'supplier:sync-rn-profi',
                'source_url' => self::SOURCE_URL,
                'image_disk_path' => 'img/products/rn-profi',
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

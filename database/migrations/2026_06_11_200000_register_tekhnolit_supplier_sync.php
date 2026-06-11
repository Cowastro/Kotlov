<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SUPPLIER_CODE = 'tekhnolit';
    private const SYNC_KEY      = 'tekhnolit_teplodvor';
    private const SOURCE_URL    = 'https://www.teplodvor.by/shop/tekhnolit/';

    public function up(): void
    {
        $now = now();

        if (! DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->exists()) {
            DB::table('suppliers')->insert([
                'code'          => self::SUPPLIER_CODE,
                'name'          => 'ТехноЛит (teplodvor.by)',
                'currency'      => 'BYN',
                'currency_rate' => 1,
                'contact'       => self::SOURCE_URL,
                'notes'         => 'Банные печи ТехноЛит. Цены с teplodvor.by (BYN).',
                'is_active'     => true,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }

        if (! Schema::hasTable('supplier_syncs')) {
            return;
        }

        DB::table('supplier_syncs')->updateOrInsert(
            ['key' => self::SYNC_KEY],
            [
                'name'            => 'ТехноЛит',
                'code'            => self::SUPPLIER_CODE,
                'title'           => 'ТехноЛит: банные печи (teplodvor.by)',
                'description'     => 'Скрапит банные печи ТехноЛит с teplodvor.by: цены BYN, описания, фото, характеристики.',
                'command'         => 'supplier:sync-tekhnolit',
                'source_url'      => self::SOURCE_URL,
                'image_disk_path' => 'img/products/tekhnolit',
                'is_active'       => true,
                'created_at'      => $now,
                'updated_at'      => $now,
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

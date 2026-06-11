<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SUPPLIER_CODE = 'sibir';
    private const SYNC_KEY      = 'sibir_teplodvor';
    private const SOURCE_URL    = 'https://www.teplodvor.by/shop/kotly/tverdotoplivnye/sibir/';

    public function up(): void
    {
        $now = now();

        if (! DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->exists()) {
            DB::table('suppliers')->insert([
                'code'          => self::SUPPLIER_CODE,
                'name'          => 'Сибирь (teplodvor.by)',
                'currency'      => 'BYN',
                'currency_rate' => 1,
                'contact'       => self::SOURCE_URL,
                'notes'         => 'Твердотопливные котлы Сибирь. Цены с teplodvor.by (BYN).',
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
                'name'            => 'Сибирь',
                'code'            => self::SUPPLIER_CODE,
                'title'           => 'Сибирь: твердотопливные котлы (teplodvor.by)',
                'description'     => 'Скрапит твердотопливные котлы Сибирь с teplodvor.by: цены BYN, описания, фото, характеристики.',
                'command'         => 'supplier:sync-sibir',
                'source_url'      => self::SOURCE_URL,
                'image_disk_path' => 'img/products/sibir',
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

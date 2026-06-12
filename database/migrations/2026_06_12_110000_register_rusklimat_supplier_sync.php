<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CODE     = 'rusklimat';
    private const SYNC_KEY = 'rusklimat_stock';

    public function up(): void
    {
        $now = now();

        DB::table('suppliers')->updateOrInsert(
            ['code' => self::CODE],
            [
                'name'          => 'Русклимат',
                'currency'      => 'BYN',
                'currency_rate' => 1,
                'contact'       => 'https://rusklimat.by/',
                'notes'         => 'Интернет-магазин климатической и отопительной техники. Прайс: Google Sheets (остатки + цены). Валюта BYN.',
                'is_active'     => true,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]
        );

        if (! Schema::hasTable('supplier_syncs')) {
            return;
        }

        DB::table('supplier_syncs')->updateOrInsert(
            ['key' => self::SYNC_KEY],
            [
                'name'            => 'Русклимат',
                'code'            => self::CODE,
                'title'           => 'Русклимат: остатки и цены',
                'description'     => 'Загружает цены и остатки из таблицы Google Sheets. Сопоставляет товары Русклимата с каталогом KOTLOV по артикулу и бренду+модели.',
                'command'         => 'supplier:sync-rusklimat',
                'source_url'      => 'https://rusklimat.by/',
                'image_disk_path' => 'img/products/rusklimat',
                'is_active'       => true,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('supplier_syncs')->where('key', self::SYNC_KEY)->delete();
        DB::table('suppliers')->where('code', self::CODE)->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SUPPLIER_CODE = 'metabel';
    private const SYNC_KEY      = 'metabel_price';
    private const SOURCE_URL    = 'https://metabel.by/produktsiya';

    public function up(): void
    {
        $now = now();

        if (! DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->exists()) {
            DB::table('suppliers')->insert([
                'code'          => self::SUPPLIER_CODE,
                'name'          => 'Мета-Бел',
                'currency'      => 'BYN',
                'currency_rate' => 1,
                'contact'       => self::SOURCE_URL,
                'notes'         => 'МРЦ-прайс Excel. Загружать файл вручную в storage/prices/.',
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
                'name'            => 'МЕТА-БЕЛ',
                'code'            => self::SUPPLIER_CODE,
                'title'           => 'МЕТА-БЕЛ: МРЦ-прайс',
                'description'     => 'Обновляет цены и карточки печей, топок, литья Мета-Бел из Excel-файла МРЦ.',
                'command'         => 'supplier:sync-metabel',
                'source_url'      => self::SOURCE_URL,
                'image_disk_path' => null,
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

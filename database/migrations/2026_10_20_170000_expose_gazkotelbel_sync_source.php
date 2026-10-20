<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SOURCE_URL = 'https://docs.google.com/spreadsheets/d/1ry8S1_DCHYuWgJ1uGHAgMhTLi9CfGEXpNj77oNHOo9Y/edit';

    public function up(): void
    {
        if (! Schema::hasTable('suppliers')) {
            return;
        }

        $supplier = DB::table('suppliers')->where('code', 'gazkotelbel')->first();
        if (! $supplier) {
            return;
        }

        $now = now();
        if (Schema::hasTable('supplier_syncs')) {
            DB::table('supplier_syncs')->where('key', 'gazkotelbel_price')->update([
                'supplier_id' => $supplier->id,
                'source_url' => self::SOURCE_URL,
                'updated_at' => $now,
            ]);
        }

        if (Schema::hasTable('supplier_sources')) {
            DB::table('supplier_sources')->updateOrInsert(
                [
                    'supplier_id' => $supplier->id,
                    'name' => 'Прайс и остатки ГазКотелБел',
                ],
                [
                    'type' => 'google_sheet',
                    'config' => json_encode(['url' => self::SOURCE_URL], JSON_UNESCAPED_UNICODE),
                    'schedule_cron' => 'Ежедневно в 08:30',
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        // Operational supplier source corrections intentionally remain in place.
    }
};

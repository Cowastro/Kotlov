<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The customer orders 1C refused «ПриЗаписи» had a line «Отгрузить» for goods 1C does not have. Now such lines go
 * «Обеспечить», so orders go the normal way again (with their number at once); a draft stays the fallback.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('onec_order_profiles')->get(['id', 'meta']) as $profile) {
            $meta = json_decode((string) $profile->meta, true) ?: [];
            if (! empty($meta['load_mode']) || ! empty($meta['variant'])) {
                unset($meta['load_mode'], $meta['variant']);
                DB::table('onec_order_profiles')->where('id', $profile->id)->update(['meta' => json_encode($meta, JSON_UNESCAPED_UNICODE)]);
            }
        }
    }

    public function down(): void {}
};

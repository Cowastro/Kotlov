<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('integration_sources')->orderBy('id')->each(function (object $source): void {
            $settings = json_decode((string) ($source->settings ?? ''), true) ?: [];
            $settings = array_merge([
                'price_tax_mode' => 'exclusive',
                'vat_rate' => 20,
                'warehouse_label' => 'Основной',
            ], $settings);

            DB::table('integration_sources')->where('id', $source->id)->update([
                'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        // Price interpretation is operational data and must not be removed on rollback.
    }
};

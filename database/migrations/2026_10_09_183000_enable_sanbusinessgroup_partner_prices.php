<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $source = DB::table('integration_sources')->where('code', 'onec')->first();
        if (! $source) {
            return;
        }

        $settings = json_decode((string) ($source->settings ?? ''), true) ?: [];
        $settings['b2b_enabled'] = true;
        $settings['partner_name'] = 'ООО «СанБизнесГруп»';

        DB::table('integration_sources')->where('id', $source->id)->update([
            'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $source = DB::table('integration_sources')->where('code', 'onec')->first();
        if (! $source) {
            return;
        }

        $settings = json_decode((string) ($source->settings ?? ''), true) ?: [];
        $settings['b2b_enabled'] = false;
        unset($settings['partner_name']);

        DB::table('integration_sources')->where('id', $source->id)->update([
            'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }
};

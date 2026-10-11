<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('integration_sources')
            ->where('code', 'onec')
            ->orderBy('id')
            ->each(function (object $source): void {
                $settings = json_decode((string) $source->settings, true) ?: [];
                $settings['order_pricing_types'] = ['b2b'];
                $settings['require_linked_order_products'] = true;

                DB::table('integration_sources')->where('id', $source->id)->update([
                    'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        DB::table('integration_sources')
            ->where('code', 'onec')
            ->orderBy('id')
            ->each(function (object $source): void {
                $settings = json_decode((string) $source->settings, true) ?: [];
                unset($settings['order_pricing_types'], $settings['require_linked_order_products']);

                DB::table('integration_sources')->where('id', $source->id)->update([
                    'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);
            });
    }
};

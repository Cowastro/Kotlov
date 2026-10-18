<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private const READY_B2B_SALE_SOURCES = ['onec', 'belomo_wholesale'];

    public function up(): void
    {
        DB::table('integration_sources')
            ->whereIn('code', self::READY_B2B_SALE_SOURCES)
            ->orderBy('id')
            ->get(['id', 'settings'])
            ->each(function (object $source): void {
                $settings = json_decode((string) ($source->settings ?? ''), true) ?: [];

                if (array_key_exists('b2b_price_role', $settings)) {
                    return;
                }

                $settings['b2b_price_role'] = 'sale';

                DB::table('integration_sources')->where('id', $source->id)->update([
                    'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        DB::table('integration_sources')
            ->whereIn('code', self::READY_B2B_SALE_SOURCES)
            ->orderBy('id')
            ->get(['id', 'settings'])
            ->each(function (object $source): void {
                $settings = json_decode((string) ($source->settings ?? ''), true) ?: [];

                if (($settings['b2b_price_role'] ?? null) !== 'sale') {
                    return;
                }

                unset($settings['b2b_price_role']);

                DB::table('integration_sources')->where('id', $source->id)->update([
                    'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            });
    }
};

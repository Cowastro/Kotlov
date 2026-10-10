<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('integration_sources') || ! Schema::hasTable('categories')) {
            return;
        }

        $chimneyCategoryId = DB::table('categories')->where('slug', 'dymohody')->value('id');
        $source = DB::table('integration_sources')->where('code', 'onec')->first(['id', 'settings']);

        if (! $chimneyCategoryId || ! $source) {
            return;
        }

        $settings = json_decode((string) $source->settings, true);
        $settings = is_array($settings) ? $settings : [];

        if (! array_key_exists('b2b_category_ids', $settings)) {
            $settings['b2b_category_ids'] = [(int) $chimneyCategoryId];

            DB::table('integration_sources')->where('id', $source->id)->update([
                'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }
    }

    public function down(): void
    {
        // Operational publication scope must not be removed by a code rollback.
    }
};

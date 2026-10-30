<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The background AI limit was counted in the cache, and every deploy cleared it: on 30.10 the queues
 * spent well over 300 searches. The day is closed; tomorrow's 300 are counted in the settings table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('settings')->where('key', 'market_research_background_usage')->exists()) {
            return;
        }
        DB::table('settings')->insert([
            'key' => 'market_research_background_usage', 'value' => '2026-10-30:300',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void {}
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * «Регуляторы» held thermostatic heads, valves, floor thermostats, pumps and blowers: a «Вентиляторы наддува» section
 * next to it, and catalog:sort-boiler-automation moves the strays and fills the «Назначение» filter.
 */
return new class extends Migration
{
    public function up(): void
    {
        $parts = DB::table('categories')->where('slug', 'zapchasti-dlya-kotlov')->first();
        if (! $parts) {
            return;
        }
        if (! DB::table('categories')->where('slug', 'ventilyatory-nadduva')->exists()) {
            $name = 'Вентиляторы наддува для котлов';
            DB::table('categories')->insert([
                'parent_id' => $parts->parent_id, 'name' => 'Вентиляторы наддува', 'slug' => 'ventilyatory-nadduva', 'h1' => $name,
                'type' => 'child', 'sort_order' => (int) $parts->sort_order + 1, 'is_active' => true,
                'meta_title' => $name.' купить в %city% — цены | KOTLOV',
                'meta_description' => $name.': вентиляторы для твердотопливных котлов, производительность и мощность, цены в BYN, доставка по Беларуси.',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        if (! app()->runningUnitTests() && DB::table('categories')->where('slug', 'regulyatory')->exists()) {
            Artisan::call('catalog:sort-boiler-automation', ['--apply' => true]);
        }
    }

    public function down(): void
    {
        // Cards stay where they were sorted; the section is kept for them.
    }
};

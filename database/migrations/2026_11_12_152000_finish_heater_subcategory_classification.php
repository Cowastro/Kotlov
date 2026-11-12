<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $root = DB::table('categories')->where('slug', 'obogrevateli')->value('id');
        if (! $root) return;
        foreach (['elektricheskie-teploakkumulyatory' => 'Электрические теплоаккумуляторы',
            'elektricheskie-teplye-plintusy' => 'Электрические тёплые плинтусы'] as $slug => $name) {
            if (! DB::table('categories')->where('slug', $slug)->exists()) {
                DB::table('categories')->insert(['slug' => $slug, 'name' => $name, 'h1' => $name,
                    'parent_id' => $root, 'type' => 'child', 'is_active' => true, 'sort_order' => 80,
                    'created_at' => now(), 'updated_at' => now()]);
            }
        }
        // The hub now includes legacy convector children. Apply the same reviewed rules there.
        $repair = require __DIR__.'/2026_11_12_150000_reclassify_heater_catalog.php';
        $repair->up();
    }

    public function down(): void {}
};

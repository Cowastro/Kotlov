<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // parent_id=0, sort_order=25 — между Тепловыми насосами (20) и Каминами (30)
        DB::table('categories')
            ->where('id', 297)
            ->update([
                'parent_id'  => 0,
                'sort_order' => 25,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('categories')
            ->where('id', 297)
            ->update([
                'parent_id'  => 56,
                'sort_order' => 0,
                'updated_at' => now(),
            ]);
    }
};

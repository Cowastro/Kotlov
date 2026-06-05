<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('categories')
            ->where('id', 50)
            ->update([
                'parent_id'  => 0,
                'sort_order' => 15,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('categories')
            ->where('id', 50)
            ->update([
                'parent_id'  => 49,
                'sort_order' => 1,
                'updated_at' => now(),
            ]);
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('brands')->updateOrInsert(
            ['slug' => 'doorwood'],
            [
                'name' => 'DoorWood',
                'h1' => 'DoorWood',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('brands')
            ->where('slug', 'doorwood')
            ->where('name', 'DoorWood')
            ->delete();
    }
};

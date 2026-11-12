<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    public function up(): void
    {
        $root = DB::table('categories')->where('slug', 'obogrevateli')->value('id');
        if (! $root) return;
        DB::table('categories')->whereIn('slug', ['elektricheskie-konvektoryi', 'maslyanye-obrevateli',
            'infrakrasnye-obrevateli', 'teploventilyatory', 'teplovye-pushki', 'teplovye-zavesy'])
            ->update(['parent_id' => $root, 'type' => 'child', 'updated_at' => now()]);
        Cache::flush();
    }

    public function down(): void {}
};

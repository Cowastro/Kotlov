<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categories')) {
            return;
        }

        $parentId = DB::table('categories')
            ->where('slug', 'komplektuyushhie-dlya-otopleniya')
            ->value('id');

        if (! $parentId) {
            return;
        }

        DB::table('categories')
            ->whereIn('slug', [
                'avtotransformatory-latr',
                'komplektuyushchie-stabilizatorov',
            ])
            ->update([
                'parent_id' => $parentId,
                'updated_at' => now(),
            ]);

        Cache::forget('nav:categories:orderable:v1');
    }

    public function down(): void
    {
        // This repairs catalog ownership; rolling it back would reintroduce
        // orphaned root navigation entries.
    }
};

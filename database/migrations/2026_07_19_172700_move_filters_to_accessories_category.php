<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $accessoriesId = (int) DB::table('categories')->where('slug', 'komplektuyushhie-dlya-otopleniya')->value('id');
        $filtersId = (int) DB::table('categories')->where('slug', 'filtry')->value('id');

        if (! $accessoriesId || ! $filtersId) {
            return;
        }

        DB::table('categories')
            ->where('id', $filtersId)
            ->update([
                'parent_id' => $accessoriesId,
            ]);
    }

    public function down(): void
    {
        $filtersId = (int) DB::table('categories')->where('slug', 'filtry')->value('id');

        if (! $filtersId) {
            return;
        }

        $newRoot = (int) DB::table('categories')
            ->where('slug', 'kotly')
            ->where('parent_id', 0)
            ->value('id');

        if (! $newRoot) {
            return;
        }

        DB::table('categories')
            ->where('id', $filtersId)
            ->update([
                'parent_id' => $newRoot,
            ]);
    }
};

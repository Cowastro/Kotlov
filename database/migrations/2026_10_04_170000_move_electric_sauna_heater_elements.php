<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $electricHeatersId = DB::table('categories')->where('slug', 'elektrokamenki')->value('id');
        $accessoriesId = DB::table('categories')
            ->where('slug', 'komplektuyushchie-dlya-elektrokamenok')
            ->value('id');

        if (! $electricHeatersId || ! $accessoriesId) {
            return;
        }

        DB::table('products')
            ->where('category_id', $electricHeatersId)
            ->where('name', 'like', 'ТЭН для электрокаменки%')
            ->update([
                'category_id' => $accessoriesId,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Catalog repair: intentionally not reversed.
    }
};

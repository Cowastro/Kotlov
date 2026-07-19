<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $kotlyCategoryId = (int) DB::table('categories')->where('slug', 'kotly')->value('id');
        $pelletCategoryId = (int) DB::table('categories')->where('slug', 'kotly-na-pelletah')->value('id');

        if (! $kotlyCategoryId || ! $pelletCategoryId) {
            return;
        }

        DB::table('categories')
            ->where('id', $pelletCategoryId)
            ->update([
                'parent_id' => $kotlyCategoryId,
            ]);
    }

    public function down(): void
    {
        $solidCategoryId = (int) DB::table('categories')->where('slug', 'tverdotoplivnye')->value('id');
        $pelletCategoryId = (int) DB::table('categories')->where('slug', 'kotly-na-pelletah')->value('id');

        if (! $solidCategoryId || ! $pelletCategoryId) {
            return;
        }

        DB::table('categories')
            ->where('id', $pelletCategoryId)
            ->update([
                'parent_id' => $solidCategoryId,
            ]);
    }
};

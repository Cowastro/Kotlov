<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $klimatId = (int) DB::table('categories')->where('slug', 'klimat')->value('id');
        $konvektoryId = (int) DB::table('categories')->where('slug', 'elektricheskie-konvektoryi')->value('id');

        if (! $klimatId || ! $konvektoryId) {
            return;
        }

        DB::table('categories')
            ->where('id', $konvektoryId)
            ->update([
                'parent_id' => $klimatId,
            ]);
    }

    public function down(): void
    {
        $konvektoryId = (int) DB::table('categories')->where('slug', 'elektricheskie-konvektoryi')->value('id');

        if (! $konvektoryId) {
            return;
        }

        $targetRootId = (int) DB::table('categories')->where('slug', 'kotly')->where('parent_id', 0)->value('id');

        if (! $targetRootId) {
            return;
        }

        DB::table('categories')
            ->where('id', $konvektoryId)
            ->update([
                'parent_id' => $targetRootId,
            ]);
    }
};

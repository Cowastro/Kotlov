<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $waterHeatersId = DB::table('categories')->where('slug', 'vodonagrevateli')->value('id');

        if (! $waterHeatersId) {
            return;
        }

        DB::table('categories')
            ->where('slug', 'bufernye-emkosti')
            ->update([
                'parent_id' => $waterHeatersId,
                'sort_order' => 60,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $accessoriesId = DB::table('categories')->where('slug', 'komplektuyushhie-dlya-otopleniya')->value('id');

        if ($accessoriesId) {
            DB::table('categories')
                ->where('slug', 'bufernye-emkosti')
                ->update([
                    'parent_id' => $accessoriesId,
                    'updated_at' => now(),
                ]);
        }
    }
};

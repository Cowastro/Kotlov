<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // id=60: переименовать "Насосы" → "Циркуляционные насосы" (остаётся в /otoplenie)
    // id=198: перенести "Насосы погружные скважинные" из /otoplenie (parent=56)
    //         в /vodosnabzhenie → /nasosy (parent=246)

    public function up(): void
    {
        DB::table('categories')
            ->where('id', 60)
            ->update(['name' => 'Циркуляционные насосы', 'updated_at' => now()]);

        DB::table('categories')
            ->where('id', 198)
            ->update(['parent_id' => 246, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('categories')
            ->where('id', 60)
            ->update(['name' => 'Насосы', 'updated_at' => now()]);

        DB::table('categories')
            ->where('id', 198)
            ->update(['parent_id' => 56, 'updated_at' => now()]);
    }
};

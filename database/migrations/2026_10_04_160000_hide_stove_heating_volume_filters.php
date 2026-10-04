<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('attributes')
            ->whereIn('name', [
                'Объём отапливаемого помещения',
                'Объем отапливаемого помещения',
            ])
            ->update([
                'in_filter' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('attributes')
            ->whereIn('name', [
                'Объём отапливаемого помещения',
                'Объем отапливаемого помещения',
            ])
            ->update([
                'in_filter' => true,
                'updated_at' => now(),
            ]);
    }
};

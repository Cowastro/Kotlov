<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('attribute_options')
            ->whereIn('id', [1306, 1278, 1261, 1255, 1251])
            ->update([
                'name' => DB::raw('TRIM(name)'),
            ]);
    }

    public function down(): void
    {
        // Нормализация грязных данных — откат не требуется
    }
};

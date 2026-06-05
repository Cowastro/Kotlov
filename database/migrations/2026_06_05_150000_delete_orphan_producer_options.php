<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('attribute_options')
            ->whereIn('id', [112, 290, 747, 795, 150, 140, 1193])
            ->delete();
    }

    public function down(): void
    {
        // Восстановление не предусмотрено — это мёртвые строки без привязок к товарам
    }
};

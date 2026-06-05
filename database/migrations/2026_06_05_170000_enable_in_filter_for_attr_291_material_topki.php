<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('attributes')
            ->where('id', 291)
            ->where('type', 'select')
            ->update(['in_filter' => 1]);
    }

    public function down(): void
    {
        DB::table('attributes')
            ->where('id', 291)
            ->update(['in_filter' => 0]);
    }
};

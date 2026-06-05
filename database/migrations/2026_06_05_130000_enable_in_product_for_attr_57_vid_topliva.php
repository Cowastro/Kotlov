<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('attributes')
            ->where('id', 57)
            ->update(['in_product' => 1]);
    }

    public function down(): void
    {
        DB::table('attributes')
            ->where('id', 57)
            ->update(['in_product' => 0]);
    }
};

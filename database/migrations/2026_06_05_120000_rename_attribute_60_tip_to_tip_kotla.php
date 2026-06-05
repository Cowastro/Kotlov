<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('attributes')
            ->where('id', 60)
            ->where('name', 'Тип')
            ->update(['name' => 'Тип котла']);
    }

    public function down(): void
    {
        DB::table('attributes')
            ->where('id', 60)
            ->where('name', 'Тип котла')
            ->update(['name' => 'Тип']);
    }
};

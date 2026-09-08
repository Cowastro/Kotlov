<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('brands')
            ->where('slug', 'antifrogen')
            ->update([
                'h1' => 'Antifrogen N и L',
                'updated_at' => now(),
            ]);
    }
};

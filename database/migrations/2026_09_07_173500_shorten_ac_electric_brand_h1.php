<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('brands')
            ->where('slug', 'ac-electric')
            ->update([
                'h1' => 'AC Electric — климат и теплый пол',
                'updated_at' => now(),
            ]);
    }
};

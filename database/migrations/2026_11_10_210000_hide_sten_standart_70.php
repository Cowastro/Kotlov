<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** «СТЭН Стандарт 70» does not exist in the manufacturer's line-up (a price-list typo) — hide the card. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')->where('slug', 'sten-kotel-sten-standart-70-380')->update(['is_active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('products')->where('slug', 'sten-kotel-sten-standart-70-380')->update(['is_active' => true, 'updated_at' => now()]);
    }
};

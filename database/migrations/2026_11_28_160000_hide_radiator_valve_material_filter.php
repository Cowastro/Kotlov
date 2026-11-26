<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** «Материал» of «Радиаторная арматура» was set on one card only: it leaves the filter (it stays in the specs). */
return new class extends Migration
{
    public function up(): void
    {
        $section = DB::table('categories')->where('slug', 'radiatornaya-armatura')->value('id');
        if ($section) {
            DB::table('attributes')->where('category_id', $section)->where('name', 'Материал')
                ->update(['in_filter' => false, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $section = DB::table('categories')->where('slug', 'radiatornaya-armatura')->value('id');
        DB::table('attributes')->where('category_id', $section)->where('name', 'Материал')->update(['in_filter' => true]);
    }
};

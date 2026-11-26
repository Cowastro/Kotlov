<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The «Материал» filter seen in «Радиаторная арматура» sits on the parent «Радиаторы» (one card, «сталь»). The radiator
 * sections already drop it (RadiatorCatalog::managed), so it leaves the filter there too (it stays in the specs).
 */
return new class extends Migration
{
    public function up(): void
    {
        $section = DB::table('categories')->where('slug', 'radiatory')->value('id');
        if ($section) {
            DB::table('attributes')->where('category_id', $section)->where('name', 'Материал')
                ->update(['in_filter' => false, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $section = DB::table('categories')->where('slug', 'radiatory')->value('id');
        DB::table('attributes')->where('category_id', $section)->where('name', 'Материал')->update(['in_filter' => true]);
    }
};

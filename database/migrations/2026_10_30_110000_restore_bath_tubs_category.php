<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «kupeli» is «Баки для воды» and the bath tubs live in «kupeli-2»: the restructure folded the
 * tubs into the tanks. The tubs get their category back, right under «Бани и сауны».
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $tubs = DB::table('categories')->where('slug', 'kupeli-2')->value('id');
        $tanks = DB::table('categories')->where('slug', 'kupeli')->value('id');
        $bath = DB::table('categories')->where('slug', 'bani-i-sauny')->value('id');
        if (! $tubs || ! $tanks || ! $bath) {
            return;
        }
        DB::table('products')->where('category_id', $tanks)->where('name', 'like', '%упел%')->update(['category_id' => $tubs, 'updated_at' => $now]);
        DB::table('categories')->where('id', $tubs)->update(['parent_id' => $bath, 'type' => 'child', 'name' => 'Купели', 'is_active' => true, 'updated_at' => $now]);
        DB::table('redirects')->where('from_url', '/kupeli-2')->delete();
    }

    public function down(): void
    {
        // The tubs keep their category.
    }
};

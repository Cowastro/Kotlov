<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Solar pump groups (Regulus, ЯSolar) leave the solar collectors for the pump groups of boiler rooms. */
return new class extends Migration
{
    public function up(): void
    {
        $ids = DB::table('categories')->whereIn('slug', ['solnechnye-kollektory', 'gruppy-bystrogo-montazha-kotelnyx'])->pluck('id', 'slug');
        if (! isset($ids['solnechnye-kollektory'], $ids['gruppy-bystrogo-montazha-kotelnyx'])) {
            return;
        }
        DB::table('products')->where('category_id', $ids['solnechnye-kollektory'])
            ->where('name', 'like', 'Насосная группа%')
            ->update(['category_id' => $ids['gruppy-bystrogo-montazha-kotelnyx'], 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Not moved back.
    }
};

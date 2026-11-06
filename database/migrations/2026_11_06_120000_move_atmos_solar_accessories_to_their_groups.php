<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** ATMOS cards of the solar section that are not collectors: expansion tanks, solar fluid, stainless pipes. */
return new class extends Migration
{
    public function up(): void
    {
        $ids = DB::table('categories')->whereIn('slug', ['solnechnye-kollektory', 'membrannye-baki', 'teplonositeli', 'gofrirovannye-truby-iz-nerzhaveyushchej-stali'])->pluck('id', 'slug');
        if (! isset($ids['solnechnye-kollektory'])) {
            return;
        }
        $moves = [
            'membrannye-baki' => ['Расширительный бак%'],
            'teplonositeli' => ['Солярная жидкость%', 'Солярн%'],
            'gofrirovannye-truby-iz-nerzhaveyushchej-stali' => ['Труба нержавеющая%'],
        ];
        foreach ($moves as $slug => $patterns) {
            if (! isset($ids[$slug])) {
                continue;
            }
            DB::table('products')->where('category_id', $ids['solnechnye-kollektory'])
                ->where(fn ($q) => collect($patterns)->each(fn (string $p) => $q->orWhere('name', 'like', $p)))
                ->update(['category_id' => $ids[$slug], 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Not moved back into the wrong group.
    }
};

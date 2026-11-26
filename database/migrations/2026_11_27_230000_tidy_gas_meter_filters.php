<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * «Номинальный расход» was set on a part of the gas meters only and repeats «Типоразмер»: it leaves the filter
 * (it stays in the product specs). «Аксессуары» moves to the end of «Серия».
 */
return new class extends Migration
{
    public function up(): void
    {
        $gas = DB::table('categories')->where('slug', 'schetchiki-gaza')->value('id');
        if (! $gas) {
            return;
        }
        DB::table('attributes')->where('category_id', $gas)->where('name', 'Номинальный расход')
            ->update(['in_filter' => false, 'updated_at' => now()]);
        if (! app()->runningUnitTests()) {
            Artisan::call('catalog:sort-boiler-automation', ['--apply' => true]);
        }
    }

    public function down(): void
    {
        $gas = DB::table('categories')->where('slug', 'schetchiki-gaza')->value('id');
        DB::table('attributes')->where('category_id', $gas)->where('name', 'Номинальный расход')->update(['in_filter' => true]);
    }
};

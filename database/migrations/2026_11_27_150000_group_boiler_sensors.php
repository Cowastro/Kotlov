<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/** «Датчики» gets its «Назначение» filter; thermometers move to «Манометры и термометры». */
return new class extends Migration
{
    public function up(): void
    {
        if (! app()->runningUnitTests() && DB::table('categories')->where('slug', 'ventilyatory-nadduva')->exists()) {
            Artisan::call('catalog:sort-boiler-automation', ['--apply' => true]);
        }
    }

    public function down(): void {}
};

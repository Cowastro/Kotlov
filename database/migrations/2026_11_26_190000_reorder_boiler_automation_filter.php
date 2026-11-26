<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/** «Назначение» lists boiler, pump and room automation first; the Джилекс «Блок автоматики» moves to pumps. */
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

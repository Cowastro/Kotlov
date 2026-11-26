<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/** «Запчасти для котлов» gets its «Назначение» filter; KIPI ROTARY burners move to pellet burners under a real name. */
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

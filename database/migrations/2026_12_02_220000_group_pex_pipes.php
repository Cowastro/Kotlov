<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * «Трубы из сшитого полиэтилена» get «Материал», «Диаметр», «Кислородный барьер» and «Длина»; underfloor clips and
 * the corrugated sleeve leave for «Водяной тёплый пол».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! app()->runningUnitTests()) {
            Artisan::call('catalog:sort-boiler-automation', ['--apply' => true]);
        }
    }

    public function down(): void {}
};

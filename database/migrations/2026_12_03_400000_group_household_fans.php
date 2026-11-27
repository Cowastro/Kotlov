<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * «Вентиляторы» get «Тип» and «Диаметр».
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

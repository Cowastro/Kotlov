<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * «Пеллетные горелки» get «Тип» and «Мощность», «Системы защиты от протечек» get «Тип», «Диаметр» and
 * «Напряжение привода» (both over their subsections too); burner parts and loose leak sensors, valves and modules move
 * into their subsections.
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

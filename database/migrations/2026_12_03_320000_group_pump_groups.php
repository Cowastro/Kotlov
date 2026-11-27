<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * «Гидрострелки, насосные группы быстрого монтажа» get «Тип», «Контур», «Насос в комплекте» and «Присоединение»; pump groups with a thermostatic valve stay there.
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

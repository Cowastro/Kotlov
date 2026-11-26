<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/** «Фитинги для ПЭ труб» get «Тип», «Способ соединения», «Резьба» and «Диаметр»; two plastic faucets leave for «Смесители». */
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

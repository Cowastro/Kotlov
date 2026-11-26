<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * «Радиаторная арматура» gets the «Тип», «Исполнение» and «Цвет» filters, «Смесительные клапаны и узлы» its «Тип»;
 * the drainage pumps and FONDITAL parts that sat among the mixing valves leave for their own sections.
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

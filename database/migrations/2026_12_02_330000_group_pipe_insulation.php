<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * «Теплоизоляция для труб» gets «Тип», «Диаметр трубы», «Толщина изоляции» and «Цвет»; ENERGOFLOOR underfloor items
 * leave for «Водяной тёплый пол».
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

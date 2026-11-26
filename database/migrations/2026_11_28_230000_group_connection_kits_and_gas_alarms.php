<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/** «Комплекты подключения» get «Тип», «Исполнение» and «Цвет»; «Сигнализаторы загазованности» get «Какой газ». */
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

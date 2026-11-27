<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/** «Предохранительная и регулирующая арматура» (with «Группы безопасности») gets «Тип» and «Диаметр (Ду)». */
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

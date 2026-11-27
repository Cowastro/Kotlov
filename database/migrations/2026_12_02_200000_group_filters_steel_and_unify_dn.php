<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * «Фильтры» get «Тип», «Диаметр (Ду)» and «Размер картриджа»; «Стальные детали трубопроводов» get «Тип», «Диаметр (Ду)»,
 * «Исполнение» and «Покрытие». Water meters label their sizes like the valves («Ду 15 (1/2")»), so «Краны и запорная
 * арматура» shows each size once.
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

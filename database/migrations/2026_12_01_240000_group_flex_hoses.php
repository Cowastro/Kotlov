<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * «Гибкая подводка для воды» gets «Назначение», «Присоединение», «Резьба», «Оплётка» and «Длина»; ZURFLEX gas hoses
 * leave for «Газовые шланги» and shower hoses for «Комплектующие для сантехники».
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

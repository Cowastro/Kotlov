<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * «Дровницы и каминные принадлежности» get «Тип»; their stove glass leaves for «Огнеупорное стекло для печей и каминов», ERMAK sauna stove parts for «Аксессуары для бани» and the vertical grill for «Мангалы».
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

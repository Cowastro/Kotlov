<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * Storm-sewer fittings leave «Внутренняя канализация» for «Наружная», SFA lifting stations leave the safety valves for
 * «Канализационные насосы сухой установки», and the stoves that sat among the bath accessories go to their sections.
 * The PP fitting types and ball valve connections list the most asked-for options first.
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

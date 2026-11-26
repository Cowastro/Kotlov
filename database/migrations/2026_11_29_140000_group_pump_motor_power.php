<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/** Multistage, end-suction/monoblock and in-line pumps get «Мощность двигателя» from their titles. */
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

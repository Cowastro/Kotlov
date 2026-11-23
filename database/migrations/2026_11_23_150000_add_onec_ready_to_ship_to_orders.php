<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** «Готов к отгрузке» in 1C: every ordered unit is to ship (register «Товары к отгрузке»). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->boolean('onec_ready_to_ship')->nullable());
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('onec_ready_to_ship'));
    }
};

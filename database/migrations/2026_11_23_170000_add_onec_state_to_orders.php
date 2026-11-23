<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The state of the order as 1C lists it (register «Состояния заказов клиентов») and the share paid and shipped. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('onec_state', 60)->nullable();
            $table->decimal('onec_paid_percent', 6, 2)->nullable();
            $table->decimal('onec_shipped_percent', 6, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['onec_state', 'onec_paid_percent', 'onec_shipped_percent']);
        });
    }
};

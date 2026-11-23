<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('carrier_provider', 20)->nullable();
            $table->string('carrier_reference', 64)->nullable();
            $table->string('carrier_display_reference', 64)->nullable();
            $table->string('carrier_stage', 20)->nullable();
            $table->string('carrier_status_text')->nullable();
            $table->timestamp('carrier_checked_at')->nullable()->index();
            $table->timestamp('carrier_polled_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['carrier_provider', 'carrier_reference', 'carrier_display_reference', 'carrier_stage', 'carrier_status_text', 'carrier_checked_at', 'carrier_polled_at']));
    }
};

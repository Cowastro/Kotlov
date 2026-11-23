<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('fulfillment_available_quantity', 12, 3)->nullable();
            $table->timestamp('fulfillment_reservation_requested_at')->nullable();
            $table->date('fulfillment_expected_date')->nullable();
        });
        Schema::table('orders', fn (Blueprint $table) => $table->text('fulfillment_customer_note')->nullable());
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn(['fulfillment_available_quantity', 'fulfillment_reservation_requested_at', 'fulfillment_expected_date']));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('fulfillment_customer_note'));
    }
};

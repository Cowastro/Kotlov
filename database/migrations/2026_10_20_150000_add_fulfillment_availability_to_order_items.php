<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->string('fulfillment_availability_status')->nullable()->after('fulfillment_purchase_price');
        });

        Schema::table('order_item_fulfillment_histories', function (Blueprint $table): void {
            $table->string('availability_status')->nullable()->after('purchase_price');
        });
    }

    public function down(): void
    {
        Schema::table('order_item_fulfillment_histories', function (Blueprint $table): void {
            $table->dropColumn('availability_status');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('fulfillment_availability_status');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreignId('warehouse_stock_id')->nullable()->after('integration_product_id')
                ->constrained('product_warehouse_stocks')->nullOnDelete();
            $table->string('supply_warehouse_name')->nullable()->after('supply_source_label');
            $table->unsignedSmallInteger('supply_delivery_days')->nullable()->after('supply_warehouse_name');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn(['supply_delivery_days', 'supply_warehouse_name']);
            $table->dropConstrainedForeignId('warehouse_stock_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_products', function (Blueprint $table) {
            if (! Schema::hasColumn('supplier_products', 'stock_quantity')) {
                $table->unsignedInteger('stock_quantity')->nullable()->after('in_stock');
            }

            if (! Schema::hasColumn('supplier_products', 'stock_status')) {
                // in_stock | low_stock | out_of_stock | preorder | discontinued | unknown
                $table->string('stock_status', 32)->nullable()->after('stock_quantity');
            }

            if (! Schema::hasColumn('supplier_products', 'stock_text')) {
                $table->string('stock_text', 255)->nullable()->after('stock_status');
            }

            if (! Schema::hasColumn('supplier_products', 'warehouse_name')) {
                $table->string('warehouse_name', 120)->nullable()->after('stock_text');
            }

            if (! Schema::hasColumn('supplier_products', 'delivery_days')) {
                $table->unsignedSmallInteger('delivery_days')->nullable()->after('warehouse_name');
            }

            if (! Schema::hasColumn('supplier_products', 'last_stock_synced_at')) {
                $table->timestamp('last_stock_synced_at')->nullable()->after('delivery_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('supplier_products', function (Blueprint $table) {
            $table->dropColumn(array_filter([
                Schema::hasColumn('supplier_products', 'stock_quantity')    ? 'stock_quantity'    : null,
                Schema::hasColumn('supplier_products', 'stock_status')      ? 'stock_status'      : null,
                Schema::hasColumn('supplier_products', 'stock_text')        ? 'stock_text'        : null,
                Schema::hasColumn('supplier_products', 'warehouse_name')    ? 'warehouse_name'    : null,
                Schema::hasColumn('supplier_products', 'delivery_days')     ? 'delivery_days'     : null,
                Schema::hasColumn('supplier_products', 'last_stock_synced_at') ? 'last_stock_synced_at' : null,
            ]));
        });
    }
};

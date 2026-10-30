<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('b2b_clients', function (Blueprint $table): void {
            $table->json('allowed_supplier_ids')->nullable()->after('allowed_integration_warehouse_ids');
        });

        Schema::table('b2b_pricing_rules', function (Blueprint $table): void {
            $table->decimal('minimum_margin_percent', 8, 4)->default(10)
                ->after('minimum_profit_byn');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreignId('supplier_product_id')->nullable()
                ->after('integration_product_id')
                ->constrained('supplier_products')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('supplier_product_id');
        });

        Schema::table('b2b_pricing_rules', function (Blueprint $table): void {
            $table->dropColumn('minimum_margin_percent');
        });

        Schema::table('b2b_clients', function (Blueprint $table): void {
            $table->dropColumn('allowed_supplier_ids');
        });
    }
};

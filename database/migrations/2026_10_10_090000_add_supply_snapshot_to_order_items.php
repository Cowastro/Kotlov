<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('supply_status')->nullable()->after('integration_product_id');
            $table->string('supply_route_label')->nullable()->after('supply_status');
            $table->foreignId('supply_supplier_id')->nullable()->after('supply_route_label')
                ->constrained('suppliers')->nullOnDelete();
            $table->foreignId('supply_integration_source_id')->nullable()->after('supply_supplier_id')
                ->constrained('integration_sources')->nullOnDelete();
            $table->string('supply_channel')->nullable()->after('supply_integration_source_id');
            $table->string('supply_supplier_name')->nullable()->after('supply_channel');
            $table->text('supply_supplier_contact')->nullable()->after('supply_supplier_name');
            $table->string('supply_source_label')->nullable()->after('supply_supplier_contact');
            $table->decimal('supply_purchase_price', 12, 2)->nullable()->after('supply_source_label');
            $table->string('supply_price_tax_mode')->nullable()->after('supply_purchase_price');
            $table->decimal('supply_vat_rate', 5, 2)->nullable()->after('supply_price_tax_mode');
            $table->decimal('supply_stock_quantity', 14, 3)->nullable()->after('supply_vat_rate');
            $table->boolean('supply_is_available')->nullable()->after('supply_stock_quantity');
            $table->unsignedSmallInteger('supply_candidate_count')->default(0)->after('supply_is_available');
            $table->timestamp('supply_captured_at')->nullable()->index()->after('supply_candidate_count');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supply_integration_source_id');
            $table->dropConstrainedForeignId('supply_supplier_id');
            $table->dropColumn([
                'supply_status',
                'supply_route_label',
                'supply_channel',
                'supply_supplier_name',
                'supply_supplier_contact',
                'supply_source_label',
                'supply_purchase_price',
                'supply_price_tax_mode',
                'supply_vat_rate',
                'supply_stock_quantity',
                'supply_is_available',
                'supply_candidate_count',
                'supply_captured_at',
            ]);
        });
    }
};

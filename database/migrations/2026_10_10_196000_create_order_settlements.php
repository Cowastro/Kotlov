<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->char('route_signature', 64);
            $table->char('basis_signature', 64);
            $table->string('currency', 3)->default('BYN');
            $table->decimal('goods_sale_total', 14, 2);
            $table->decimal('delivery_revenue', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('order_total', 14, 2);
            $table->decimal('cost_of_goods_total', 14, 2)->default(0);
            $table->decimal('supplier_payable_total', 14, 2)->default(0);
            $table->decimal('marketplace_commission_total', 14, 2)->default(0);
            $table->decimal('reseller_margin_total', 14, 2)->default(0);
            $table->decimal('platform_gross_margin_total', 14, 2)->default(0);
            $table->decimal('delivery_cost', 14, 2);
            $table->decimal('payment_fee', 14, 2);
            $table->decimal('refund_total', 14, 2);
            $table->decimal('net_profit', 14, 2);
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at');
            $table->text('note')->nullable();
            $table->json('calculation_basis');
            $table->timestamps();

            $table->unique(['order_id', 'version']);
            $table->unique(['order_id', 'basis_signature']);
            $table->index(['order_id', 'route_signature']);
        });

        Schema::create('order_settlement_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_settlement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier_name')->nullable();
            $table->text('supplier_contact')->nullable();
            $table->string('route');
            $table->string('product_name');
            $table->string('product_sku')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('sale_total', 14, 2);
            $table->decimal('purchase_price', 14, 2)->nullable();
            $table->decimal('cost_of_goods', 14, 2)->default(0);
            $table->decimal('supplier_payable', 14, 2)->default(0);
            $table->decimal('commission_rate', 7, 4)->nullable();
            $table->decimal('commission_amount', 14, 2)->default(0);
            $table->decimal('platform_margin', 14, 2);
            $table->timestamps();

            $table->index(['supplier_id', 'route']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_settlement_lines');
        Schema::dropIfExists('order_settlements');
    }
};

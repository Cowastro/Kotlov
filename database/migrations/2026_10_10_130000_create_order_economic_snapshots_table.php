<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_economic_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 32)->default('placed');
            $table->string('status', 32);
            $table->string('currency', 3)->default('BYN');
            $table->decimal('goods_sale_total', 14, 2);
            $table->decimal('delivery_revenue', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('order_total', 14, 2);
            $table->decimal('known_purchase_total', 14, 2)->default(0);
            $table->decimal('purchase_total', 14, 2)->nullable();
            $table->decimal('known_goods_margin_total', 14, 2)->default(0);
            $table->decimal('goods_margin_total', 14, 2)->nullable();
            $table->decimal('goods_margin_percent', 8, 2)->nullable();
            $table->decimal('delivery_cost', 14, 2)->nullable();
            $table->decimal('payment_fee', 14, 2)->nullable();
            $table->decimal('marketplace_commission', 14, 2)->nullable();
            $table->decimal('net_profit', 14, 2)->nullable();
            $table->unsignedInteger('items_count')->default(0);
            $table->unsignedInteger('priced_items_count')->default(0);
            $table->unsignedInteger('missing_purchase_price_count')->default(0);
            $table->unsignedInteger('missing_supplier_count')->default(0);
            $table->json('tax_modes')->nullable();
            $table->json('unknown_costs')->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->unique(['order_id', 'kind']);
            $table->index(['status', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_economic_snapshots');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('b2b_order_items', function (Blueprint $table): void {
            // integration:{id} | warehouse:{id} | supplier:{id} — the stock pool the line is served from.
            $table->string('stock_key', 64)->nullable()->after('warehouse_stock_id')->index();
            $table->decimal('stock_free_at_check', 12, 3)->nullable()->after('confirmed_quantity');
            $table->string('availability_note')->nullable()->after('stock_free_at_check');
        });

        Schema::create('b2b_order_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('b2b_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('b2b_order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('stock_key', 64)->index();
            $table->foreignId('integration_product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_stock_id')->nullable()->constrained('product_warehouse_stocks')->nullOnDelete();
            $table->foreignId('supplier_product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('warehouse_name')->nullable();
            $table->decimal('quantity', 12, 3);
            // active → shipped (left our hands) | released (cancelled or rechecked)
            $table->string('status', 16)->default('active')->index();
            $table->foreignId('reserved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['stock_key', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('b2b_order_reservations');
        Schema::table('b2b_order_items', function (Blueprint $table): void {
            $table->dropIndex(['stock_key']);
            $table->dropColumn(['stock_key', 'stock_free_at_check', 'availability_note']);
        });
    }
};

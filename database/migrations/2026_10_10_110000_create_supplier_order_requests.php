<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('fulfillment_purchase_price', 12, 2)->nullable()->after('fulfillment_supplier_contact');
        });

        Schema::table('order_item_fulfillment_histories', function (Blueprint $table) {
            $table->decimal('purchase_price', 12, 2)->nullable()->after('supplier_contact');
        });

        Schema::create('supplier_order_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number')->unique();
            $table->string('route');
            $table->string('status')->default('draft')->index();
            $table->string('supplier_name');
            $table->text('supplier_contact')->nullable();
            $table->unsignedSmallInteger('item_count')->default(0);
            $table->decimal('purchase_total', 12, 2)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'supplier_id', 'route'], 'supplier_order_requests_route_unique');
        });

        Schema::create('supplier_order_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_order_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->string('product_name');
            $table->string('product_sku')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->decimal('purchase_total', 12, 2)->nullable();
            $table->timestamps();

            $table->unique('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_order_request_items');
        Schema::dropIfExists('supplier_order_requests');

        Schema::table('order_item_fulfillment_histories', function (Blueprint $table) {
            $table->dropColumn('purchase_price');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('fulfillment_purchase_price');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('can_manage_b2b_orders')->default(false)->after('is_crm_user')->index();
        });

        Schema::create('b2b_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('b2b_client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('commercial_document_id')->nullable()->constrained('commercial_documents')->nullOnDelete();
            $table->foreignId('legacy_order_id')->nullable()->unique()->constrained('orders')->nullOnDelete();
            $table->string('number')->unique();
            $table->string('status', 40)->default('new')->index();
            $table->string('payment_status', 32)->default('pending')->index();
            $table->string('payment_type', 40)->default('invoice');
            $table->string('delivery_type', 40)->default('b2b_delivery');
            $table->string('delivery_region')->nullable();
            $table->string('delivery_city')->nullable();
            $table->text('delivery_address')->nullable();
            $table->decimal('delivery_price', 14, 2)->default(0);
            $table->string('contact_name');
            $table->string('contact_phone', 64);
            $table->string('contact_email')->nullable();
            $table->string('company_name');
            $table->string('company_unp', 32)->nullable();
            $table->text('company_address')->nullable();
            $table->string('company_email')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->json('commercial_terms_snapshot')->nullable();
            $table->text('client_comment')->nullable();
            $table->text('manager_note')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('availability_confirmed_at')->nullable();
            $table->timestamp('client_confirmed_at')->nullable();
            $table->timestamp('invoice_notified_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['b2b_client_id', 'created_at']);
            $table->index(['assigned_manager_id', 'status']);
        });

        Schema::create('b2b_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('b2b_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('integration_product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_stock_id')->nullable()->constrained('product_warehouse_stocks')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('product_sku')->nullable();
            $table->decimal('price', 14, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('total', 14, 2);
            $table->decimal('base_price', 14, 2)->nullable();
            $table->decimal('purchase_price', 14, 2)->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('price_level_code')->nullable();
            $table->decimal('discount_percent', 8, 3)->nullable();
            $table->string('warehouse_name')->nullable();
            $table->unsignedSmallInteger('delivery_days')->nullable();
            $table->string('availability_status', 32)->default('pending')->index();
            $table->unsignedInteger('confirmed_quantity')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('manager_note')->nullable();
            $table->timestamps();
        });

        Schema::create('b2b_order_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('b2b_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 64)->index();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->text('comment')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('b2b_order_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('b2b_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('visible_to_client')->default(false);
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('b2b_order_comments');
        Schema::dropIfExists('b2b_order_events');
        Schema::dropIfExists('b2b_order_items');
        Schema::dropIfExists('b2b_orders');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('can_manage_b2b_orders');
        });
    }
};

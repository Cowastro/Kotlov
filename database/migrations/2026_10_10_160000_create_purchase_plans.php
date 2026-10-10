<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->string('status', 24)->default('draft');
            $table->foreignId('integration_source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_code');
            $table->string('source_name');
            $table->unsignedSmallInteger('period_days');
            $table->unsignedInteger('items_count');
            $table->decimal('total_quantity', 14, 3);
            $table->decimal('known_purchase_total', 14, 2)->default(0);
            $table->decimal('purchase_total', 14, 2)->nullable();
            $table->unsignedInteger('missing_price_count')->default(0);
            $table->string('snapshot_hash', 64)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('purchase_plan_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('integration_product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_sku')->nullable();
            $table->string('product_name');
            $table->decimal('current_stock', 14, 3);
            $table->unsignedInteger('target_stock');
            $table->unsignedInteger('recommended_quantity');
            $table->unsignedInteger('planned_quantity');
            $table->decimal('unit_purchase_price', 12, 2)->nullable();
            $table->decimal('purchase_total', 14, 2)->nullable();
            $table->string('price_tax_mode')->nullable();
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->timestamp('stock_confirmed_at')->nullable();
            $table->text('explanation');
            $table->timestamps();

            $table->unique(['purchase_plan_id', 'product_id']);
        });

        Schema::create('purchase_plan_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status_from', 24)->nullable();
            $table->string('status_to', 24);
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_plan_status_histories');
        Schema::dropIfExists('purchase_plan_items');
        Schema::dropIfExists('purchase_plans');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('command')->index();
            $table->string('status', 24)->default('running')->index();
            $table->timestamp('started_at')->index();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->integer('exit_code')->nullable();
            $table->unsignedInteger('changes_count')->default(0);
            $table->unsignedInteger('price_changes_count')->default(0);
            $table->unsignedInteger('stock_changes_count')->default(0);
            $table->string('supplier_names')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_sync_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_sync_run_id')
                ->constrained('supplier_sync_runs')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('supplier_id')->nullable()->index();
            $table->unsignedBigInteger('supplier_product_id')->nullable()->index();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->string('supplier_name')->nullable()->index();
            $table->string('supplier_article')->nullable()->index();
            $table->string('product_sku')->nullable()->index();
            $table->string('product_name')->nullable();
            $table->json('change_flags');
            $table->decimal('supplier_price_before', 14, 2)->nullable();
            $table->decimal('supplier_price_after', 14, 2)->nullable();
            $table->decimal('retail_price_before', 14, 2)->nullable();
            $table->decimal('retail_price_after', 14, 2)->nullable();
            $table->boolean('in_stock_before')->nullable();
            $table->boolean('in_stock_after')->nullable();
            $table->integer('stock_quantity_before')->nullable();
            $table->integer('stock_quantity_after')->nullable();
            $table->string('stock_status_before')->nullable();
            $table->string('stock_status_after')->nullable();
            $table->string('availability_before')->nullable();
            $table->string('availability_after')->nullable();
            $table->timestamp('created_at')->index();
        });

        // This table is the comparison baseline. IDs deliberately have no
        // foreign keys so removal of a link can itself be recorded.
        Schema::create('supplier_sync_states', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_product_id')->unique();
            $table->unsignedBigInteger('supplier_id')->nullable()->index();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->string('supplier_name')->nullable();
            $table->string('supplier_article')->nullable();
            $table->string('product_sku')->nullable();
            $table->string('product_name')->nullable();
            $table->decimal('supplier_price', 14, 2)->nullable();
            $table->decimal('retail_price', 14, 2)->nullable();
            $table->boolean('in_stock')->nullable();
            $table->integer('stock_quantity')->nullable();
            $table->string('stock_status')->nullable();
            $table->string('availability_status')->nullable();
            $table->timestamp('observed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_sync_changes');
        Schema::dropIfExists('supplier_sync_states');
        Schema::dropIfExists('supplier_sync_runs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_sources', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('driver')->default('commerceml');
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('create_products')->default(false);
            $table->boolean('update_prices')->default(false);
            $table->boolean('update_stock')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('integration_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id');
            $table->string('external_sku')->nullable()->index();
            $table->string('barcode')->nullable()->index();
            $table->string('name')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('stock_quantity', 12, 3)->nullable();
            $table->string('match_status')->default('unmatched')->index();
            $table->string('match_method')->nullable();
            $table->decimal('match_confidence', 5, 4)->nullable();
            $table->json('candidates')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['integration_source_id', 'external_id'], 'integration_product_external_unique');
            $table->index(['integration_source_id', 'product_id'], 'integration_product_link_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_products');
        Schema::dropIfExists('integration_sources');
    }
};

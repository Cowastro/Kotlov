<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_product_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_code');           // e.g. 'berezka'
            $table->string('supplier_article');        // supplier's own code, e.g. '001', '026'
            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();
            $table->string('product_sku')->nullable();  // copy of products.sku at mapping time
            $table->string('supplier_name')->nullable(); // product name from supplier
            $table->string('confidence')->nullable();    // 'high', 'medium', 'low', 'manual'
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['supplier_code', 'supplier_article']);
            $table->index('product_id');
            $table->index('product_sku');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_product_mappings');
    }
};

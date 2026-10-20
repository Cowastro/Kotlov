<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_warehouse_stocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('warehouse_code', 80);
            $table->string('warehouse_name', 120);
            $table->string('external_article')->nullable()->index();
            $table->string('source_product_name')->nullable();
            $table->decimal('total_quantity', 12, 3)->default(0);
            $table->decimal('reserved_quantity', 12, 3)->default(0);
            $table->decimal('shipping_quantity', 12, 3)->default(0);
            $table->decimal('available_quantity', 12, 3)->default(0);
            $table->unsignedSmallInteger('delivery_days')->default(0);
            $table->date('as_of_date')->nullable();
            $table->string('source_file')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'supplier_id', 'warehouse_code'], 'product_supplier_warehouse_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_warehouse_stocks');
    }
};

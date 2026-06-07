<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_price_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('import_id')->constrained('supplier_price_imports')->cascadeOnDelete();
            $table->string('article');               // артикул поставщика
            $table->string('name');                  // название в прайсе поставщика
            $table->decimal('price', 12, 2);         // цена в валюте поставщика
            $table->decimal('price_byn', 12, 2)->nullable(); // пересчитанная в BYN
            $table->boolean('in_stock')->default(true);
            $table->integer('stock_qty')->nullable();
            $table->json('raw')->nullable();         // вся строка прайса как есть

            // Результат матчинга
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_sku')->nullable();
            $table->enum('match_status', ['unmatched', 'matched', 'ambiguous', 'ignored'])->default('unmatched');
            $table->enum('match_method', ['mapping', 'sku', 'name', 'manual'])->nullable();

            $table->index(['supplier_id', 'article']);
            $table->index('match_status');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_price_items');
    }
};

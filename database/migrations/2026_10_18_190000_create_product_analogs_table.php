<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_analogs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('analog_product_id')->constrained('products')->cascadeOnDelete();
            $table->string('source', 80)->nullable();
            $table->string('match_method', 80)->nullable();
            $table->string('source_model')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'analog_product_id']);
            $table->index(['analog_product_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_analogs');
    }
};

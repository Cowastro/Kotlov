<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->longText('items');
            $table->text('meta')->nullable();
            $table->timestamps();
        });
        Schema::create('customer_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->text('note')->nullable();
            $table->longText('items');
            $table->text('meta')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_selections');
        Schema::dropIfExists('customer_carts');
    }
};

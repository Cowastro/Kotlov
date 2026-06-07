<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_price_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('filename');
            $table->integer('total_rows')->default(0);
            $table->integer('matched')->default(0);
            $table->integer('unmatched')->default(0);
            $table->integer('updated')->default(0);
            $table->integer('skipped')->default(0);  // цена не изменилась
            $table->enum('status', ['pending', 'processing', 'done', 'failed'])->default('pending');
            $table->text('error')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_price_imports');
    }
};

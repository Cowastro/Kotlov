<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_ai_selection_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->char('session_hash', 64)->index();
            $table->text('intent');
            $table->string('category', 40)->index();
            $table->json('answers')->nullable();
            $table->json('result_product_ids')->nullable();
            $table->string('status', 30)->default('completed')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_ai_selection_runs');
    }
};

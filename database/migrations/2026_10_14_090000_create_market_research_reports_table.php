<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_research_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('batch_code');
            $table->text('review_reason');
            $table->text('summary')->nullable();
            $table->text('verdict')->nullable();
            $table->string('confidence', 24)->default('low');
            $table->string('decision', 32);
            $table->text('decision_reason')->nullable();
            $table->decimal('price_before', 14, 2)->nullable();
            $table->decimal('recommended_price', 14, 2)->nullable();
            $table->decimal('price_after', 14, 2)->nullable();
            $table->boolean('requires_price_confirmation')->default(true);
            $table->json('search_queries')->nullable();
            $table->json('rejected_candidates')->nullable();
            $table->json('excluded_offers')->nullable();
            $table->json('research_payload')->nullable();
            $table->json('observations_snapshot')->nullable();
            $table->string('research_model')->nullable();
            $table->timestamp('researched_at')->nullable();
            $table->timestamp('applied_at');
            $table->timestamps();

            $table->unique(['batch_code', 'product_id'], 'market_research_batch_product_unique');
            $table->index(['product_id', 'applied_at'], 'market_research_product_applied_idx');
            $table->index(['decision', 'applied_at'], 'market_research_decision_applied_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_research_reports');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_price_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('kind', 32);
            $table->string('collection_method', 32)->default('manual');
            $table->string('base_url')->nullable();
            $table->string('currency', 3)->default('BYN');
            $table->string('region')->default('Беларусь');
            $table->unsignedSmallInteger('freshness_hours')->default(48);
            $table->decimal('minimum_match_confidence', 5, 4)->default(0.8500);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'kind']);
        });

        Schema::create('market_price_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('market_price_source_id')->constrained()->cascadeOnDelete();
            $table->string('fingerprint', 64)->unique();
            $table->text('url');
            $table->string('url_hash', 64);
            $table->string('external_name')->nullable();
            $table->string('external_sku')->nullable();
            $table->string('model')->nullable();
            $table->string('package')->nullable();
            $table->string('unit', 32)->nullable();
            $table->decimal('observed_price', 14, 2);
            $table->string('currency', 3)->default('BYN');
            $table->decimal('exchange_rate_to_byn', 18, 6)->default(1);
            $table->decimal('price_byn', 14, 2);
            $table->boolean('price_includes_vat')->nullable();
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->decimal('delivery_price_byn', 14, 2)->nullable();
            $table->string('region')->default('Беларусь');
            $table->string('availability_status', 32)->default('unknown');
            $table->string('match_method', 32)->default('manual');
            $table->decimal('match_confidence', 5, 4)->default(0);
            $table->boolean('is_confirmed')->default(false);
            $table->boolean('is_comparable')->default(false);
            $table->json('validation_flags')->nullable();
            $table->timestamp('observed_at');
            $table->timestamps();

            $table->index(['product_id', 'observed_at'], 'market_obs_product_seen_idx');
            $table->index(['market_price_source_id', 'observed_at'], 'market_obs_source_seen_idx');
            $table->index(['product_id', 'is_confirmed', 'is_comparable'], 'market_obs_product_quality_idx');
            $table->index(['url_hash', 'observed_at'], 'market_obs_url_seen_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_price_observations');
        Schema::dropIfExists('market_price_sources');
    }
};

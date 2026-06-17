<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail for supplier:enrich-images downloads. Lets us explain WHY two
 * products share a photo (same source card) and review trust/provider later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('image_enrichment_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('image_path')->nullable();
            $table->text('image_url')->nullable();
            $table->text('source_url')->nullable();
            $table->string('provider', 32)->nullable();
            $table->string('trusted_level', 16)->nullable();
            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_enrichment_logs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remembers products for which supplier:import-attributes-rusklimat found no
 * usable specs (page not found / b2b JS page / no mapped keys), so repeated
 * batch runs don't re-scrape hopeless products. Entries older than the TTL
 * (30 days) are retried.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_import_failures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->timestamp('attempted_at')->nullable();
            $table->timestamps();

            $table->unique('product_id');
            $table->index('attempted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_import_failures');
    }
};

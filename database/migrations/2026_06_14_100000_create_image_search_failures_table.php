<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remembers products for which supplier:enrich-images found no usable photo,
 * so repeated batch runs don't waste search-API credits re-querying hopeless
 * SKUs. Entries older than the command's TTL (30 days) are retried.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('image_search_failures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->timestamp('searched_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'supplier_id']);
            $table->index('searched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('image_search_failures');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Retail and wholesale of the 1C items (with VAT) and the 1C retail price of the cards — one price everywhere. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onec_items', function (Blueprint $table): void {
            $table->decimal('price_retail', 14, 2)->nullable();
            $table->decimal('price_wholesale', 14, 2)->nullable();
            $table->timestamp('prices_synced_at')->nullable();
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('onec_retail_byn', 12, 2)->nullable();
            $table->timestamp('onec_retail_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('onec_items', fn (Blueprint $table) => $table->dropColumn(['price_retail', 'price_wholesale', 'prices_synced_at']));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['onec_retail_byn', 'onec_retail_at']));
    }
};

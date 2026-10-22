<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Time-limited supplier promotions: the product keeps its regular price in
 * price_old while the campaign runs and catalog:expire-sale-campaigns
 * restores it after sale_ends_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('sale_campaign', 64)->nullable()->index()->after('is_sale');
            $table->timestamp('sale_ends_at')->nullable()->after('sale_campaign');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['sale_campaign']);
            $table->dropColumn(['sale_campaign', 'sale_ends_at']);
        });
    }
};

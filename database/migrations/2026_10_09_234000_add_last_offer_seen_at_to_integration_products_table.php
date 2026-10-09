<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_products', function (Blueprint $table) {
            $table->timestamp('last_offer_seen_at')->nullable()->after('last_seen_at');
            $table->index(['integration_source_id', 'last_offer_seen_at'], 'integration_products_offer_seen_index');
        });
    }

    public function down(): void
    {
        Schema::table('integration_products', function (Blueprint $table) {
            $table->dropIndex('integration_products_offer_seen_index');
            $table->dropColumn('last_offer_seen_at');
        });
    }
};

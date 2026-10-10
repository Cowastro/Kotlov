<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_products', function (Blueprint $table) {
            $table->timestamp('stock_confirmed_at')->nullable()->after('last_offer_seen_at');
            $table->index(['integration_source_id', 'stock_confirmed_at'], 'integration_products_stock_confirmed_index');
        });

        DB::table('integration_products')
            ->whereNotNull('stock_quantity')
            ->whereNotNull('last_offer_seen_at')
            ->update(['stock_confirmed_at' => DB::raw('last_offer_seen_at')]);
    }

    public function down(): void
    {
        Schema::table('integration_products', function (Blueprint $table) {
            $table->dropIndex('integration_products_stock_confirmed_index');
            $table->dropColumn('stock_confirmed_at');
        });
    }
};

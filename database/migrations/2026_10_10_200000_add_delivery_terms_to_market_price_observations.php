<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_price_observations', function (Blueprint $table): void {
            $table->text('delivery_terms')->nullable()->after('delivery_price_byn');
        });
    }

    public function down(): void
    {
        Schema::table('market_price_observations', function (Blueprint $table): void {
            $table->dropColumn('delivery_terms');
        });
    }
};

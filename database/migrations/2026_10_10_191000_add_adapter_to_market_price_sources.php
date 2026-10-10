<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_price_sources', function (Blueprint $table): void {
            $table->string('adapter_key', 64)->nullable()->after('collection_method')->index();
            $table->json('collection_settings')->nullable()->after('adapter_key');
        });
    }

    public function down(): void
    {
        Schema::table('market_price_sources', function (Blueprint $table): void {
            $table->dropIndex(['adapter_key']);
            $table->dropColumn(['adapter_key', 'collection_settings']);
        });
    }
};

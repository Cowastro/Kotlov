<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->json('shipping_parcel')->nullable();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->json('evropochta_quote')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('evropochta_quote');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('shipping_parcel');
        });
    }
};

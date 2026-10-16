<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_documents', function (Blueprint $table): void {
            $table->boolean('delivery_to_client')->default(false)->after('delivery_price');
            $table->boolean('allow_unconfirmed_purchase_price')->default(false)->after('delivery_to_client');
        });
    }

    public function down(): void
    {
        Schema::table('commercial_documents', function (Blueprint $table): void {
            $table->dropColumn(['delivery_to_client', 'allow_unconfirmed_purchase_price']);
        });
    }
};

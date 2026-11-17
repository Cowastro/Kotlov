<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_products', fn (Blueprint $table) => $table->json('fulfillment_details')->nullable());
    }

    public function down(): void
    {
        Schema::table('supplier_products', fn (Blueprint $table) => $table->dropColumn('fulfillment_details'));
    }
};

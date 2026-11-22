<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Stock of 1C items on the selling warehouse: in stock and promised to orders (the reserve). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onec_items', function (Blueprint $table): void {
            $table->decimal('stock_on_hand', 14, 3)->default(0);
            $table->decimal('stock_reserved', 14, 3)->default(0);
            $table->timestamp('stock_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('onec_items', function (Blueprint $table): void {
            $table->dropColumn(['stock_on_hand', 'stock_reserved', 'stock_synced_at']);
        });
    }
};

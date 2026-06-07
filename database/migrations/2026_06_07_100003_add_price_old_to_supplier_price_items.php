<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('supplier_price_items', 'price_old')) {
            Schema::table('supplier_price_items', function (Blueprint $table) {
                $table->decimal('price_old', 12, 2)->nullable()->after('price_byn');
            });
        }
    }

    public function down(): void
    {
        Schema::table('supplier_price_items', function (Blueprint $table) {
            $table->dropColumn('price_old');
        });
    }
};

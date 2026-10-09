<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('pricing_type')->default('retail')->after('total');
            $table->string('price_tax_mode')->nullable()->after('pricing_type');
            $table->foreignId('integration_product_id')->nullable()->after('price_tax_mode')
                ->constrained('integration_products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('integration_product_id');
            $table->dropColumn(['pricing_type', 'price_tax_mode']);
        });
    }
};

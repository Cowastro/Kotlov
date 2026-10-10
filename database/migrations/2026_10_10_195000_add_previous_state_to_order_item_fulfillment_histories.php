<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_item_fulfillment_histories', function (Blueprint $table): void {
            $table->string('previous_route')->nullable()->after('user_id');
            $table->foreignId('previous_supplier_id')->nullable()->after('previous_route')
                ->constrained('suppliers')->nullOnDelete();
            $table->string('previous_supplier_name')->nullable()->after('previous_supplier_id');
            $table->decimal('previous_purchase_price', 12, 2)->nullable()->after('previous_supplier_name');
        });
    }

    public function down(): void
    {
        Schema::table('order_item_fulfillment_histories', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('previous_supplier_id');
            $table->dropColumn([
                'previous_route',
                'previous_supplier_name',
                'previous_purchase_price',
            ]);
        });
    }
};

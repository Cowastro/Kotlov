<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('fulfillment_route')->nullable()->after('supply_captured_at');
            $table->foreignId('fulfillment_supplier_id')->nullable()->after('fulfillment_route')->constrained('suppliers')->nullOnDelete();
            $table->string('fulfillment_supplier_name')->nullable()->after('fulfillment_supplier_id');
            $table->text('fulfillment_supplier_contact')->nullable()->after('fulfillment_supplier_name');
            $table->foreignId('fulfillment_confirmed_by')->nullable()->after('fulfillment_supplier_contact')->constrained('users')->nullOnDelete();
            $table->timestamp('fulfillment_confirmed_at')->nullable()->index()->after('fulfillment_confirmed_by');
            $table->text('fulfillment_note')->nullable()->after('fulfillment_confirmed_at');
        });

        Schema::create('order_item_fulfillment_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('route');
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier_name')->nullable();
            $table->text('supplier_contact')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_fulfillment_histories');

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fulfillment_confirmed_by');
            $table->dropConstrainedForeignId('fulfillment_supplier_id');
            $table->dropColumn([
                'fulfillment_route',
                'fulfillment_supplier_name',
                'fulfillment_supplier_contact',
                'fulfillment_confirmed_at',
                'fulfillment_note',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «Заказ поставщику» in 1C for the lines of a customer order bought from a supplier: one per order and supplier.
 * Schemes now say which 1C document they make (a customer order or a supplier order).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onec_supplier_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('profile_id')->nullable();
            $table->string('status', 20)->default('queued');
            $table->string('counterparty_key', 36)->nullable();
            $table->decimal('total', 14, 2)->nullable();
            $table->string('onec_order_guid', 36)->nullable()->index();
            $table->string('onec_order_number', 50)->nullable();
            $table->string('onec_doc_status', 40)->nullable();
            $table->boolean('onec_posted')->nullable();
            $table->boolean('onec_deleted')->default(false);
            $table->timestamp('onec_checked_at')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'supplier_id']);
        });

        Schema::table('onec_order_profiles', function (Blueprint $table): void {
            $table->string('document', 20)->default('customer')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('onec_order_profiles', function (Blueprint $table): void {
            $table->dropColumn('document');
        });
        Schema::dropIfExists('onec_supplier_orders');
    }
};

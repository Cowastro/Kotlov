<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_purchase_price_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_type')->default('supplier_invoice');
            $table->string('supplier_name')->nullable();
            $table->string('document_number')->nullable();
            $table->date('document_date');
            $table->date('valid_until')->nullable();
            $table->decimal('source_price', 14, 2);
            $table->string('currency', 3)->default('BYN');
            $table->decimal('currency_rate', 14, 6)->nullable();
            $table->string('price_tax_mode')->default('inclusive');
            $table->decimal('vat_rate', 6, 2)->default(20);
            $table->decimal('price_byn', 14, 2);
            $table->string('attachment_path')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('confirmed_at');
            $table->timestamps();

            $table->index(['product_id', 'is_active', 'valid_until'], 'purchase_price_sources_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_purchase_price_sources');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->string('type', 24)->default('quote')->index();
            $table->string('status', 24)->default('draft')->index();
            $table->foreignId('source_document_id')->nullable()->constrained('commercial_documents')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('crm_mail_thread_id')->nullable()->constrained('crm_mail_threads')->nullOnDelete();
            $table->string('client_name')->nullable();
            $table->string('client_company')->nullable();
            $table->string('client_email')->nullable();
            $table->string('client_phone', 64)->nullable();
            $table->string('client_unp', 32)->nullable();
            $table->text('client_address')->nullable();
            $table->date('issued_at');
            $table->date('valid_until')->nullable();
            $table->string('currency', 8)->default('BYN');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('delivery_price', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(20);
            $table->string('payment_terms')->nullable();
            $table->string('delivery_terms')->nullable();
            $table->text('notes')->nullable();
            $table->json('seller_snapshot');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('commercial_document_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('commercial_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('unit', 32)->default('шт.');
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('discount_percent', 7, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(20);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_document_items');
        Schema::dropIfExists('commercial_documents');
    }
};

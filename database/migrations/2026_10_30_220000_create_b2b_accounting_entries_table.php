<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('b2b_accounting_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('b2b_client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_source_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('commercial_document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_key');
            $table->string('entry_type', 24);
            $table->dateTime('occurred_at');
            $table->string('document_number')->nullable();
            $table->string('description')->nullable();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->string('currency', 3)->default('BYN');
            $table->timestamp('imported_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['integration_source_id', 'external_key', 'entry_type'], 'b2b_accounting_source_entry_unique');
            $table->index(['b2b_client_id', 'occurred_at'], 'b2b_accounting_client_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('b2b_accounting_entries');
    }
};

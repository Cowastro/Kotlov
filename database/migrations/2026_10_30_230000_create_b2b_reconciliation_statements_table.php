<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('b2b_reconciliation_statements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('b2b_client_id')->constrained()->cascadeOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->string('statement_hash', 64);
            $table->string('source', 24)->default('onec');
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->decimal('period_debit', 14, 2)->default(0);
            $table->decimal('period_credit', 14, 2)->default(0);
            $table->decimal('closing_balance', 14, 2)->default(0);
            $table->string('currency', 3)->default('BYN');
            $table->json('entries_snapshot');
            $table->string('status', 32)->default('client_confirmed');
            $table->foreignId('client_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('client_confirmed_at')->nullable();
            $table->foreignId('manager_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('manager_confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['b2b_client_id', 'date_from', 'date_to', 'statement_hash'],
                'b2b_reconciliation_statement_version_unique',
            );
            $table->index(['b2b_client_id', 'status'], 'b2b_reconciliation_client_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('b2b_reconciliation_statements');
    }
};

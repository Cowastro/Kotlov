<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A partner from the existing base is invited by a personal link, fills in the
        // questionnaire, and a manager turns it into a B2B client with sales points.
        Schema::create('partner_onboardings', function (Blueprint $table): void {
            $table->id();
            $table->string('token', 64)->unique();
            $table->string('email');
            $table->string('company_name')->nullable();
            $table->string('unp', 50)->nullable();
            $table->foreignId('crm_counterparty_id')->nullable()->constrained('crm_counterparties')->nullOnDelete();
            $table->string('status', 20)->default('invited')->index();
            $table->json('data')->nullable();
            $table->foreignId('b2b_client_id')->nullable()->constrained('b2b_clients')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_note')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_onboardings');
    }
};

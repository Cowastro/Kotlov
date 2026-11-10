<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Mailing lists of partners: who gets a newsletter, with every contact channel gathered from CRM, B2B and suppliers. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_mailing_lists', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_mailing_list_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mailing_list_id')->constrained('crm_mailing_lists')->cascadeOnDelete();
            $table->string('query')->nullable();
            $table->foreignId('counterparty_id')->nullable()->constrained('crm_counterparties')->nullOnDelete();
            $table->foreignId('b2b_client_id')->nullable()->constrained('b2b_clients')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['mailing_list_id', 'counterparty_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_mailing_list_members');
        Schema::dropIfExists('crm_mailing_lists');
    }
};

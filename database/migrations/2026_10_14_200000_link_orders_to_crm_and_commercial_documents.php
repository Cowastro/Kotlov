<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('commercial_document_id')->nullable()->unique()->after('user_id')
                ->constrained('commercial_documents')->nullOnDelete();
            $table->foreignId('crm_counterparty_id')->nullable()->after('commercial_document_id')
                ->constrained('crm_counterparties')->nullOnDelete();
            $table->foreignId('crm_contact_id')->nullable()->after('crm_counterparty_id')
                ->constrained('crm_contacts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('crm_contact_id');
            $table->dropConstrainedForeignId('crm_counterparty_id');
            $table->dropConstrainedForeignId('commercial_document_id');
        });
    }
};

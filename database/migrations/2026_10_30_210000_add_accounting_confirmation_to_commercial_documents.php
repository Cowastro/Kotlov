<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_documents', function (Blueprint $table): void {
            $table->foreignId('accounting_source_id')->nullable()->after('crm_contact_id')
                ->constrained('integration_sources')->nullOnDelete();
            $table->string('accounting_external_id')->nullable()->after('accounting_source_id');
            $table->string('accounting_number')->nullable()->after('accounting_external_id');
            $table->string('accounting_status')->nullable()->after('accounting_number');
            $table->string('accounting_payment_status', 32)->nullable()->after('accounting_status');
            $table->timestamp('accounting_confirmed_at')->nullable()->after('accounting_payment_status');

            $table->index(['accounting_source_id', 'accounting_external_id'], 'commercial_documents_accounting_external_idx');
        });
    }

    public function down(): void
    {
        Schema::table('commercial_documents', function (Blueprint $table): void {
            $table->dropIndex('commercial_documents_accounting_external_idx');
            $table->dropConstrainedForeignId('accounting_source_id');
            $table->dropColumn([
                'accounting_external_id',
                'accounting_number',
                'accounting_status',
                'accounting_payment_status',
                'accounting_confirmed_at',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_counterparties', function (Blueprint $table): void {
            $table->string('bank_name')->nullable()->after('actual_address');
            $table->string('bank_bic', 32)->nullable()->after('bank_name');
            $table->string('bank_iban', 64)->nullable()->after('bank_bic');
            $table->string('authorized_representative')->nullable()->after('bank_iban');
            $table->string('representative_position')->nullable()->after('authorized_representative');
            $table->string('authority_basis')->nullable()->after('representative_position');
        });
    }

    public function down(): void
    {
        Schema::table('crm_counterparties', function (Blueprint $table): void {
            $table->dropColumn([
                'bank_name', 'bank_bic', 'bank_iban', 'authorized_representative',
                'representative_position', 'authority_basis',
            ]);
        });
    }
};

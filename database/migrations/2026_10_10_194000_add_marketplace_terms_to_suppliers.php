<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->decimal('marketplace_commission_rate', 7, 4)->nullable()->after('currency_rate');
            $table->unsignedSmallInteger('settlement_terms_days')->default(14)->after('marketplace_commission_rate');
            $table->text('settlement_notes')->nullable()->after('settlement_terms_days');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropColumn([
                'marketplace_commission_rate',
                'settlement_terms_days',
                'settlement_notes',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_counterparties', function (Blueprint $table): void {
            $table->decimal('commercial_discount_percent', 7, 2)->default(0)->after('actual_address');
            $table->string('commercial_payment_terms')->nullable()->after('commercial_discount_percent');
            $table->string('commercial_delivery_terms')->nullable()->after('commercial_payment_terms');
            $table->string('commercial_currency', 8)->default('BYN')->after('commercial_delivery_terms');
            $table->text('commercial_note')->nullable()->after('commercial_currency');
        });
    }

    public function down(): void
    {
        Schema::table('crm_counterparties', function (Blueprint $table): void {
            $table->dropColumn([
                'commercial_discount_percent',
                'commercial_payment_terms',
                'commercial_delivery_terms',
                'commercial_currency',
                'commercial_note',
            ]);
        });
    }
};

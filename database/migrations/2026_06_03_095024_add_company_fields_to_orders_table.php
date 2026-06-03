<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Реквизиты для оплаты по счёту (payment_type = invoice)
            $table->string('company_name')->nullable()->after('customer_email');
            $table->string('company_unp')->nullable()->after('company_name');      // УНП / ИНН
            $table->string('company_address')->nullable()->after('company_unp');
            $table->string('company_email')->nullable()->after('company_address');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['company_name', 'company_unp', 'company_address', 'company_email']);
        });
    }
};

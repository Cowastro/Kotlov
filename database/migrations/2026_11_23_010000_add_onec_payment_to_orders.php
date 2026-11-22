<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Payment of the order as 1C has it: its settlement object, what is to be paid and what is paid. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('onec_settlement_key', 36)->nullable();
            $table->decimal('onec_to_pay', 12, 2)->nullable();
            $table->decimal('onec_paid', 12, 2)->nullable();
            $table->timestamp('onec_paid_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['onec_settlement_key', 'onec_to_pay', 'onec_paid', 'onec_paid_checked_at']);
        });
    }
};

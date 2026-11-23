<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Settlement objects were taken from the records of the order and could be a contract shared by every order: found again. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')->whereNotNull('onec_settlement_key')->where(fn ($q) => $q->whereNull('payment_status')->orWhere('payment_status', '!=', 'paid'))
            ->update(['onec_settlement_key' => null, 'onec_to_pay' => null, 'onec_paid' => null, 'onec_shipped' => null]);
    }

    public function down(): void {}
};

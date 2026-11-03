<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('b2b_orders', function (Blueprint $table): void {
            $table->timestamp('paid_at')->nullable()->after('client_confirmed_at')->index();
        });

        // Orders paid before this column: the moment from the history, else the last update.
        DB::table('b2b_orders')->where('payment_status', 'paid')->orderBy('id')
            ->each(function (object $order): void {
                $paidAt = DB::table('b2b_order_events')->where('b2b_order_id', $order->id)
                    ->where(fn ($query) => $query->where('to_status', 'paid')->orWhere('event', 'payment_received'))
                    ->min('created_at');
                DB::table('b2b_orders')->where('id', $order->id)
                    ->update(['paid_at' => $paidAt ?: $order->updated_at]);
            });
    }

    public function down(): void
    {
        Schema::table('b2b_orders', function (Blueprint $table): void {
            $table->dropIndex(['paid_at']);
            $table->dropColumn('paid_at');
        });
    }
};

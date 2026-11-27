<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')->select('id', 'review_sms_id', 'review_sms_sent_at')
            ->whereNotNull('review_sms_sent_at')->whereNotNull('review_sms_id')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('order_status_sms')
                    ->whereColumn('order_status_sms.order_id', 'orders.id')
                    ->whereColumn('order_status_sms.sms_id', 'orders.review_sms_id')
                    ->where('order_status_sms.order_status', 'customer_review');
            })
            ->chunkById(100, function ($orders): void {
                DB::table('order_status_sms')->insertOrIgnore($orders->map(fn ($order): array => [
                    'order_id' => $order->id,
                    'event_key' => 'review:'.substr(hash('sha256', $order->review_sms_id), 0, 25),
                    'order_status' => 'customer_review',
                    'message' => 'KOTLOV: Оставьте отзыв',
                    'state' => 'accepted',
                    'sms_id' => $order->review_sms_id,
                    'sent_at' => $order->review_sms_sent_at,
                    'created_at' => $order->review_sms_sent_at,
                    'updated_at' => now(),
                ])->all());
            });
    }
};

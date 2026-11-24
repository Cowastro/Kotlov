<?php

namespace App\Services\Orders;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

final class EvropochtaTrackingSmsJournal
{
    public static function eventKey(string $track): string
    {
        return 'ep:'.substr(hash('sha256', $track), 0, 29);
    }

    /** Mirror the existing sender result. This method never sends or queues SMS. */
    public function sync(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $current = Order::lockForUpdate()->findOrFail($order->id);
            if (blank($current->evropochta_tracking_number) || blank($current->evropochta_sms_status)) return;
            $state = match ($current->evropochta_sms_status) {
                'pending' => 'tracking_pending', // The separate tracking job owns this attempt.
                'accepted', 'unconfirmed', 'unavailable', 'skipped' => $current->evropochta_sms_status,
                default => 'unconfirmed',
            };
            $key = ['order_id' => $current->id, 'event_key' => self::eventKey($current->evropochta_tracking_number)];
            DB::table('order_status_sms')->insertOrIgnore($key + [
                'order_status' => 'evropochta_tracking', 'message' => 'KOTLOV: трек Европочты '.$current->evropochta_tracking_number,
                'state' => $state, 'created_at' => $current->evropochta_sms_sent_at ?: now(), 'updated_at' => now(),
            ]);
            DB::table('order_status_sms')->where($key)->update(['state' => $state,
                'sms_id' => $current->evropochta_sms_id, 'sent_at' => $current->evropochta_sms_sent_at, 'updated_at' => now()]);
        });
    }
}

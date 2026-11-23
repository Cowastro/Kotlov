<?php

namespace App\Services\Orders;

use App\Mail\OrderStatusUpdatedMail;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderStatusCustomerNotifier
{
    public function notify(Order $order, ?string $event = null): bool
    {
        if ($order->is_internal || ! in_array($order->status, ['processing', 'shipped', 'delivered'], true)) {
            return false;
        }

        $order->loadMissing('user');
        $recipient = mb_strtolower(trim((string) ($order->user?->email ?: $order->customer_email)));
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $event ??= $order->status;
        $claimed = DB::table('order_stage_email_claims')->insertOrIgnore([
            'order_id' => $order->id, 'status' => $event, 'created_at' => now(),
        ]);
        if (! $claimed) {
            return false;
        }
        try {
            Mail::mailer('transactional')
                ->to($recipient)
                ->queue(new OrderStatusUpdatedMail($order));

            return true;
        } catch (\Throwable $exception) {
            DB::table('order_stage_email_claims')->where('order_id', $order->id)->where('status', $event)->delete();
            Log::error('Order status customer email could not be queued.', [
                'order_id' => $order->id,
                'status' => $order->status,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}

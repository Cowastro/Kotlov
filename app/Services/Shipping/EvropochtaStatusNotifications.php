<?php

namespace App\Services\Shipping;

use App\Jobs\SendEvropochtaTrackingSms;
use App\Models\Order;
use App\Services\SmsByClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class EvropochtaStatusNotifications
{
    public function __construct(private readonly Evropochta $carrier, private readonly SmsByClient $sms) {}

    public function recordCancellation(Order $order, array $status): void
    {
        if ($status['code'] === Evropochta::STATUS_CANCELLED && self::enabledFor($order)) {
            $this->record($order, 'cancelled', $status['status']);
        }
    }

    public function messages(Order $order): Collection
    {
        return DB::table('evropochta_status_messages')->where('order_id', $order->id)
            ->where('tracking_number', $order->evropochta_tracking_number)
            ->latest('id')->get(['carrier_status', 'sms_status']);
    }

    public function run(): int
    {
        if (! $this->carrier->configured()) {
            return 0;
        }

        $orders = Order::query()
            ->where('delivery_type', EvropochtaCheckout::METHOD)
            ->whereNotNull('evropochta_tracking_number')
            ->where('evropochta_status_sms_started_at', '>=', now()->subDays(120))
            ->where(fn ($query) => $query->whereNull('evropochta_status_sms_polled_at')
                ->orWhere('evropochta_status_sms_polled_at', '<=', now()->subMinutes(5)))
            ->orderBy('evropochta_status_sms_polled_at')->orderBy('id')->limit(25)->get();

        foreach ($orders as $order) {
            $order->newQuery()->whereKey($order->id)->update(['evropochta_status_sms_polled_at' => now()]);
            try {
                $events = $this->carrier->notificationHistory($order->evropochta_tracking_number);
            } catch (EvropochtaShipmentException) {
                continue;
            }

            foreach ($events as $event) {
                $this->record($order, $event['key'], $event['status']);
            }
        }

        return $this->sendPending();
    }

    private static function enabledFor(Order $order): bool
    {
        return filled($order->evropochta_sms_status) && $order->evropochta_sms_status !== 'skipped';
    }

    private function record(Order $order, string $key, string $status): void
    {
        DB::table('evropochta_status_messages')->insertOrIgnore([
            'order_id' => $order->id,
            'tracking_number' => $order->evropochta_tracking_number,
            'carrier_event' => $key,
            'carrier_status' => mb_substr($status, 0, 255),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function sendPending(): int
    {
        if (! SendEvropochtaTrackingSms::configured()) {
            return 0;
        }

        $sent = 0;
        $groups = DB::table('evropochta_status_messages')->where('sms_status', 'pending')
            ->orderBy('updated_at')->orderBy('id')->limit(50)->get()->groupBy('tracking_number');
        foreach ($groups as $trackingNumber => $messages) {
            $pending = DB::table('evropochta_status_messages')->whereIn('id', $messages->pluck('id'))
                ->where('sms_status', 'pending');
            $pending->update(['updated_at' => now()]);
            try {
                $phone = $this->carrier->shipmentReceiverPhone($trackingNumber);
            } catch (EvropochtaShipmentException) {
                continue;
            }

            if ($phone === null) {
                $pending->update(['sms_status' => 'no_phone']);

                continue;
            }

            foreach ($messages as $message) {
                $sent += (int) $this->sendToRecipient($message, $phone);
            }
        }

        return $sent;
    }

    private function sendToRecipient(object $message, string $phone): bool
    {
        $attempt = DB::table('evropochta_status_messages')->where('id', $message->id);
        // An uncertain provider response must not generate a second SMS on the next poll.
        if ((clone $attempt)->where('sms_status', 'pending')->update(['sms_status' => 'unconfirmed']) !== 1) {
            return false;
        }

        try {
            $id = $this->sms->sendSms($phone, (string) config('services.sms_by.alphaname_id'),
                'Европочта: '.Str::limit($message->carrier_status, 42).' · '.$message->tracking_number);
        } catch (RuntimeException) {
            return false;
        }

        $attempt->update(['sms_status' => 'accepted', 'sms_id' => $id, 'sent_at' => now()]);

        return true;
    }
}

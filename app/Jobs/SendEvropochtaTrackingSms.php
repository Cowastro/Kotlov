<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Shipping\EvropochtaCheckout;
use App\Services\SmsByClient;
use App\Services\Orders\EvropochtaTrackingSmsJournal;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

final class SendEvropochtaTrackingSms
{
    use Queueable;

    public function __construct(
        public readonly int $orderId,
        public readonly string $trackingNumber,
        public readonly string $phone,
    ) {}

    public static function configured(): bool
    {
        return app(SmsByClient::class)->configuredForSms();
    }

    public function handle(SmsByClient $sms): void
    {
        $shipment = Order::query()->whereKey($this->orderId)
            ->where('delivery_type', EvropochtaCheckout::METHOD)
            ->where('evropochta_tracking_number', $this->trackingNumber);
        $pending = (clone $shipment)->where('evropochta_sms_status', 'pending');

        if (! self::configured()) {
            $pending->update(['evropochta_sms_status' => 'unavailable']);
            if ($order = (clone $shipment)->first()) app(EvropochtaTrackingSmsJournal::class)->sync($order);

            return;
        }

        // A timeout may mean SMS.BY accepted the request. Claim the attempt
        // before calling the provider so a replay cannot send a second SMS.
        $claimed = DB::transaction(function () use ($pending, $shipment): bool {
            $claimed = $pending->where('status', '!=', 'cancelled')->update(['evropochta_sms_status' => 'unconfirmed']) === 1;
            if ($order = (clone $shipment)->first()) app(EvropochtaTrackingSmsJournal::class)->sync($order);
            return $claimed;
        });
        if (! $claimed) {
            return;
        }

        try {
            $id = $sms->sendSms(
                $this->phone,
                (string) config('services.sms_by.alphaname_id'),
                'KOTLOV: трек Европочты '.$this->trackingNumber,
            );
        } catch (RuntimeException) {
            return;
        }

        DB::transaction(function () use ($shipment, $id): void {
            $shipment->update([
                'evropochta_sms_status' => 'accepted',
                'evropochta_sms_id' => $id,
                'evropochta_sms_sent_at' => now(),
            ]);
            DB::table('order_status_sms')->where('order_id', $this->orderId)
                ->where('event_key', EvropochtaTrackingSmsJournal::eventKey($this->trackingNumber))
                ->update(['state' => 'accepted', 'sms_id' => $id, 'sent_at' => now(), 'updated_at' => now()]);
        });
    }
}

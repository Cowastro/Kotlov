<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Shipping\CarrierOrderProgress;
use App\Services\Shipping\Evropochta;
use App\Services\Shipping\EvropochtaShipmentException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncEvropochtaOrderStatusCommand extends Command
{
    protected $signature = 'shipping:sync-evropochta-status';

    protected $description = 'Проверить не более пяти отправлений Европочты; каждое не чаще раза в 30 минут';

    public function handle(Evropochta $carrier, CarrierOrderProgress $progress): int
    {
        if (! $carrier->configured()) {
            return self::SUCCESS;
        }
        Cache::lock('evropochta-status-sync', 180)->get(function () use ($carrier, $progress): void {
            $orders = Order::where('delivery_type', 'evropochta')->whereNotNull('evropochta_tracking_number')
                ->whereNotIn('status', ['cancelled', 'delivered'])->whereNull('archived_at')
                ->where(fn ($q) => $q->whereNull('carrier_polled_at')->orWhere('carrier_polled_at', '<=', now()->subMinutes(30)))
                ->orderBy('carrier_polled_at')->orderBy('id')->limit(5)->get();
            foreach ($orders as $order) {
                $number = $order->evropochta_tracking_number;
                Order::whereKey($order->id)->where('evropochta_tracking_number', $number)->update(['carrier_polled_at' => now()]);
                $key = 'evropochta.order-status.v2.'.$number;
                Cache::lock($key.':request', 45)->get(function () use ($order, $number, $key, $carrier, $progress): void {
                    if (! Cache::add($key.':refreshing', true, now()->addMinutes(30))) {
                        return;
                    }
                    $observedAt = now();
                    try {
                        $status = $carrier->shipmentStatus($number);
                        Cache::put($key.':latest', $status, now()->addDays(30));
                        Cache::forget($key.':unavailable');
                        $progress->euro($order, $status, $observedAt, $number);
                    } catch (EvropochtaShipmentException) {
                        Cache::put($key.':unavailable', true, now()->addMinutes(30));
                    }
                });
            }
        });

        return self::SUCCESS;
    }
}

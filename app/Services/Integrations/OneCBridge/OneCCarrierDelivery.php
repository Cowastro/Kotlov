<?php

namespace App\Services\Integrations\OneCBridge;

use App\Models\Order;
use App\Models\VamExpressShipment;
use App\Services\Shipping\Evropochta;
use RuntimeException;

/** The driver's destination is the sending terminal; the recipient travels in the delivery instructions. */
class OneCCarrierDelivery
{
    public function carrier(Order $order): ?string
    {
        if (in_array($order->delivery_type, ['evropochta', 'vam_express'], true)) return $order->delivery_type;
        return $order->exists && $order->delivery_type === 'transport'
            && VamExpressShipment::where('order_id', $order->id)->count() === 1 ? 'vam_express' : null;
    }

    public function reference(Order $order): ?string
    {
        if ($this->carrier($order) === 'evropochta') return $order->evropochta_tracking_number;
        if ($this->carrier($order) !== 'vam_express' || ! $order->exists) return null;
        $rows = VamExpressShipment::where('order_id', $order->id)->get();
        return $rows->count() === 1 ? ($rows->first()->display_id ?: $rows->first()->remote_id) : null;
    }

    public function terminal(Order $order): ?string
    {
        if ($this->carrier($order) === 'vam_express') return config('services.vam_express.terminal_address');
        if ($this->carrier($order) !== 'evropochta') return null;
        $quote = $order->evropochta_quote ?? [];
        $origin = data_get($quote, 'parcel.store_id_start');
        if (! $origin) return null;
        // A quote records the selected sending branch, never the receiving branch or the global default.
        if ((string) data_get($quote, 'origin.id') === (string) $origin && filled(data_get($quote, 'origin.address'))) {
            return $quote['origin']['address'];
        }
        try {
            return app(Evropochta::class)->branches()[$origin]['label'] ?? null;
        } catch (RuntimeException) {
            return null;
        }
    }
}

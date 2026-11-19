<?php

namespace App\Livewire;

use App\Filament\Resources\Orders\OrderResource;
use App\Services\Shipping\Evropochta;
use App\Services\Shipping\EvropochtaCheckout;
use App\Services\Shipping\EvropochtaShipmentException;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class EvropochtaOrderStatus extends Component
{
    #[Locked]
    public int $orderId;

    #[Locked]
    public ?array $latest = null;

    #[Locked]
    public bool $loaded = false;

    #[Locked]
    public bool $unavailable = false;

    public function refreshStatus(Evropochta $carrier): void
    {
        abort_unless(auth()->user()?->canAccessPanel(Filament::getPanel('admin')), 403);

        $order = OrderResource::getEloquentQuery()->findOrFail($this->orderId);
        abort_unless(OrderResource::canView($order), 403);
        abort_unless($order->delivery_type === EvropochtaCheckout::METHOD && filled($order->evropochta_tracking_number), 404);

        $this->loaded = true;
        $this->unavailable = ! $carrier->configured();
        if ($this->unavailable) {
            return;
        }

        $key = 'evropochta.order-status.'.$order->evropochta_tracking_number;
        if (Cache::add($key.':refreshing', true, now()->addMinutes(3))) {
            try {
                $history = $carrier->trackingHistory($order->evropochta_tracking_number);
                if ($latest = $history[0] ?? null) {
                    Cache::put($key.':latest', $latest, now()->addDays(30));
                }
                Cache::forget($key.':unavailable');
            } catch (EvropochtaShipmentException) {
                Cache::put($key.':unavailable', true, now()->addMinutes(3));
            }
        }

        $this->latest = Cache::get($key.':latest');
        $this->unavailable = Cache::get($key.':unavailable', false);
    }

    public function render(): View
    {
        return view('livewire.evropochta-order-status');
    }
}

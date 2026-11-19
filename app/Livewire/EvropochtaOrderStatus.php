<?php

namespace App\Livewire;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Services\Shipping\Evropochta;
use App\Services\Shipping\EvropochtaCheckout;
use App\Services\Shipping\EvropochtaShipmentException;
use App\Services\Shipping\EvropochtaStatusNotifications;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
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

    #[Locked]
    public bool $canEditShipment = false;

    #[Locked]
    public ?string $smsStatus = null;

    #[Locked]
    public array $statusMessages = [];

    public function refreshStatus(Evropochta $carrier, EvropochtaStatusNotifications $notifications): void
    {
        $order = $this->trackingOrder();
        $this->canEditShipment = OrderResource::canEdit($order);
        $this->smsStatus = $order->evropochta_sms_status;
        $this->loadStatusMessages($order, $notifications);
        $this->loaded = true;
        $this->unavailable = ! $carrier->configured();
        if ($this->unavailable) {
            return;
        }

        $number = $order->evropochta_tracking_number;
        $key = 'evropochta.order-status.v2.'.$number;
        Cache::lock($key.':request', 45)->get(function () use ($carrier, $key, $number): void {
            if (! Cache::add($key.':refreshing', true, now()->addMinutes(3))) {
                return;
            }

            try {
                $this->rememberStatus($key, $carrier->shipmentStatus($number));
            } catch (EvropochtaShipmentException) {
                Cache::put($key.':unavailable', true, now()->addMinutes(3));
            }
        });

        $this->latest = Cache::get($key.':latest');
        $this->unavailable = Cache::get($key.':unavailable', false);
    }

    public function cancelShipment(Evropochta $carrier, EvropochtaStatusNotifications $notifications): void
    {
        $order = $this->trackingOrder();
        abort_unless(OrderResource::canEdit($order), 403);

        if (! $carrier->configured()) {
            $this->unavailable = true;
            Notification::make()->danger()->title('Ключ API Европочты не настроен.')->send();

            return;
        }

        $number = $order->evropochta_tracking_number;
        $key = 'evropochta.order-status.v2.'.$number;
        $handled = Cache::lock($key.':request', 45)->get(function () use ($carrier, $key, $number, $order, $notifications): bool {
            try {
                $status = $carrier->shipmentStatus($number);
                $this->rememberStatus($key, $status);
                if ($status['code'] !== Evropochta::STATUS_CREATED) {
                    Notification::make()->warning()->title('Отмена доступна только в статусе «Заявка создана».')->send();

                    return true;
                }

                $cancelled = $carrier->cancelShipment($number);
                $this->rememberStatus($key, $cancelled);
                $notifications->recordCancellation($order, $cancelled);
                $this->loadStatusMessages($order, $notifications);
                Notification::make()->success()->title('Отправление Европочты отменено')->send();
            } catch (EvropochtaShipmentException $failure) {
                $this->unavailable = true;
                Cache::put($key.':unavailable', true, now()->addMinutes(3));
                Cache::forget($key.':refreshing');
                Notification::make()->danger()->title($failure->getMessage())->send();
            }

            return true;
        });

        if (! $handled) {
            Notification::make()->warning()->title('Европочта обрабатывает другой запрос. Повторите позже.')->send();
        }
    }

    private function loadStatusMessages(Order $order, EvropochtaStatusNotifications $notifications): void
    {
        $this->statusMessages = $notifications->messages($order)->map(fn (object $message): array => (array) $message)->all();
    }

    private function trackingOrder(): Order
    {
        abort_unless(auth()->user()?->canAccessPanel(Filament::getPanel('admin')), 403);

        $order = OrderResource::getEloquentQuery()->findOrFail($this->orderId);
        abort_unless(OrderResource::canView($order), 403);
        abort_unless($order->delivery_type === EvropochtaCheckout::METHOD && filled($order->evropochta_tracking_number), 404);

        return $order;
    }

    private function rememberStatus(string $key, array $status): void
    {
        $this->loaded = true;
        $this->latest = $status;
        $this->unavailable = false;
        Cache::put($key.':latest', $status, now()->addDays(30));
        Cache::put($key.':refreshing', true, now()->addMinutes(3));
        Cache::forget($key.':unavailable');
    }

    public function render(): View
    {
        return view('livewire.evropochta-order-status');
    }
}

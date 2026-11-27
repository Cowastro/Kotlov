<?php

namespace App\Services\Shipping;

use App\Models\Order;
use App\Models\VamExpressShipment;
use App\Services\Orders\OrderStatusCustomerNotifier;
use App\Support\Shipping\VamExpressStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class CarrierOrderProgress
{
    private const RANK = ['created' => 0, 'accepted' => 1, 'transit' => 2, 'ready' => 3, 'delivered' => 4];

    public function vam(VamExpressShipment $shipment, CarbonInterface $observedAt): void
    {
        if (! $shipment->order_id || VamExpressShipment::where('order_id', $shipment->order_id)->count() !== 1) {
            return;
        }
        $group = VamExpressStatus::group($shipment->carrier_status);
        $stage = match ($group) {
            'new' => 'created', 'processed', 'delivered' => 'delivered',
            'warehouse' => 'accepted', 'transit' => 'transit',
            'cancelled', 'warning', 'return', 'exchange' => 'attention', default => null,
        };
        $this->apply($shipment->order_id, 'vam', $shipment->remote_id, $shipment->display_id, $stage, $shipment->carrier_status, $observedAt);
    }

    public function euro(Order $order, array $status, CarbonInterface $observedAt, string $number): void
    {
        // Exact names from the carrier response; unknown wording never implies receipt.
        $name = mb_strtolower(trim($status['status'] ?? ''));
        $stage = match (true) {
            ($status['code'] ?? null) === 0 => 'created',
            in_array($name, ['отменено', 'отменена', 'заявка отменена', 'возврат', 'возврат отправителю', 'возвращено отправителю', 'отказ получателя'], true) => 'attention',
            in_array($name, ['вручено', 'вручено получателю', 'выдано', 'выдано получателю', 'доставлено получателю'], true) => 'delivered',
            in_array($name, ['прибыло в отделение назначения', 'готово к выдаче', 'ожидает получения'], true) => 'ready',
            in_array($name, ['в пути', 'в пути в отделение назначения', 'передано курьеру'], true) => 'transit',
            in_array($name, ['принято', 'принято от отправителя', 'принято в отделении', 'принято к пересылке'], true) => 'accepted',
            default => null,
        };
        $this->apply($order->id, 'evropochta', $number, $number, $stage, $status['status'] ?? '', $observedAt);
    }

    public function euroCheckFailed(Order $order, string $number, CarbonInterface $observedAt): void
    {
        Order::whereKey($order->id)->where('delivery_type', 'evropochta')->where('evropochta_tracking_number', $number)
            ->where(fn ($query) => $query->whereNull('carrier_checked_at')->orWhere('carrier_checked_at', '<=', $observedAt))
            ->update(['carrier_check_failed_at' => $observedAt]);
    }

    private function apply(int $id, string $provider, string $reference, ?string $display, ?string $stage, string $text, CarbonInterface $observedAt): void
    {
        DB::transaction(function () use ($id, $provider, $reference, $display, $stage, $text, $observedAt): void {
            $order = Order::lockForUpdate()->find($id);
            if (! $order) return;
            $closed = $order->is_internal || $order->archived_at || in_array($order->status, ['cancelled', 'delivered'], true);
            // Closed retail orders still need their carrier status recorded, without reopening or notifying them.
            if ($closed && $provider !== 'evropochta') return;
            if ($provider === 'evropochta' && ($order->delivery_type !== 'evropochta' || $order->evropochta_tracking_number !== $reference)) {
                return;
            }
            if ($provider === 'vam' && ($order->delivery_type === 'evropochta' || VamExpressShipment::where('order_id', $id)->count() !== 1 || ! VamExpressShipment::where('order_id', $id)->where('remote_id', $reference)->exists())) {
                return;
            }
            $same = $order->carrier_provider === $provider && $order->carrier_reference === $reference;
            if ($same && $order->carrier_checked_at?->gt($observedAt)) {
                return;
            }
            $previous = $same ? $order->carrier_stage : null;
            if ($provider === 'evropochta' && $order->carrier_check_failed_at?->lte($observedAt)) {
                $order->forceFill(['carrier_check_failed_at' => null])->save();
            }
            if ($stage === 'created' && $previous && $previous !== 'created') {
                return;
            }
            if ($stage !== null && $stage !== 'attention' && $previous !== 'attention' && (self::RANK[$stage] ?? 0) < (self::RANK[$previous] ?? 0)) {
                return;
            }
            // An unrecognised response preserves the last verified customer stage.
            $order->forceFill(['carrier_provider' => $provider, 'carrier_reference' => $reference,
                'carrier_display_reference' => $display, 'carrier_status_text' => mb_substr($text, 0, 255),
                'carrier_checked_at' => $observedAt, 'carrier_stage' => $stage ?? $previous])->save();
            if ($closed || $stage === null || $stage === 'created') {
                return;
            }
            $target = $stage === 'delivered' ? 'delivered' : ($stage === 'attention' ? $order->status : 'shipped');
            if ($target !== $order->status) {
                $order->transitionTo($target, 'Статус перевозчика: '.$text);
            }
            app(OrderStatusCustomerNotifier::class)->notify($order->fresh(['user']), 'c:'.substr(hash('sha256', $provider.':'.$reference), 0, 12).':'.$stage);
        });
    }
}

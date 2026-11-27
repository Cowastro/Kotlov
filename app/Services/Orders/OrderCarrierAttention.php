<?php

namespace App\Services\Orders;

use App\Filament\Resources\Evropochta\ShipmentResource;
use App\Filament\Resources\VamExpress\VamExpressShipmentResource;
use App\Models\Order;
use App\Support\Shipping\VamExpressStatus;
use Illuminate\Database\Eloquent\Builder;

class OrderCarrierAttention
{
    public static function query(Builder $query): Builder
    {
        return $query->where(fn (Builder $carrier) => $carrier
            ->where(fn (Builder $euro) => $euro->where('delivery_type', 'evropochta')
                ->whereNotNull('evropochta_tracking_number')
                ->where(fn (Builder $issue) => $issue->whereNotNull('carrier_check_failed_at')
                    ->orWhere(fn (Builder $status) => $status->where('carrier_provider', 'evropochta')
                        ->whereColumn('carrier_reference', 'evropochta_tracking_number')->where('carrier_stage', 'attention'))))
            ->orWhere(fn (Builder $vam) => $vam->where('delivery_type', '!=', 'evropochta')
                ->whereHas('vamExpressShipments', fn (Builder $shipments) => $shipments
                    ->where(fn (Builder $issue) => VamExpressStatus::scope($issue, 'attention'))
                    ->orWhere(fn (Builder $check) => $check->whereNotNull('checked_at')
                        ->where(fn (Builder $missing) => $missing->whereNull('synced_at')->orWhereColumn('checked_at', '>', 'synced_at'))))));
    }

    /** @return list<array{label: string, url?: string, link?: string}> */
    public static function reasons(Order $order): array
    {
        if ($order->delivery_type === 'evropochta') {
            if (blank($order->evropochta_tracking_number)) {
                return [];
            }

            $reasons = [];
            $url = ShipmentResource::trackingUrl($order);
            if ($order->carrier_provider === 'evropochta' && $order->carrier_reference === $order->evropochta_tracking_number
                && $order->carrier_stage === 'attention') {
                $reasons[] = self::reason('Европочта · '.$order->carrier_status_text, $url);
            }
            if ($order->carrier_check_failed_at) {
                $reasons[] = self::reason('Европочта · Не удалось проверить статус', $url);
            }

            return $reasons;
        }

        $reasons = [];
        foreach ($order->vamExpressShipments as $shipment) {
            $url = VamExpressShipmentResource::canAccess()
                ? VamExpressShipmentResource::getUrl('view', ['record' => $shipment])
                : null;
            if (in_array(VamExpressStatus::group($shipment->carrier_status), ['cancelled', 'warning', 'return', 'exchange'], true)) {
                $reasons[] = self::reason('ВАМ Экспресс · '.$shipment->carrier_status, $url);
            }
            if ($shipment->checked_at && (! $shipment->synced_at || $shipment->checked_at->gt($shipment->synced_at))) {
                $reasons[] = self::reason('ВАМ Экспресс · Отправление не найдено при проверке', $url);
            }
        }

        return $reasons;
    }

    /** @return array{label: string, url?: string, link?: string} */
    private static function reason(string $label, ?string $url): array
    {
        return ['label' => $label] + ($url ? ['url' => $url, 'link' => 'Открыть отправление'] : []);
    }
}

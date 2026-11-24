<?php

namespace App\Services\Shipping;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\Models\VamExpressShipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VamExpressOrderDelivery
{
    public function select(Order $order, User $actor, ?int $shipmentId = null): void
    {
        $actor = $actor->fresh();
        abort_unless($actor?->is_active && $actor->isManager() && OrderResource::canEdit($order), 403);
        DB::transaction(function () use ($order, $shipmentId): void {
            $shipment = $shipmentId !== null ? VamExpressShipment::lockForUpdate()->findOrFail($shipmentId) : null;
            $locked = Order::lockForUpdate()->findOrFail($order->id);
            abort_unless(OrderResource::canEdit($locked), 403);
            if (! in_array($locked->status, ['processing', 'shipped'], true)) {
                throw ValidationException::withMessages(['shipment_id' => 'Сначала подтвердите заказ и передайте его в исполнение.']);
            }
            if ($locked->archived_at || in_array($locked->status, ['cancelled', 'delivered'], true)) {
                throw ValidationException::withMessages(['shipment_id' => 'Заказ закрыт. Изменение доставки недоступно.']);
            }
            if ($locked->delivery_type === 'evropochta' && filled($locked->evropochta_tracking_number)) {
                throw ValidationException::withMessages(['shipment_id' => 'У заказа есть отправление Европочты. Сначала разберите его отмену, чтобы не создать две доставки.']);
            }
            if ($shipment !== null) {
                if (($shipment->order_id && $shipment->order_id !== $locked->id)
                    || VamExpressShipment::where('order_id', $locked->id)->where('id', '!=', $shipmentId)->exists()) {
                    throw ValidationException::withMessages(['shipment_id' => 'У заявки или заказа уже есть другая привязка. Проверьте её в разделе ВАМ Экспресс.']);
                }
                $shipment->forceFill(['order_id' => $locked->id])->save();
            }
            $locked->update(['delivery_type' => 'vam_express']);
        });
    }
}

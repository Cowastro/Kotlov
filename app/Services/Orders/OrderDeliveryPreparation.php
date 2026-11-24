<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\User;
use App\Models\VamExpressShipment;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderDeliveryPreparation
{
    public function reference(Order $order): ?string
    {
        if ($order->delivery_type === 'evropochta') {
            return filled($order->evropochta_tracking_number) ? $order->evropochta_tracking_number : null;
        }
        if ($order->delivery_type === 'vam_express') {
            $rows = VamExpressShipment::where('order_id', $order->id)->get();
            return $rows->count() === 1 ? ($rows->first()->display_id ?: $rows->first()->remote_id) : null;
        }
        return null;
    }

    public function signature(Order $order): string
    {
        return hash('sha256', json_encode([
            $order->delivery_type, $order->delivery_region, $order->delivery_city, $order->delivery_address,
            $order->planned_delivery_date?->format('Y-m-d'), $order->customer_name, $order->customer_phone,
            $order->total, $this->reference($order),
            $order->delivery_type === 'vam_express' ? VamExpressShipment::where('order_id', $order->id)->value('remote_id') : null,
            $order->items()->orderBy('id')->get(['id', 'product_id', 'product_sku', 'quantity', 'price', 'total', 'fulfillment_route', 'fulfillment_supplier_id'])->toArray(),
        ], JSON_THROW_ON_ERROR));
    }

    public function blocker(Order $order): ?string
    {
        if ($order->status !== 'processing') return 'Сначала подтвердите заказ и нажмите «Передать в исполнение».';
        if (! $order->items()->exists() || $order->needsFulfillmentConfirmation()) return 'Сначала подтвердите исполнение всех позиций.';
        if (in_array($order->delivery_type, ['evropochta', 'vam_express'], true) && ! $this->reference($order)) {
            return $order->delivery_type === 'evropochta'
                ? 'Сначала оформите Европочту: нужен трек-номер.'
                : 'Сначала привяжите отправление ВАМ Экспресс: нужен ID заявки.';
        }
        $plan = $order->delivery_preparation ?? [];
        if (blank($plan['from'] ?? null) || blank($plan['to'] ?? null) || blank($plan['prepared_at'] ?? null)) {
            return 'Сначала согласуйте доставку: нажмите «Подготовить доставку / самовывоз».';
        }
        if (! hash_equals((string) ($plan['signature'] ?? ''), $this->signature($order))) {
            return 'Данные заказа или доставки изменились. Подтвердите подготовку доставки заново.';
        }
        return null;
    }

    public function confirm(Order $order, User $actor, array $input): void
    {
        $data = Validator::make($input, [
            'method' => ['required', 'in:'.implode(',', array_keys(Order::DELIVERY_TYPES))],
            'from' => ['required', 'string', 'max:1000'], 'to' => ['required', 'string', 'max:1000'],
            'date' => ['required', 'date', 'after_or_equal:today'], 'note' => ['nullable', 'string', 'max:2000'],
        ])->validate();
        DB::transaction(function () use ($order, $actor, $data): void {
            $locked = Order::lockForUpdate()->findOrFail($order->id);
            $locked->setRelation('items', $locked->items()->lockForUpdate()->get());
            if ($locked->status !== 'processing' || ! $locked->items->count() || $locked->needsFulfillmentConfirmation()) {
                throw new DomainException('Подготовка доставки доступна после подтверждения заказа и передачи в исполнение.');
            }
            if ($locked->onec_order_guid || app(\App\Services\Integrations\OneCBridge\OneCAgentTasks::class)
                ->activeFor($locked, \App\Services\Integrations\OneCBridge\OrderTaskHandler::TYPE)) throw new DomainException('Заказ уже передан в 1С. Изменения доставки согласуйте отдельно.');
            $locked->fill(['delivery_type' => $data['method'], 'planned_delivery_date' => $data['date']])->save();
            $locked->refresh();
            if (in_array($locked->delivery_type, ['evropochta', 'vam_express'], true) && ! $this->reference($locked)) {
                throw new DomainException('Для выбранного перевозчика сначала оформите и привяжите трек / ID отправления.');
            }
            $locked->delivery_preparation = ['from' => trim($data['from']), 'to' => trim($data['to']),
                'note' => trim($data['note'] ?? ''), 'reference' => $this->reference($locked),
                'prepared_at' => now()->toIso8601String(), 'prepared_by' => $actor->id, 'signature' => $this->signature($locked)];
            $locked->save();
            $locked->statusHistory()->create(['user_id' => $actor->id, 'status_from' => 'processing', 'status_to' => 'processing',
                'comment' => 'Доставка согласована. '.Order::DELIVERY_TYPES[$locked->delivery_type].'. '.$this->summary($locked)]);
        });
    }

    public function summary(Order $order): string
    {
        $plan = $order->delivery_preparation ?? [];
        return collect([Order::DELIVERY_TYPES[$order->delivery_type] ?? $order->delivery_type,
            filled($plan['from'] ?? null) ? 'Забрать: '.$plan['from'] : null,
            filled($plan['to'] ?? null) ? 'Передать: '.$plan['to'] : null,
            $order->planned_delivery_date ? 'Дата: '.$order->planned_delivery_date->format('d.m.Y') : null,
            $this->reference($order) ? 'Трек / ID: '.$this->reference($order) : null,
            $plan['note'] ?? null])->filter()->implode("\n");
    }
}

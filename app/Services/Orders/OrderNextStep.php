<?php

namespace App\Services\Orders;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SupplierOrderRequest;

class OrderNextStep
{
    /** @return list<string> */
    public function actionCandidates(Order $order): array
    {
        if (! OrderResource::canEdit($order) || in_array($order->status, ['delivered', 'completed', 'cancelled'], true)) {
            return [];
        }

        return match (true) {
            $order->usesGuidedFulfillment() && $order->needsFulfillmentConfirmation() => ['confirmFulfillment', 'confirmItemFulfillment'],
            $this->pendingSupplierReply($order) !== null => ['recordSupplierEmailResponse'],
            $order->needsEvropochtaShipment() => ['createEvropochtaShipment', 'prepareEvropochtaShipment'],
            app(OrderDeliveryPreparation::class)->needsPreparation($order) => ['prepareOrderDelivery'],
            $order->status === 'processing' && blank($order->onec_order_guid) => ['advanceWorkflow', 'onecOrder'],
            default => ['advanceWorkflow'],
        };
    }

    /** @return array{item: OrderItem, message: ?string, count: int}|null */
    public function fulfillmentIssue(Order $order): ?array
    {
        if (! OrderResource::canEdit($order) || ! in_array($order->status, ['new', 'checking', 'confirmed', 'processing'], true)) {
            return null;
        }

        $unassigned = $order->items->filter(fn (OrderItem $item): bool => blank($item->fulfillment_supplier_id)
            && in_array($item->fulfillment_route ?: $item->supplyContext()['status'], ['supplier_purchase', 'direct_supplier'], true));
        $canReviewOwnStock = $order->status !== 'new'
            && ! (filled($order->onec_order_guid) && ! $order->onec_deleted && $order->onec_posted);
        $shortages = [];
        if ($canReviewOwnStock) {
            $display = app(OrderItemAvailabilityDisplay::class);
            foreach ($order->items as $item) {
                if ($message = $display->ownStockShortage($item)) {
                    $shortages[] = ['item' => $item, 'message' => $message];
                }
            }
        }

        $next = $shortages[0] ?? ['item' => $unassigned->first(), 'message' => null];

        return $next['item'] ? $next + ['count' => count($shortages) + $unassigned->count()] : null;
    }

    public function pendingSupplierReply(Order $order): ?SupplierOrderRequest
    {
        if (! OrderResource::canEdit($order) || ! in_array($order->status, ['confirmed', 'processing'], true)) {
            return null;
        }

        return $order->supplierOrderRequests->first(fn (SupplierOrderRequest $request): bool => $request->status === 'sent'
            && $request->transfer_mode === 'email');
    }

    /** @return array{label: string, url: string, count: int}|null */
    public function queueShortcut(Order $order): ?array
    {
        if ($issue = $this->fulfillmentIssue($order)) {
            return [
                'label' => $issue['message'] ? 'Проверить исполнение' : 'Назначить поставщика',
                'url' => OrderResource::getUrl('view', ['record' => $order, 'next-step' => 'fulfillment']),
                'count' => $issue['count'],
            ];
        }

        if ($order->status === 'processing' && ($this->actionCandidates($order)[0] ?? null) === 'prepareOrderDelivery') {
            return [
                'label' => 'Подготовить '.app(OrderDeliveryPreparation::class)->handoverLabel($order),
                'url' => OrderResource::getUrl('view', ['record' => $order, 'next-step' => 'delivery']),
                'count' => 0,
            ];
        }

        return null;
    }
}

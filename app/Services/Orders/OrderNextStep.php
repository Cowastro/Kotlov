<?php

namespace App\Services\Orders;

use App\Filament\Resources\OneCAgentTasks\OneCAgentTaskResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\OneCAgentTask;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SupplierOrderRequest;
use App\Services\Integrations\OneCBridge\OrderTaskHandler;
use App\Services\Shipping\Evropochta;

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

    /** @return list<array{label: string, action: string, url?: string, link?: string}> */
    public function queueWaitReasons(Order $order): array
    {
        $reasons = OrderCarrierAttention::reasons($order);
        $task = $order->latestOnecOrderTask;
        if ($task?->status === OneCAgentTask::FAILED
            && ($task->type !== OrderTaskHandler::TYPE || blank($order->onec_order_guid) || $order->onec_deleted)) {
            $reasons[] = ['label' => 'Ошибка 1С', 'action' => 'Посмотреть ошибку и устранить причину сбоя'] + (OneCAgentTaskResource::canViewAny() && OneCAgentTaskResource::canView($task) ? [
                'url' => OneCAgentTaskResource::getUrl('view', ['record' => $task]),
                'link' => 'Задача №'.$task->id,
            ] : []);
        }

        if ($request = $this->pendingSupplierReply($order)) {
            $reasons[] = [
                'label' => 'Ждём ответ поставщика · '.$request->supplier_name.' · '.$request->number,
                'action' => 'Уточнить ответ у поставщика и зафиксировать его в заказе',
                'url' => OrderResource::getUrl('view', ['record' => $order]),
                'link' => 'Открыть заказ',
            ];
        }

        if ($order->delivery_type === 'evropochta' && preg_match('/^BY\d{12}$/D', (string) $order->evropochta_tracking_number)) {
            $status = app(Evropochta::class)->labelStatus($order->evropochta_tracking_number, 'thermal');
            if ($status !== Evropochta::LABEL_READY) {
                $reasons[] = [
                    'label' => match ($status) {
                        Evropochta::LABEL_PENDING => 'Этикетка Европочты ещё готовится',
                        Evropochta::LABEL_UNAVAILABLE => 'Этикетка Европочты временно недоступна',
                        default => 'Готовность этикетки Европочты не проверена',
                    },
                    'action' => $status === Evropochta::LABEL_PENDING
                        ? 'Дождаться этикетки и распечатать её'
                        : 'Проверить готовность этикетки в заказе',
                    'url' => OrderResource::getUrl('view', ['record' => $order]),
                    'link' => 'Открыть заказ',
                ];
            }
        }

        return $reasons;
    }

    /** @return list<array{label: string, action: string, url?: string, link?: string}> */
    public function queueAttentionReasons(Order $order): array
    {
        $reasons = $this->queueWaitReasons($order);
        $orderUrl = OrderResource::getUrl('view', ['record' => $order]);
        $problems = $order->managementSummary()['problems'];
        if ($order->items->whereNotNull('supply_captured_at')->pluck('supply_supplier_id')->filter()->unique()->count() > 1) {
            $problems = $problems->concat([['code' => 'mixed_suppliers', 'label' => 'Несколько поставщиков']]);
        }
        if (config('onec.order_queue_enabled', false) && $order->integrationIssues->isNotEmpty()
            && ! $problems->contains('code', 'sync_problem')) {
            $problems = $problems->concat([['code' => 'sync_problem', 'label' => 'Проблема обмена с 1С']]);
        }

        foreach ($problems as $problem) {
            $action = match ($problem['code']) {
                'missing_supplier' => 'Назначить поставщика по позиции',
                'missing_price' => 'Уточнить входную цену по позиции',
                'negative_margin', 'low_margin' => 'Сверить входную цену и цену продажи',
                'no_stock' => $order->status === 'shipped'
                    ? 'Сверить остаток и источник отгрузки'
                    : 'Подтвердить остаток или выбрать другой источник',
                'mixed_suppliers' => 'Сверить позиции и сроки у каждого поставщика',
                'unassigned' => 'Назначить ответственного менеджера',
                'sync_problem' => 'Проверить обмен с 1С в заказе',
                'supplier_request_rejected' => 'Уточнить отказ и подобрать другой источник',
                'payment_failed' => 'Проверить платёж и способ оплаты',
            };
            $reasons[] = ['label' => $problem['label'], 'action' => $action];
        }

        return $reasons ?: [[
            'label' => 'Заказ требует проверки',
            'action' => 'Сверить позиции и историю заказа',
            'url' => $orderUrl,
            'link' => 'Открыть заказ',
        ]];
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

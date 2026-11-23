<?php

namespace App\Services\Integrations\OneCBridge;

use App\Models\Order;
use App\Services\Orders\OrderStatusCustomerNotifier;

/**
 * Once an order is in 1C its logistics are done there, so the site moves its stages after 1C: a posted order «К
 * обеспечению» / «К выполнению» is «В исполнении», a closed one is «Доставлен». Only forward, never a cancelled one.
 * The customer hears of every move of an order the manager confirmed on the site. An order 1C already has in work
 * that was never confirmed on the site (an old one, agreed with the customer outside the site) moves silently.
 * A 1C order closed with every line cancelled is not a delivery: the manager gets a note instead.
 */
class OneCOrderStages
{
    private const RANK = ['new' => 0, 'checking' => 1, 'confirmed' => 2, 'processing' => 3, 'shipped' => 4, 'delivered' => 5];

    private const IN_WORK = ['КОбеспечению', 'КВыполнению'];

    /** What the stage of the order should be after 1C, or null. */
    public function target(Order $order, bool $allLinesCancelled = false): ?string
    {
        if (blank($order->onec_order_guid) || $order->onec_deleted || ! $order->onec_posted) {
            return null;
        }

        return match (true) {
            $order->onec_doc_status === 'Закрыт' && ! $allLinesCancelled => 'delivered',
            OneCOrderPayments::readyToClose($order) => 'delivered', // «Готов к закрытию»: shipped and paid
            in_array($order->onec_doc_status, self::IN_WORK, true) => 'processing',
            default => null,
        };
    }

    /** Moves the order forward after 1C; @return string|null the new stage */
    public function apply(Order $order, bool $allLinesCancelled = false, ?string $upTo = null): ?string
    {
        $order->refresh();
        if ($allLinesCancelled && $order->onec_doc_status === 'Закрыт' && $order->status !== 'cancelled'
            && ! $order->managerComments()->where('body', 'like', '%закрыт в 1С без отгрузки%')->exists()) {
            $order->managerComments()->create(['author_name' => 'Мост 1С',
                'body' => 'Заказ 1С '.$order->onec_order_number.' закрыт в 1С без отгрузки (все строки отменены) — проверьте и при необходимости отмените заказ на сайте.']);
        }
        $target = $this->target($order, $allLinesCancelled);
        if ($upTo !== null && $target !== null && self::RANK[$target] > self::RANK[$upTo]) {
            $target = $upTo;
        }
        $current = self::RANK[$order->status] ?? null;
        if ($target === null || $current === null || $order->archived_at || self::RANK[$target] <= $current) {
            return null;
        }
        $silent = $current < self::RANK['confirmed'];
        $status = $order->onec_doc_status !== 'Закрыт' && OneCOrderPayments::readyToClose($order) ? 'Готов к закрытию'
            : (OneCOrderProfiles::STATUSES[$order->onec_doc_status] ?? $order->onec_doc_status);
        $order->transitionTo($target, 'По 1С: заказ '.$order->onec_order_number.' проведён, «'.$status.'»'
            .($silent ? ' (не подтверждался на сайте — клиенту не сообщали).' : '.'));
        if (! $silent) {
            app(OrderStatusCustomerNotifier::class)->notify($order->fresh(['user']));
        }

        return $target;
    }
}

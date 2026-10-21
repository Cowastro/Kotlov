<?php

namespace App\Services\Orders;

use App\Mail\OrderAvailabilityConfirmedMail;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class OrderAvailabilityConfirmation
{
    /** @return array{order: Order, notified: bool} */
    public function confirmAndNotify(Order $order, ?User $manager = null): array
    {
        $order = DB::transaction(function () use ($order, $manager): Order {
            $locked = Order::query()->lockForUpdate()->with(['items', 'user'])->findOrFail($order->id);

            if (! in_array($locked->status, ['new', 'checking', 'confirmed'], true)) {
                throw new RuntimeException('Подтвердить наличие можно только до передачи заказа в исполнение.');
            }

            if (! $locked->hasConfirmedFulfillmentForAllItems()) {
                throw new RuntimeException('Сначала подтвердите наличие и способ исполнения каждой позиции.');
            }

            if ($locked->status === 'new') {
                $locked->transitionTo('checking', 'Начата проверка наличия перед подтверждением заказа.', [
                    'manager_id' => $locked->manager_id ?: $manager?->id,
                ]);
            }

            if ($locked->status !== 'confirmed' || $locked->availability_confirmed_at === null) {
                $locked->transitionTo('confirmed', 'Наличие и исполнение всех позиций подтверждены менеджером.', [
                    'availability_confirmed_at' => $locked->availability_confirmed_at ?: now(),
                    'availability_confirmed_by' => $locked->availability_confirmed_by ?: $manager?->id,
                    'manager_id' => $locked->manager_id ?: $manager?->id,
                ]);
            }

            return $locked->fresh(['items', 'user']);
        });

        return [
            'order' => $order,
            'notified' => $this->notifyCustomerIfNeeded($order),
        ];
    }

    private function notifyCustomerIfNeeded(Order $order): bool
    {
        if ($order->customer_notified_at !== null || ! $order->user_id) {
            return false;
        }

        $recipient = mb_strtolower(trim((string) ($order->user?->email ?: $order->customer_email)));
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $notifiedAt = now();
        $claimed = Order::query()
            ->whereKey($order->id)
            ->whereNull('customer_notified_at')
            ->update(['customer_notified_at' => $notifiedAt]);

        if ($claimed !== 1) {
            return false;
        }

        try {
            Mail::mailer('transactional')
                ->to($recipient)
                ->queue(new OrderAvailabilityConfirmedMail($order));
        } catch (\Throwable $exception) {
            Order::query()
                ->whereKey($order->id)
                ->where('customer_notified_at', $notifiedAt)
                ->update(['customer_notified_at' => null]);

            throw $exception;
        }

        $order->forceFill(['customer_notified_at' => $notifiedAt]);

        return true;
    }
}

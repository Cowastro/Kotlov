<?php

namespace App\Services\B2bOrders;

use App\Models\B2bOrder;
use App\Models\B2bOrderEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class B2bOrderWorkflow
{
    private const TRANSITIONS = [
        'new' => ['assigned', 'checking', 'cancelled'],
        'assigned' => ['checking', 'cancelled'],
        'checking' => ['awaiting_supplier', 'proposal_ready', 'cancelled'],
        'awaiting_supplier' => ['checking', 'proposal_ready', 'cancelled'],
        'proposal_ready' => ['awaiting_client', 'cancelled'],
        'awaiting_client' => ['client_confirmed', 'checking', 'cancelled'],
        'client_confirmed' => ['invoice_issued', 'cancelled'],
        'invoice_issued' => ['awaiting_payment', 'credit_approved', 'paid', 'cancelled'],
        'awaiting_payment' => ['paid', 'cancelled'],
        'credit_approved' => ['assembling', 'cancelled'],
        'paid' => ['assembling', 'cancelled'],
        'assembling' => ['ready_to_ship', 'cancelled'],
        'ready_to_ship' => ['shipped', 'cancelled'],
        'shipped' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function accept(B2bOrder $order, User $manager): B2bOrder
    {
        abort_unless($manager->canManageB2bOrders(), 403);

        return DB::transaction(function () use ($order, $manager): B2bOrder {
            $order->forceFill([
                'assigned_manager_id' => $manager->id,
                'accepted_by' => $manager->id,
                'accepted_at' => now(),
            ])->save();

            $order = $this->transition($order, 'checking', $manager, 'Менеджер принял заказ в работу.');
            app(B2bOrderStockCheck::class)->check($order, $manager);

            return $order->refresh();
        });
    }

    /** @return array<string, string> */
    public function allowedTransitions(B2bOrder $order): array
    {
        return collect(self::TRANSITIONS[$order->status] ?? [])
            ->mapWithKeys(fn (string $status): array => [$status => B2bOrder::STATUSES[$status] ?? $status])
            ->all();
    }

    public function transition(B2bOrder $order, string $status, User $actor, ?string $comment = null): B2bOrder
    {
        abort_unless($actor->canManageB2bOrders(), 403);

        return DB::transaction(function () use ($order, $status, $actor, $comment): B2bOrder {
            $locked = B2bOrder::query()->lockForUpdate()->with('items')->findOrFail($order->id);
            $from = $locked->status;
            if (! in_array($status, self::TRANSITIONS[$from] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => "Переход «{$from}» → «{$status}» недоступен.",
                ]);
            }
            if ($status === 'cancelled' && trim((string) $comment) === '') {
                throw ValidationException::withMessages(['comment' => 'Укажите причину отмены B2B-заказа.']);
            }
            if ($status === 'proposal_ready' && ! $locked->stockIsSettled()) {
                throw ValidationException::withMessages([
                    'status' => 'Сначала подтвердите наличие каждой позиции заказа или уберите её с указанием причины.',
                ]);
            }

            $changes = ['status' => $status];
            if ($status === 'proposal_ready') $changes['availability_confirmed_at'] = now();
            if ($status === 'completed') $changes['completed_at'] = now();
            if ($status === 'cancelled') $changes['cancelled_at'] = now();
            if ($status === 'paid') $changes['payment_status'] = 'paid';
            if ($status === 'invoice_issued') $changes['payment_status'] = 'invoiced';
            if ($status === 'credit_approved') $changes['payment_status'] = 'credit';

            $locked->forceFill($changes)->save();
            if ($status === 'cancelled') app(B2bOrderStockCheck::class)->releaseOrder($locked);
            if ($status === 'shipped') app(B2bOrderStockCheck::class)->shipOrder($locked);
            B2bOrderEvent::create([
                'b2b_order_id' => $locked->id,
                'actor_id' => $actor->id,
                'event' => 'status_changed',
                'from_status' => $from,
                'to_status' => $status,
                'comment' => $comment,
            ]);

            return $locked->refresh();
        });
    }

    public function clientConfirm(B2bOrder $order, User $actor): B2bOrder
    {
        return DB::transaction(function () use ($order, $actor): B2bOrder {
            $locked = B2bOrder::query()->lockForUpdate()->findOrFail($order->id);
            $client = $actor->activeB2bClient();
            abort_unless($client && (int) $locked->b2b_client_id === (int) $client->id, 404);
            if ($locked->status !== 'awaiting_client') {
                throw ValidationException::withMessages(['order' => 'Этот заказ сейчас не ожидает подтверждения.']);
            }

            $locked->forceFill(['status' => 'client_confirmed', 'client_confirmed_at' => now()])->save();
            B2bOrderEvent::create([
                'b2b_order_id' => $locked->id,
                'actor_id' => $actor->id,
                'event' => 'client_confirmed',
                'from_status' => 'awaiting_client',
                'to_status' => 'client_confirmed',
                'comment' => 'Партнёр подтвердил состав, цены и условия заказа.',
            ]);

            return $locked->refresh();
        });
    }
}

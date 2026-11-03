<?php

namespace App\Services\B2bOrders;

use App\Models\B2bOrder;
use App\Models\B2bOrderEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

    /** Stages the client hears about by e-mail; the invoice and cancellation letters are sent separately. */
    public const CLIENT_LETTERS = [
        'checking' => 'accepted',
        'paid' => 'paid',
        'assembling' => 'assembling',
        'ready_to_ship' => 'ready_to_ship',
        'shipped' => 'shipped',
        'completed' => 'completed',
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

    /**
     * Every line is decided: the order goes to the client for approval with
     * the stock already reserved. The client gets a letter with the lines.
     */
    public function sendToClient(B2bOrder $order, User $manager, ?string $message = null): B2bOrder
    {
        abort_unless($manager->canManageB2bOrders(), 403);

        $order = DB::transaction(function () use ($order, $manager, $message): B2bOrder {
            $locked = B2bOrder::query()->lockForUpdate()->with('items')->findOrFail($order->id);
            if (! in_array($locked->status, ['checking', 'awaiting_supplier', 'proposal_ready'], true)) {
                throw ValidationException::withMessages(['status' => 'Отправить клиенту можно только после проверки наличия.']);
            }
            if (! $locked->assigned_manager_id) {
                $locked->forceFill(['assigned_manager_id' => $manager->id])->save();
            }
            if ($locked->status !== 'proposal_ready') {
                $locked = $this->transition($locked, 'proposal_ready', $manager, 'Наличие всех позиций подтверждено.');
            }
            if (trim((string) $message) !== '') {
                $locked->comments()->create(['author_id' => $manager->id, 'visible_to_client' => true, 'body' => trim($message)]);
            }

            return $this->transition($locked, 'awaiting_client', $manager, 'Условия отправлены клиенту на подтверждение.');
        });

        app(B2bOrderNotifier::class)->client($order->load('items'), 'proposal', $message);

        return $order;
    }

    public function markPaid(B2bOrder $order, User $actor, ?string $comment = null): B2bOrder
    {
        return $this->transition($order, 'paid', $actor, $comment ?: 'Оплата получена.');
    }

    /**
     * Money for a deferred-payment order came in. The order already went to the
     * warehouse on credit, so only the payment status changes: the credit limit
     * is freed and the order counts as paid.
     */
    public function markCreditPaid(B2bOrder $order, User $actor, ?string $comment = null): B2bOrder
    {
        abort_unless($actor->canManageB2bOrders(), 403);

        $order = DB::transaction(function () use ($order, $actor, $comment): B2bOrder {
            $locked = B2bOrder::query()->lockForUpdate()->with('items')->findOrFail($order->id);
            if (! self::awaitsCreditPayment($locked)) {
                throw ValidationException::withMessages(['payment_status' => 'Заказ не ждёт оплаты по отсрочке.']);
            }
            $from = $locked->payment_status;
            $locked->forceFill(['payment_status' => 'paid'])->save();
            B2bOrderEvent::create([
                'b2b_order_id' => $locked->id,
                'actor_id' => $actor->id,
                'event' => 'payment_received',
                'comment' => trim((string) $comment) !== '' ? 'Оплата по отсрочке получена: '.trim($comment) : 'Оплата по отсрочке получена.',
                'metadata' => ['from_payment_status' => $from],
            ]);

            return $locked->refresh();
        });
        app(B2bOrderNotifier::class)->client($order, 'credit_paid');

        return $order;
    }

    public static function awaitsCreditPayment(B2bOrder $order): bool
    {
        return $order->payment_type === 'invoice_deferred'
            && in_array($order->payment_status, ['credit', 'overdue'], true)
            && in_array($order->status, ['credit_approved', 'assembling', 'ready_to_ship', 'shipped', 'completed'], true);
    }

    /** Warehouse steps: assembling → ready_to_ship → shipped → completed. */
    public function advanceFulfilment(B2bOrder $order, User $actor): B2bOrder
    {
        $next = match ($order->status) {
            'paid', 'credit_approved' => ['assembling', 'Передан на склад в комплектацию.'],
            'assembling' => ['ready_to_ship', 'Заказ собран и готов к отгрузке.'],
            'ready_to_ship' => ['shipped', 'Заказ отгружен.'],
            'shipped' => ['completed', 'Заказ закрыт.'],
            default => throw ValidationException::withMessages(['status' => 'Для этого этапа нет складского действия.']),
        };
        return $this->transition($order, $next[0], $actor, $next[1]);
    }

    public function cancel(B2bOrder $order, User $actor, string $reason): B2bOrder
    {
        $order = DB::transaction(function () use ($order, $actor, $reason): B2bOrder {
            $order = $this->transition($order, 'cancelled', $actor, $reason);
            $order->comments()->create(['author_id' => $actor->id, 'visible_to_client' => true, 'body' => 'Заказ отменён: '.trim($reason)]);

            return $order;
        });
        app(B2bOrderNotifier::class)->client($order, 'cancelled', $reason);

        return $order;
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

        $order = DB::transaction(function () use ($order, $status, $actor, $comment): B2bOrder {
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
        if (isset(self::CLIENT_LETTERS[$status])) {
            app(B2bOrderNotifier::class)->client($order, self::CLIENT_LETTERS[$status]);
        }

        return $order;
    }

    /**
     * The client agrees: the invoice is issued at once on behalf of the order's
     * manager, so the PDF is in the cabinet right after the click. If issuing
     * fails, the order stays confirmed and the manager issues it by hand.
     */
    public function clientConfirm(B2bOrder $order, User $actor): B2bOrder
    {
        $order = $this->recordClientConfirmation($order, $actor);
        $issuer = User::query()->find($order->assigned_manager_id ?: $order->accepted_by);
        if (! $issuer?->is_active || ! $issuer->canManageB2bOrders()) {
            app(B2bOrderNotifier::class)->manager($order, 'Клиент подтвердил заказ', 'Выставите счёт вручную: у заказа нет активного B2B-менеджера.');

            return $order;
        }

        try {
            $document = app(B2bOrderInvoice::class)->issue($order, $issuer);
            app(B2bOrderNotifier::class)->manager($order->refresh(), 'Клиент подтвердил заказ',
                'Счёт '.$document->number.' выставлен автоматически и отправлен клиенту. Ждём оплату.');
        } catch (\Throwable $exception) {
            $reason = $exception instanceof ValidationException
                ? collect($exception->errors())->flatten()->implode(' ')
                : $exception->getMessage();
            Log::error('B2B automatic invoice failed.', ['b2b_order_id' => $order->id, 'exception' => $reason]);
            B2bOrderEvent::create([
                'b2b_order_id' => $order->id,
                'event' => 'invoice_failed',
                'comment' => 'Счёт не сформировался автоматически: '.mb_substr($reason, 0, 500),
            ]);
            app(B2bOrderNotifier::class)->manager($order, 'Клиент подтвердил заказ', 'Счёт не сформировался автоматически: '.mb_substr($reason, 0, 200));
        }

        return $order->refresh();
    }

    private function recordClientConfirmation(B2bOrder $order, User $actor): B2bOrder
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

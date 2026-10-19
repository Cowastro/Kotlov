<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderPortalEvent;
use App\Models\OrderPortalLink;
use App\Models\OrderPortalSession;
use App\Models\User;
use chillerlan\QRCode\QRCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class OrderPortal
{
    public function publish(Order $order, ?User $user = null): OrderPortalLink
    {
        if ($order->status === 'completed'
            || $order->status === 'cancelled'
            || ($order->status === 'delivered' && $order->payment_status === 'paid')) {
            throw new RuntimeException('Для завершённого или отменённого заказа нельзя создать новую ссылку.');
        }

        $active = $order->portalLinks()
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if ($active) {
            $maximumExpiry = $active->created_at->copy()->addDays(15);
            if ($active->access_email !== null || $active->expires_at->gt($maximumExpiry)) {
                $active->forceFill([
                    'access_email' => null,
                    'expires_at' => $active->expires_at->min($maximumExpiry),
                ])->save();
            }

            return $active;
        }

        $token = Str::random(80);
        $link = $order->portalLinks()->create([
            'public_id' => (string) Str::uuid(),
            'token_hash' => hash('sha256', $token),
            'token_encrypted' => $token,
            'access_email' => null,
            'created_by' => $user?->id,
            'expires_at' => now()->addDays(15),
        ]);
        $this->event($link, null, 'link_created');

        return $link;
    }

    public function url(OrderPortalLink $link): string
    {
        return rtrim((string) config('app.url'), '/').'/order-view/access/'.rawurlencode($link->token_encrypted);
    }

    public function qrDataUri(OrderPortalLink $link): ?string
    {
        if (! class_exists(QRCode::class)) {
            return null;
        }

        try {
            return (new QRCode)->render($this->url($link));
        } catch (\Throwable) {
            return null;
        }
    }

    public function printUrl(OrderPortalLink $link): string
    {
        return rtrim((string) config('app.url'), '/').'/order-view/access/'
            .rawurlencode($link->token_encrypted).'/print';
    }

    public function resolveToken(string $token): OrderPortalLink
    {
        $link = OrderPortalLink::query()
            ->with('order')
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();

        if (! $link->isUsable()) {
            throw new RuntimeException('Срок действия ссылки истёк или доступ был отозван.');
        }

        return $link;
    }

    public function event(
        OrderPortalLink $link,
        ?OrderPortalSession $session,
        string $type,
        ?Request $request = null,
        array $metadata = [],
    ): void {
        OrderPortalEvent::query()->create([
            'order_id' => $link->order_id,
            'portal_link_id' => $link->id,
            'portal_session_id' => $session?->id,
            'type' => $type,
            'ip_hash' => $request ? $this->ipHash($request->ip()) : null,
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 500, '') : null,
            'metadata' => $metadata ?: null,
            'occurred_at' => now(),
        ]);
    }

    public function revoke(OrderPortalLink $link, array $metadata = []): void
    {
        if ($link->revoked_at) {
            return;
        }

        $link->forceFill(['revoked_at' => now()])->save();
        $link->sessions()->whereNull('revoked_at')->update(['revoked_at' => now()]);
        $this->event($link, null, 'link_revoked', metadata: $metadata);
    }

    public function performAction(
        OrderPortalLink $link,
        string $action,
        string $actor,
        Request $request,
        array $details = [],
    ): bool
    {
        $actor = trim($actor);
        if ($actor === '') {
            throw new RuntimeException('Укажите имя сотрудника, который выполняет действие.');
        }

        return DB::transaction(function () use ($link, $action, $actor, $request, $details): bool {
            $order = Order::query()->lockForUpdate()->findOrFail($link->order_id);
            $fromStatus = $order->status;
            $fromPayment = $order->payment_status;
            $label = '';
            $eventDetails = [];

            if ($action === 'accept_delivery') {
                if (! in_array($order->status, ['confirmed', 'processing'], true)) {
                    throw new RuntimeException('Передать в доставку можно только подтверждённый заказ или заказ в обработке.');
                }
                $label = 'Принят в доставку';
                $order->transitionTo('shipped', 'Онлайн-заказ: '.$actor.' — '.$label.'.');
            } elseif ($action === 'delivered_paid') {
                if ($order->status !== 'shipped') {
                    throw new RuntimeException('Сначала заказ должен быть принят в доставку.');
                }
                $label = 'Доставлен и оплачен';
                $order->transitionTo('delivered', 'Онлайн-заказ: '.$actor.' — '.$label.'.', [
                    'payment_status' => 'paid',
                ]);
            } elseif ($action === 'add_comment') {
                $categories = [
                    'no_stock' => 'Нет в наличии',
                    'delivery' => 'Доставка',
                    'general' => 'Комментарий офиса',
                ];
                $category = (string) ($details['comment_category'] ?? 'general');
                $note = trim((string) ($details['note'] ?? ''));
                if (! isset($categories[$category]) || $note === '') {
                    throw new RuntimeException('Выберите тип отметки и напишите комментарий.');
                }

                $label = $categories[$category];
                $plannedDate = filled($details['planned_delivery_date'] ?? null)
                    ? (string) $details['planned_delivery_date']
                    : null;
                $timezone = config('app.display_timezone', 'Europe/Minsk');
                $adminNote = '['.now()->timezone($timezone)->format('d.m.Y H:i').'] '
                    .$actor.' — '.$label.': '.$note;
                if ($plannedDate) {
                    $adminNote .= ' Плановая доставка: '
                        .\Illuminate\Support\Carbon::parse($plannedDate)->format('d.m.Y').'.';
                }
                $adminComment = filled($order->admin_comment)
                    ? rtrim((string) $order->admin_comment)."\n\n".$adminNote
                    : $adminNote;
                $order->forceFill([
                    'admin_comment' => $adminComment,
                    'planned_delivery_date' => $plannedDate ?: $order->planned_delivery_date,
                ])->save();
                $eventDetails = [
                    'comment_category' => $category,
                    'note' => $note,
                    'planned_delivery_date' => $plannedDate,
                ];
            } else {
                throw new RuntimeException('Неизвестное действие.');
            }

            $order->refresh();
            $completed = $order->status === 'completed'
                || ($order->status === 'delivered' && $order->payment_status === 'paid');
            $this->event($link, null, 'order_action', $request, [
                'actor' => $actor,
                'action' => $action,
                'label' => $label,
                'status_from' => $fromStatus,
                'status_to' => $order->status,
                'payment_from' => $fromPayment,
                'payment_to' => $order->payment_status,
                'completed' => $completed,
                'actor_user_id' => $details['actor_user_id'] ?? null,
            ] + $eventDetails);

            return $completed;
        });
    }

    private function ipHash(?string $ip): ?string
    {
        return $ip ? hash_hmac('sha256', $ip, (string) config('app.key')) : null;
    }

}

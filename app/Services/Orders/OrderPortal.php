<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderPortalEvent;
use App\Models\OrderPortalLink;
use App\Models\OrderPortalSession;
use App\Models\User;
use chillerlan\QRCode\QRCode;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class OrderPortal
{
    public function publish(Order $order, ?User $user = null): OrderPortalLink
    {
        if (in_array($order->status, ['delivered', 'completed', 'cancelled'], true)) {
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

    private function ipHash(?string $ip): ?string
    {
        return $ip ? hash_hmac('sha256', $ip, (string) config('app.key')) : null;
    }

}

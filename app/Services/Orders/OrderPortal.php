<?php

namespace App\Services\Orders;

use App\Mail\OrderAccessCodeMail;
use App\Models\Order;
use App\Models\OrderPortalChallenge;
use App\Models\OrderPortalEvent;
use App\Models\OrderPortalLink;
use App\Models\OrderPortalSession;
use App\Models\User;
use chillerlan\QRCode\QRCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

class OrderPortal
{
    public function publish(Order $order, string $accessEmail, ?User $user = null): OrderPortalLink
    {
        $accessEmail = mb_strtolower(trim($accessEmail));
        if (! filter_var($accessEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Укажите корректный email офиса или отдела закупок.');
        }

        $active = $order->portalLinks()
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if ($active) {
            if ($active->access_email !== $accessEmail) {
                $active->forceFill(['access_email' => $accessEmail])->save();
            }

            return $active;
        }

        $token = Str::random(80);
        $link = $order->portalLinks()->create([
            'public_id' => (string) Str::uuid(),
            'token_hash' => hash('sha256', $token),
            'token_encrypted' => $token,
            'access_email' => $accessEmail,
            'created_by' => $user?->id,
            'expires_at' => now()->addDays(30),
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

    public function activateFirstDevice(OrderPortalLink $link, Request $request): ?OrderPortalSession
    {
        if ($link->sessions()->whereNull('revoked_at')->where('expires_at', '>', now())->exists()) {
            return null;
        }

        $session = $this->createDeviceSession($link, $request);
        $link->forceFill(['first_activated_at' => $link->first_activated_at ?: now()])->save();
        $this->event($link, $session, 'first_device_activated', $request);

        return $session;
    }

    public function requestCode(OrderPortalLink $link, Request $request): OrderPortalChallenge
    {
        $code = (string) random_int(100000, 999999);
        $challenge = $link->challenges()->create([
            'public_id' => (string) Str::uuid(),
            'code_hash' => $this->codeHash($link, $code),
            'expires_at' => now()->addMinutes(10),
        ]);

        Mail::to($link->access_email)->send(new OrderAccessCodeMail($link->order->number, $code));
        $this->event($link, null, 'verification_code_sent', $request);

        return $challenge;
    }

    public function verifyCode(OrderPortalChallenge $challenge, string $code, Request $request): OrderPortalSession
    {
        $challenge->loadMissing('link.order');
        if ($challenge->consumed_at || $challenge->expires_at->isPast() || $challenge->attempts >= 5) {
            throw new RuntimeException('Код просрочен. Запросите новый код.');
        }

        $challenge->increment('attempts');
        if (! hash_equals($challenge->code_hash, $this->codeHash($challenge->link, trim($code)))) {
            throw new RuntimeException('Неверный код подтверждения.');
        }

        $challenge->forceFill(['consumed_at' => now()])->save();
        $session = $this->createDeviceSession($challenge->link, $request);
        $this->event($challenge->link, $session, 'device_verified', $request);

        return $session;
    }

    public function authorizedSession(OrderPortalLink $link, Request $request): ?OrderPortalSession
    {
        $secret = $request->session()->get($this->sessionKey($link));
        if (! is_string($secret) || $secret === '') {
            return null;
        }

        $session = $link->sessions()
            ->where('token_hash', hash('sha256', $secret))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($session) {
            $session->forceFill(['last_seen_at' => now()])->save();
        }

        return $session;
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

    public function revokeSession(OrderPortalSession $session, ?User $user = null): void
    {
        if ($session->revoked_at) {
            return;
        }

        $session->loadMissing('link');
        $session->forceFill(['revoked_at' => now()])->save();
        $this->event($session->link, $session, 'device_revoked', metadata: [
            'revoked_by' => $user?->id,
            'device_label' => $session->device_label,
        ]);
    }

    private function createDeviceSession(OrderPortalLink $link, Request $request): OrderPortalSession
    {
        $secret = Str::random(80);
        $session = $link->sessions()->create([
            'token_hash' => hash('sha256', $secret),
            'device_label' => $this->deviceLabel((string) $request->userAgent()),
            'ip_hash' => $this->ipHash($request->ip()),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'authorized_at' => now(),
            'last_seen_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
        $request->session()->put($this->sessionKey($link), $secret);

        return $session;
    }

    private function sessionKey(OrderPortalLink $link): string
    {
        return 'order_portal.devices.'.$link->public_id;
    }

    private function codeHash(OrderPortalLink $link, string $code): string
    {
        return hash_hmac('sha256', $link->id.'|'.$code, (string) config('app.key'));
    }

    private function ipHash(?string $ip): ?string
    {
        return $ip ? hash_hmac('sha256', $ip, (string) config('app.key')) : null;
    }

    private function deviceLabel(string $userAgent): string
    {
        $device = preg_match('/Mobile|Android|iPhone/i', $userAgent) ? 'Телефон' : 'Компьютер';
        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'браузер',
        };

        return $device.' · '.$browser;
    }
}

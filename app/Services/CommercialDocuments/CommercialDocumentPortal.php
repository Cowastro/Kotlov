<?php

namespace App\Services\CommercialDocuments;

use App\Models\CommercialDocument;
use App\Models\CommercialDocumentItem;
use App\Models\CommercialDocumentPortalChallenge;
use App\Models\CommercialDocumentPortalEvent;
use App\Models\CommercialDocumentPortalLink;
use App\Models\CommercialDocumentPortalSession;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

class CommercialDocumentPortal
{
    public function publish(CommercialDocument $document, ?User $user = null): CommercialDocumentPortalLink
    {
        if ($document->type !== 'quote') {
            throw new RuntimeException('Онлайн-доступ создаётся только для коммерческого предложения.');
        }
        if (! filter_var((string) $document->client_email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Для онлайн-доступа нужен корректный email клиента.');
        }
        if ($document->valid_until?->isPast()) {
            throw new RuntimeException('Нельзя опубликовать предложение с истёкшим сроком действия.');
        }

        $active = $document->portalLinks()
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if ($active) {
            $this->snapshot($document);

            return $active;
        }

        $token = Str::random(80);
        $expiresAt = now()->addDays(7);
        if ($document->valid_until && $document->valid_until->endOfDay()->lt($expiresAt)) {
            $expiresAt = $document->valid_until->endOfDay();
        }

        $link = $document->portalLinks()->create([
            'public_id' => (string) Str::uuid(),
            'token_hash' => hash('sha256', $token),
            'token_encrypted' => $token,
            'created_by' => $user?->id,
            'expires_at' => $expiresAt,
        ]);

        $this->snapshot($document);
        $this->event($link, null, 'link_created');

        return $link;
    }

    public function url(CommercialDocumentPortalLink $link): string
    {
        return rtrim((string) config('app.url'), '/').'/offer/access/'.rawurlencode($link->token_encrypted);
    }

    public function resolveToken(string $token): CommercialDocumentPortalLink
    {
        $link = CommercialDocumentPortalLink::query()
            ->with('document.items.product')
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();

        if (! $link->isUsable()) {
            throw new RuntimeException('Срок действия ссылки истёк или доступ был отозван.');
        }

        return $link;
    }

    public function activateFirstDevice(CommercialDocumentPortalLink $link, Request $request): ?CommercialDocumentPortalSession
    {
        if ($link->sessions()->whereNull('revoked_at')->where('expires_at', '>', now())->exists()) {
            return null;
        }

        $session = $this->createDeviceSession($link, $request);
        $link->forceFill(['first_activated_at' => $link->first_activated_at ?: now()])->save();
        $this->event($link, $session, 'first_device_activated', $request);

        return $session;
    }

    public function requestCode(CommercialDocumentPortalLink $link, Request $request): CommercialDocumentPortalChallenge
    {
        $code = (string) random_int(100000, 999999);
        $challenge = $link->challenges()->create([
            'public_id' => (string) Str::uuid(),
            'code_hash' => $this->codeHash($link, $code),
            'expires_at' => now()->addMinutes(10),
        ]);

        Mail::raw(
            "Код подтверждения для предложения {$link->document->number}: {$code}\n\nКод действует 10 минут. Если вы не запрашивали доступ, проигнорируйте письмо.",
            fn ($message) => $message
                ->to($link->document->client_email)
                ->subject('Код доступа к предложению '.$link->document->number)
        );
        $this->event($link, null, 'verification_code_sent', $request);

        return $challenge;
    }

    public function verifyCode(CommercialDocumentPortalChallenge $challenge, string $code, Request $request): CommercialDocumentPortalSession
    {
        $challenge->loadMissing('link.document');
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

    public function authorizedSession(CommercialDocumentPortalLink $link, Request $request): ?CommercialDocumentPortalSession
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
        CommercialDocumentPortalLink $link,
        ?CommercialDocumentPortalSession $session,
        string $type,
        ?Request $request = null,
        array $metadata = [],
    ): void {
        CommercialDocumentPortalEvent::query()->create([
            'commercial_document_id' => $link->commercial_document_id,
            'portal_link_id' => $link->id,
            'portal_session_id' => $session?->id,
            'type' => $type,
            'ip_hash' => $request ? $this->ipHash($request->ip()) : null,
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 500, '') : null,
            'metadata' => $metadata ?: null,
            'occurred_at' => now(),
        ]);
    }

    public function revoke(CommercialDocumentPortalLink $link): void
    {
        $link->forceFill(['revoked_at' => now()])->save();
        $link->sessions()->whereNull('revoked_at')->update(['revoked_at' => now()]);
        $this->event($link, null, 'link_revoked');
    }

    public function refreshSnapshot(CommercialDocument $document): void
    {
        if ($document->type !== 'quote') {
            throw new RuntimeException('Онлайн-доступ создаётся только для коммерческого предложения.');
        }

        $this->snapshot($document, true);

        $link = $document->portalLinks()
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if ($link) {
            $this->event($link, null, 'snapshot_refreshed');
        }
    }

    public function ensureSnapshot(CommercialDocument $document): void
    {
        $this->snapshot($document);
    }

    private function createDeviceSession(CommercialDocumentPortalLink $link, Request $request): CommercialDocumentPortalSession
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

    private function snapshot(CommercialDocument $document, bool $force = false): void
    {
        $document->load('items.product.attributeValues.attribute', 'items.product.attributeValues.option', 'items.product.category', 'items.product.brand');
        foreach ($document->items as $item) {
            if (! $force && $item->portal_snapshot) {
                continue;
            }

            $product = $this->resolveCatalogProduct($item);
            if (! $product) {
                continue;
            }

            $brand = trim((string) $product->brand?->name);
            $attributes = $product->attributeValues
                ->filter(fn ($value) => $value->hasDisplayValue())
                ->map(fn ($value) => [
                    'name' => $value->attribute?->name,
                    'value' => $value->display_value,
                ])
                ->filter(fn ($value) => filled($value['name']))
                ->reject(fn ($value) => mb_strtolower(trim((string) $value['name'])) === 'бренд')
                ->values();

            if ($brand !== '') {
                $attributes->prepend(['name' => 'Бренд', 'value' => $brand]);
            }

            $item->forceFill([
                'product_id' => $product->id,
                'portal_snapshot' => [
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'short_description' => $product->short_description,
                    'content' => $product->content,
                    'images' => collect($product->imagePaths())->take(4)->map(
                        fn ($path, $index) => $product->imageUrl((int) $index)
                    )->values()->all(),
                    'specs' => is_array($product->specs) ? $product->specs : [],
                    'brand' => $brand ?: null,
                    'attributes' => $attributes->all(),
                    'warranty' => $product->warranty,
                    'availability' => $product->availabilityLabel(),
                    'catalog_url' => $product->category?->slug && $product->slug
                        ? url('/'.$product->category->slug.'/'.$product->slug)
                        : null,
                    'captured_at' => now()->toIso8601String(),
                ],
            ])->saveQuietly();
        }
    }

    private function resolveCatalogProduct(CommercialDocumentItem $item): ?Product
    {
        if ($item->product) {
            return $item->product;
        }

        $sku = trim((string) $item->sku);
        if ($sku === '') {
            return null;
        }

        $products = Product::query()
            ->with(['attributeValues.attribute', 'attributeValues.option', 'category', 'brand'])
            ->where('sku', $sku)
            ->where('is_active', true)
            ->where('is_archived', false)
            ->limit(3)
            ->get();

        if ($products->count() === 1) {
            return $products->first();
        }

        $itemName = mb_strtolower(trim((string) $item->name));

        return $products->first(
            fn (Product $product): bool => mb_strtolower(trim((string) $product->name)) === $itemName
        );
    }

    private function sessionKey(CommercialDocumentPortalLink $link): string
    {
        return 'commercial_portal.devices.'.$link->public_id;
    }

    private function codeHash(CommercialDocumentPortalLink $link, string $code): string
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

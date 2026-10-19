<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderPortalChallenge;
use App\Models\OrderPortalLink;
use App\Services\Orders\OrderPortal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class OrderPortalController extends Controller
{
    public function preview(Order $order)
    {
        $link = $order->portalLinks()
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->firstOrFail();

        return $this->orderView($link, null, true);
    }

    public function access(string $token, OrderPortal $portal)
    {
        try {
            $link = $portal->resolveToken($token);
        } catch (Throwable $exception) {
            return $this->securedView('order-portal.unavailable', ['message' => $exception->getMessage()], 410);
        }

        return $this->securedView('order-portal.activate', [
            'link' => $link,
            'token' => $token,
            'hasDevices' => $link->sessions()->whereNull('revoked_at')->where('expires_at', '>', now())->exists(),
        ]);
    }

    public function activate(string $token, Request $request, OrderPortal $portal): RedirectResponse
    {
        try {
            $link = $portal->resolveToken($token);
            if ($portal->authorizedSession($link, $request) || $portal->activateFirstDevice($link, $request)) {
                return redirect()->route('order-portal.show', $link->public_id);
            }

            $challenge = $portal->requestCode($link, $request);

            return redirect()->route('order-portal.verify-form', $challenge->public_id);
        } catch (Throwable $exception) {
            return back()->withErrors(['access' => $exception->getMessage()]);
        }
    }

    public function verifyForm(OrderPortalChallenge $challenge)
    {
        $challenge->loadMissing('link.order');

        return $this->securedView('order-portal.verify', ['challenge' => $challenge]);
    }

    public function verify(OrderPortalChallenge $challenge, Request $request, OrderPortal $portal): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);

        try {
            $portal->verifyCode($challenge, $data['code'], $request);

            return redirect()->route('order-portal.show', $challenge->link->public_id);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['code' => $exception->getMessage()]);
        }
    }

    public function show(OrderPortalLink $link, Request $request, OrderPortal $portal)
    {
        $link->loadMissing('order');
        if (! $link->isUsable()) {
            abort(410, 'Доступ к заказу недоступен.');
        }

        $session = $portal->authorizedSession($link, $request);
        if (! $session) {
            abort(403, 'Это устройство не подтверждено. Откройте исходную ссылку или QR-код.');
        }

        $link->increment('open_count');
        $link->forceFill(['last_opened_at' => now()])->save();
        $portal->event($link, $session, 'order_viewed', $request);

        return $this->orderView($link, $session, false);
    }

    private function orderView(OrderPortalLink $link, mixed $session, bool $preview)
    {
        $link->loadMissing([
            'order.manager',
            'order.correctedBy',
            'order.items.product.category',
            'order.items.fulfillmentSupplier.crmCounterparty.contactPoints',
            'order.items.supplySupplier.crmCounterparty.contactPoints',
            'order.items.fulfillmentConfirmedBy',
            'order.items.supplierOrderRequestItem.request',
        ]);

        return $this->securedView('order-portal.show', [
            'link' => $link,
            'order' => $link->order,
            'session' => $session,
            'preview' => $preview,
        ]);
    }

    private function securedView(string $view, array $data, int $status = 200)
    {
        return response()->view($view, $data, $status)
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}

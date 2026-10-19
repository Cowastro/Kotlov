<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderPortalLink;
use App\Services\Orders\OrderPortal;
use Illuminate\Http\Request;
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

    public function access(string $token, Request $request, OrderPortal $portal)
    {
        try {
            $link = $portal->resolveToken($token);
        } catch (Throwable $exception) {
            return $this->securedView('order-portal.unavailable', ['message' => $exception->getMessage()], 410);
        }

        $link->increment('open_count');
        $link->forceFill(['last_opened_at' => now()])->save();
        $portal->event($link, null, 'order_viewed', $request);

        return $this->orderView($link, null, false);
    }

    public function print(string $token, Request $request, OrderPortal $portal)
    {
        try {
            $link = $portal->resolveToken($token);
        } catch (Throwable $exception) {
            return $this->securedView('order-portal.unavailable', ['message' => $exception->getMessage()], 410);
        }

        $link->loadMissing([
            'order.manager',
            'order.correctedBy',
            'order.items.product',
            'order.items.fulfillmentSupplier.crmCounterparty.contactPoints',
            'order.items.supplySupplier.crmCounterparty.contactPoints',
            'order.items.fulfillmentConfirmedBy',
            'order.items.supplierOrderRequestItem.request',
        ]);
        $portal->event($link, null, 'order_print_viewed', $request);

        return $this->securedView('order-portal.print', [
            'link' => $link,
            'order' => $link->order,
        ]);
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

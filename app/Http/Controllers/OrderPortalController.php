<?php

namespace App\Http\Controllers;

use App\Models\Order;
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
            'order.portalEvents' => fn ($query) => $query->where('type', 'order_action')->limit(20),
        ]);
        $portal->event($link, null, 'order_print_viewed', $request);

        return $this->securedView('order-portal.print', [
            'link' => $link,
            'order' => $link->order,
            'qr' => $portal->qrDataUri($link),
        ]);
    }

    public function action(string $token, Request $request, OrderPortal $portal): RedirectResponse|\Illuminate\Http\Response
    {
        $data = $request->validate([
            'actor' => ['required', 'string', 'max:100'],
            'workflow_action' => ['required', 'in:accept_delivery,delivered_paid,add_comment'],
            'comment_category' => ['required_if:workflow_action,add_comment', 'nullable', 'in:no_stock,delivery,general'],
            'note' => ['required_if:workflow_action,add_comment', 'nullable', 'string', 'max:2000'],
            'planned_delivery_date' => ['nullable', 'date'],
        ]);

        try {
            $link = $portal->resolveToken($token);
            $completed = $portal->performAction(
                $link,
                $data['workflow_action'],
                $data['actor'],
                $request,
                $data,
            );
            $request->session()->put('order_portal.actor.'.$link->public_id, trim($data['actor']));

            if ($completed) {
                return $this->securedView('order-portal.finished', [
                    'order' => $link->order()->firstOrFail(),
                    'actor' => trim($data['actor']),
                ]);
            }

            return redirect($portal->url($link))->with('portal_success', 'Статус заказа обновлён.');
        } catch (RuntimeException $exception) {
            return back()->withErrors(['workflow_action' => $exception->getMessage()]);
        }
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
            'order.portalEvents' => fn ($query) => $query->where('type', 'order_action')->limit(20),
        ]);

        return $this->securedView('order-portal.show', [
            'link' => $link,
            'order' => $link->order,
            'session' => $session,
            'preview' => $preview,
            'actor' => $preview ? '' : request()->session()->get('order_portal.actor.'.$link->public_id, ''),
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

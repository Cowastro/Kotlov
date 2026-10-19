<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderProcurementController extends Controller
{
    public function __invoke(Request $request, Order $order): Response
    {
        abort_unless($request->user()?->isManager(), 403);

        $order->load([
            'manager',
            'correctedBy',
            'procurementSentBy',
            'items.product',
            'items.fulfillmentSupplier.crmCounterparty.contactPoints',
            'items.supplySupplier.crmCounterparty.contactPoints',
            'items.fulfillmentConfirmedBy',
            'items.supplierOrderRequestItem.request',
        ]);

        return response()
            ->view('admin.orders.procurement', compact('order'))
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}

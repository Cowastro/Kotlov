<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\B2bCompanyAccess;
use App\Services\Payments\WebpayService;
use Illuminate\Http\Request;

class AccountOrderController extends Controller
{
    public function show(Request $request, Order $order, WebpayService $webpay, B2bCompanyAccess $access)
    {
        $ownsOrder = (int) $order->user_id === (int) $request->user()->id;
        $client = $request->user()->activeB2bClient();
        $companyCanView = $client
            && $access->allows($request->user(), 'orders.view', $client)
            && $access->allUserIds($client)->contains((int) $order->user_id);
        abort_unless($ownsOrder || $companyCanView, 404);
        abort_unless($order->customerDetailsAreAvailable(), 404);

        $order->loadMissing('items');

        return view('pages.account-order', [
            'order' => $order,
            'webpayCanPay' => $webpay->canPay($order),
            'webpayConfigured' => $webpay->configured(),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Payments\WebpayService;
use Illuminate\Http\Request;

class AccountOrderController extends Controller
{
    public function show(Request $request, Order $order, WebpayService $webpay)
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 404);
        abort_unless($order->customerDetailsAreAvailable(), 404);

        $order->loadMissing('items');

        return view('pages.account-order', [
            'order' => $order,
            'webpayCanPay' => $webpay->canPay($order),
            'webpayConfigured' => $webpay->configured(),
        ]);
    }
}

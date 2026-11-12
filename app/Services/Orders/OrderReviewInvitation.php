<?php

namespace App\Services\Orders;

use App\Models\Order;
use Illuminate\Support\Facades\URL;

final class OrderReviewInvitation
{
    public function url(Order $order): string
    {
        return rtrim(config('app.url'), '/').URL::signedRoute(
            'orders.review.show', ['order' => $order->id], absolute: false,
        );
    }
}

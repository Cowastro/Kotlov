<?php

namespace App\Listeners;

use App\Events\NewOrderCreated;
use Illuminate\Support\Facades\Log;

class HandleNewOrderCreated
{
    public function handle(NewOrderCreated $event): void
    {
        $order = $event->order;

        Log::channel('single')->info('Новый заказ создан', [
            'number' => $order->number,
            'total'  => $order->total . ' BYN',
            'phone'  => $order->customer_phone,
            'name'   => $order->customer_name,
            'items'  => $order->items->count(),
        ]);
    }
}

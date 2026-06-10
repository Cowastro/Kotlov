<?php

namespace App\Listeners;

use App\Events\NewOrderCreated;
use App\Models\User;
use Filament\Notifications\Notification;

class NotifyAdminOnNewOrder
{
    public function handle(NewOrderCreated $event): void
    {
        $order = $event->order;

        $admins = User::where('role', 'admin')->get();

        if ($admins->isEmpty()) {
            return;
        }

        $title = "Новый заказ {$order->number}";
        $body  = "{$order->customer_name} · {$order->customer_phone} · {$order->total} BYN";

        Notification::make()
            ->title($title)
            ->body($body)
            ->icon('heroicon-o-shopping-bag')
            ->iconColor('warning')
            ->sendToDatabase($admins);
    }
}

<?php

namespace App\Listeners;

use App\Events\NewOrderCreated;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifyAdminOnNewOrder implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public function handle(NewOrderCreated $event): void
    {
        $order = $event->order;

        $recipients = User::query()
            ->whereIn('role', ['admin', 'manager', 'sales_manager'])
            ->where('is_active', true)
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $requiresStockConfirmation = $order->items
            ->contains(fn ($item): bool => $item->supply_is_available !== true)
            || $order->payment_type === 'invoice';
        $requiresInstallmentFollowUp = $order->payment_type === 'installment_6';
        $title = "Новый заказ {$order->number}";
        if ($requiresStockConfirmation) {
            $title .= ' — подтвердить наличие';
        }
        if ($requiresInstallmentFollowUp) {
            $title = "Заявка на рассрочку {$order->number}";
        }
        if ($order->payment_type === 'invoice') {
            $title = "Запрос счёта {$order->number} — проверить наличие";
        }
        $body = "{$order->customer_name} · {$order->customer_phone} · {$order->total} BYN";
        if ($requiresInstallmentFollowUp) {
            $body .= ' · Связаться с клиентом для оформления рассрочки.';
        }
        if ($requiresStockConfirmation) {
            $body .= ' · До подтверждения клиенту проверьте остаток и срок поставки.';
        }
        if ($order->payment_type === 'invoice' && $order->commercialDocument) {
            $body .= ' · Черновик счёта '.$order->commercialDocument->number.' уже сформирован.';
        }

        Notification::make()
            ->title($title)
            ->body($body)
            ->icon('heroicon-o-shopping-bag')
            ->iconColor('warning')
            ->sendToDatabase($recipients);
    }
}

<?php

namespace App\Listeners;

use App\Mail\B2bOrderInvoiceIssuedMail;
use App\Mail\B2bOrderStageMail;
use App\Models\B2bOrder;
use App\Services\B2bOrders\B2bOrderMailLog;
use Illuminate\Mail\Events\MessageSent;
use Symfony\Component\Mime\Address;

class RecordB2bOrderMailSent
{
    public function handle(MessageSent $event): void
    {
        $order = $event->data['order'] ?? null;
        $mailable = $event->data['__laravel_mailable'] ?? null;
        if (! $order instanceof B2bOrder
            || ! in_array($mailable, [B2bOrderStageMail::class, B2bOrderInvoiceIssuedMail::class], true)) {
            return;
        }

        $letter = $mailable === B2bOrderInvoiceIssuedMail::class
            ? 'Счёт'
            : (B2bOrderStageMail::SUBJECTS[$event->data['stage'] ?? ''] ?? 'Этап заказа');
        $recipients = collect($event->message->getTo())
            ->map(fn (Address $address): string => $address->getAddress())
            ->implode(', ');

        app(B2bOrderMailLog::class)->sent($order, $letter, $recipients);
    }
}

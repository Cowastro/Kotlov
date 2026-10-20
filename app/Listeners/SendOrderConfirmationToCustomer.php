<?php

namespace App\Listeners;

use App\Events\NewOrderCreated;
use App\Mail\OrderPlacedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendOrderConfirmationToCustomer implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public int $backoff = 30;

    public function handle(NewOrderCreated $event): void
    {
        $order = $event->order->loadMissing('items');
        $recipient = mb_strtolower(trim((string) $order->customer_email));

        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        Mail::mailer('transactional')
            ->to($recipient)
            ->send(new OrderPlacedMail($order));
    }
}

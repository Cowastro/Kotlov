<?php

namespace App\Services\B2bOrders;

use App\Models\B2bOrder;
use App\Models\B2bOrderEvent;

/**
 * Every letter to the client leaves a line in the order history: queued (or
 * why not), and later sent, when the queue worker actually hands it to SMTP.
 * A "queued" line without a "sent" one means the queue is not processed.
 */
class B2bOrderMailLog
{
    public function queued(B2bOrder $order, string $letter, string $recipient): void
    {
        $this->record($order, 'client_email_queued', 'Письмо «'.$letter.'» поставлено в очередь на '.$recipient.'.', $letter, $recipient);
    }

    public function sent(B2bOrder $order, string $letter, string $recipient): void
    {
        $this->record($order, 'client_email_sent', 'Письмо «'.$letter.'» отправлено на '.$recipient.'.', $letter, $recipient);
    }

    public function skipped(B2bOrder $order, string $letter): void
    {
        $this->record($order, 'client_email_skipped', 'Письмо «'.$letter.'» не отправлено: в заказе нет корректного e-mail компании или контакта.', $letter, null);
    }

    public function failed(B2bOrder $order, string $letter, string $recipient, string $error): void
    {
        $this->record($order, 'client_email_failed', 'Письмо «'.$letter.'» на '.$recipient.' не отправлено: '.mb_substr($error, 0, 300), $letter, $recipient);
    }

    private function record(B2bOrder $order, string $event, string $comment, string $letter, ?string $recipient): void
    {
        try {
            B2bOrderEvent::query()->create([
                'b2b_order_id' => $order->id,
                'event' => $event,
                'comment' => $comment,
                'metadata' => ['letter' => $letter, 'recipient' => $recipient],
            ]);
        } catch (\Throwable) {
            // The history line must never break the letter itself.
        }
    }
}

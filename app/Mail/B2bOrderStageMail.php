<?php

namespace App\Mail;

use App\Models\B2bOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

/** Client-facing letter for a B2B order stage: proposal, shipment or cancellation. */
class B2bOrderStageMail extends Mailable
{
    use Queueable, SerializesModels;

    public const SUBJECTS = [
        'proposal' => 'Наличие подтверждено, товар в резерве',
        'shipped' => 'Заказ отгружен',
        'cancelled' => 'Заказ отменён',
    ];

    public function __construct(
        public readonly B2bOrder $order,
        public readonly string $stage,
        public readonly ?string $note = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                (string) config('mail.transactional_from.address'),
                (string) config('mail.transactional_from.name'),
            ),
            subject: (self::SUBJECTS[$this->stage] ?? 'B2B-заказ').' · '.$this->order->number.' — KOTLOV.BY',
            using: [function (Email $message): void {
                $logo = public_path('img/logo-black.png');
                if (is_file($logo)) {
                    $message->embedFromPath($logo, 'kotlov-logo', 'image/png');
                }
            }],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.b2b-order-stage');
    }
}

<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\PartnerBranch;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Symfony\Component\Mime\Email;

class PartnerOrderOfferedMail extends Mailable
{
    use Queueable;

    public function __construct(public readonly Order $order, public readonly PartnerBranch $branch) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                (string) config('mail.transactional_from.address'),
                (string) config('mail.transactional_from.name'),
            ),
            subject: 'Новый заказ клиента · '.$this->order->number.' — KOTLOV.BY',
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
        return new Content(view: 'emails.partner-order-offered');
    }
}

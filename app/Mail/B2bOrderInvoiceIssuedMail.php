<?php

namespace App\Mail;

use App\Models\B2bOrder;
use App\Services\CommercialDocuments\CommercialDocumentPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class B2bOrderInvoiceIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly B2bOrder $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                (string) config('mail.transactional_from.address'),
                (string) config('mail.transactional_from.name'),
            ),
            subject: 'Счёт по B2B-заказу '.$this->order->number.' — KOTLOV.BY',
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
        return new Content(view: 'emails.b2b-order-invoice-issued');
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $document = $this->order->commercialDocument;
        if (! $document) {
            return [];
        }

        return [
            Attachment::fromData(
                fn (): string => app(CommercialDocumentPdf::class)->render($document),
                'Счет-'.$document->number.'.pdf',
            )->withMime('application/pdf'),
        ];
    }
}

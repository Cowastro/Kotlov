<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PartnerLevelReachedMail extends Mailable
{
    use Queueable;

    public function __construct(public string $levelName) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('no-reply@kotlov.by', 'KOTLOV'),
            subject: 'Вы перешли на уровень '.$this->levelName,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.partner-level-reached');
    }
}

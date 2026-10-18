<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class NewB2bAccessRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $applicant) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Новая заявка на B2B-доступ — KOTLOV.BY',
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
        return new Content(
            view: 'emails.b2b-access-request',
            with: [
                'adminUrl' => url('/admin/users/'.$this->applicant->id.'/edit'),
            ],
        );
    }
}

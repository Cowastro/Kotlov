<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewB2bAccessRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $applicant) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Новая заявка на B2B-доступ — KOTLOV.BY');
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

<?php

namespace App\Mail;

use App\Models\InstallerProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class InstallerB2bInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly InstallerProfile $profile,
        public readonly string $activationUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                (string) config('mail.transactional_from.address'),
                (string) config('mail.transactional_from.name'),
            ),
            subject: 'Для вас открыт B2B-кабинет KOTLOV — без новой регистрации',
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
        return new Content(view: 'emails.installer-b2b-invitation');
    }
}

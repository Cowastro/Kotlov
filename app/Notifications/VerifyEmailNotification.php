<?php

namespace App\Notifications;

use App\Support\TransactionalMailbox;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Symfony\Component\Mime\Email;

class VerifyEmailNotification extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        TransactionalMailbox::configure();
        $minutes = (int) config('auth.verification.expire', 60);

        $message = (new MailMessage)
            ->mailer('transactional')
            ->from(
                config('mail.transactional_from.address'),
                config('mail.transactional_from.name'),
            )
            ->subject('Подтвердите email — KOTLOV.BY')
            ->view('emails.verify-email', [
                'name' => $notifiable->name,
                'verificationUrl' => $this->verificationUrl($notifiable),
                'minutes' => $minutes,
            ]);

        return $message->withSymfonyMessage(function (Email $email): void {
            $logo = public_path('img/logo-black.png');
            if (is_file($logo)) {
                $email->embedFromPath($logo, 'kotlov-logo', 'image/png');
            }
        });
    }

}

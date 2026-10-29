<?php

namespace App\Notifications;

use App\Models\CrmMailbox;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Symfony\Component\Mime\Email;

class VerifyEmailNotification extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $this->configureTransactionalMailbox();
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

    private function configureTransactionalMailbox(): void
    {
        $mailbox = CrmMailbox::query()->where('email', 'no-reply@kotlov.by')->first();
        if (! $mailbox || blank($mailbox->password)) {
            return;
        }

        config()->set('mail.mailers.transactional', [
            'transport' => 'smtp',
            'scheme' => $mailbox->smtp_encryption === 'ssl' ? 'smtps' : null,
            'host' => $mailbox->smtp_host,
            'port' => $mailbox->smtp_port,
            'username' => $mailbox->username,
            'password' => $mailbox->password,
            'timeout' => null,
            'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST),
        ]);
        config()->set('mail.transactional_from', [
            'address' => $mailbox->email,
            'name' => $mailbox->name,
        ]);
        app('mail.manager')->purge('transactional');
    }
}

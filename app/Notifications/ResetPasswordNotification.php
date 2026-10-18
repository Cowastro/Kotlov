<?php

namespace App\Notifications;

use App\Models\CrmMailbox;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Symfony\Component\Mime\Email;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $mailbox = CrmMailbox::query()->where('email', 'no-reply@kotlov.by')->first();
        if ($mailbox && filled($mailbox->password)) {
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

        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        $message = (new MailMessage)
            ->mailer('transactional')
            ->from(
                config('mail.transactional_from.address'),
                config('mail.transactional_from.name'),
            )
            ->subject('Доступ к аккаунту KOTLOV.BY')
            ->view('emails.password-reset', [
                'name' => $notifiable->name,
                'resetUrl' => $this->resetUrl($notifiable),
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

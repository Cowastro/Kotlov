<?php

namespace App\Notifications;

use App\Support\TransactionalMailbox;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Symfony\Component\Mime\Email;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        TransactionalMailbox::configure();

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

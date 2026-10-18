<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->mailer('transactional')
            ->from(
                config('mail.transactional_from.address'),
                config('mail.transactional_from.name'),
            )
            ->subject('Доступ к аккаунту KOTLOV.BY')
            ->greeting('Здравствуйте!')
            ->line('Для вашего аккаунта запрошено создание или восстановление пароля.')
            ->action('Задать новый пароль', $this->resetUrl($notifiable))
            ->line("Ссылка действует {$minutes} минут.")
            ->line('Если вы не запрашивали доступ, просто проигнорируйте это письмо.');
    }
}

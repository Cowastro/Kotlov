<?php

namespace App\Notifications;

use App\Models\CrmMailbox;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

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

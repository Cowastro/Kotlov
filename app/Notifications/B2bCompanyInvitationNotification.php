<?php

namespace App\Notifications;

use App\Models\B2bClientInvitation;
use App\Models\CrmMailbox;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

class B2bCompanyInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly B2bClientInvitation $invitation,
        private readonly string $token,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->configureTransactionalMailbox();
        $company = $this->invitation->client->company_name;

        return (new MailMessage)
            ->mailer('transactional')
            ->from(config('mail.transactional_from.address'), config('mail.transactional_from.name'))
            ->subject("Приглашение в B2B-кабинет {$company} — KOTLOV.BY")
            ->view('emails.b2b-company-invitation', [
                'company' => $company,
                'role' => $this->invitation->roleLabel(),
                'inviter' => $this->invitation->inviter?->name,
                'invitationUrl' => route('b2b.invitation.show', ['token' => $this->token]),
                'expiresAt' => $this->invitation->expires_at,
            ])
            ->withSymfonyMessage(function (Email $email): void {
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

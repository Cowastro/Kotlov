<?php

namespace App\Notifications;

use App\Support\TransactionalMailbox;
use App\Models\B2bClientInvitation;
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
        TransactionalMailbox::configure();
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

}

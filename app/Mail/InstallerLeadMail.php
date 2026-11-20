<?php

namespace App\Mail;

use App\Models\InstallRequest;
use App\Models\InstallRequestLead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class InstallerLeadMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly InstallRequestLead $lead,
        public readonly string $leadUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                (string) config('mail.transactional_from.address'),
                (string) config('mail.transactional_from.name'),
            ),
            subject: 'Новая заявка на монтаж: '.$this->summary(),
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
        return new Content(view: 'emails.installer-lead', with: [
            'request' => $this->lead->installRequest,
            'profile' => $this->lead->installerProfile,
            'kind' => $this->kind(),
            'place' => $this->place(),
            'maxTakers' => (int) config('installer_leads.max_takers', 3),
            'linkDays' => (int) config('installer_leads.link_days', 7),
        ]);
    }

    public function summary(): string
    {
        return implode(', ', array_filter([$this->kind(), $this->place()]));
    }

    private function kind(): string
    {
        $request = $this->lead->installRequest;

        return InstallRequest::SPECIALIZATIONS[$request->specialization] ?? 'Монтаж отопления';
    }

    private function place(): string
    {
        $request = $this->lead->installRequest;

        return implode(', ', array_unique(array_filter([trim((string) $request->city), trim((string) $request->region)])));
    }
}

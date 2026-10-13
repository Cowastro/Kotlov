<?php

namespace App\Services\CrmMail;

use App\Models\CrmMailMessage;
use App\Models\CrmMailThread;
use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;

class CrmMailSender
{
    public function reply(CrmMailThread $thread, string $body, User $user): CrmMailMessage
    {
        $mailbox = $thread->mailbox;
        if (blank($mailbox->password)) {
            throw new RuntimeException('В настройках ящика не сохранён пароль SMTP.');
        }

        $transport = new EsmtpTransport(
            $mailbox->smtp_host,
            $mailbox->smtp_port,
            in_array($mailbox->smtp_encryption, ['ssl', 'tls'], true),
        );
        $transport->setUsername($mailbox->username);
        $transport->setPassword($mailbox->password);

        $lastMessage = $thread->messages()->whereNotNull('message_id')->latest('received_at')->first();
        $subject = preg_match('/^re\s*:/i', (string) $thread->subject)
            ? $thread->subject
            : 'Re: '.$thread->subject;
        $messageId = Str::uuid().'@'.parse_url(config('app.url'), PHP_URL_HOST);
        $htmlBody = $this->brandedHtml($body, $user);
        $plainBody = $this->brandedText($body, $user);

        $email = (new Email)
            ->from(sprintf('%s <%s>', $mailbox->name, $mailbox->email))
            ->replyTo(sprintf('%s <%s>', $mailbox->name, $mailbox->email))
            ->to($thread->participant_email)
            ->subject($subject)
            ->text($plainBody)
            ->html($htmlBody);
        $email->getHeaders()->addIdHeader('Message-ID', $messageId);
        if ($lastMessage?->message_id) {
            $email->getHeaders()->addIdHeader('In-Reply-To', $lastMessage->message_id);
            $email->getHeaders()->addTextHeader('References', $lastMessage->message_id);
        }

        (new Mailer($transport))->send($email);

        $message = CrmMailMessage::create([
            'thread_id' => $thread->id,
            'mailbox_id' => $mailbox->id,
            'sent_by_user_id' => $user->id,
            'message_id' => $messageId,
            'in_reply_to' => $lastMessage?->message_id,
            'direction' => 'outgoing',
            'from_name' => $mailbox->name,
            'from_email' => $mailbox->email,
            'to' => [['name' => $thread->participant_name, 'email' => $thread->participant_email]],
            'subject' => $subject,
            'text_body' => $plainBody,
            'html_body' => $htmlBody,
            'is_read' => true,
            'sent_at' => now(),
            'received_at' => now(),
        ]);

        $thread->forceFill([
            'status' => 'waiting_customer',
            'messages_count' => $thread->messages_count + 1,
            'last_message_at' => now(),
            'handled_at' => now(),
        ])->save();

        return $message;
    }

    public function brandedHtml(string $body, User $user): string
    {
        return view('emails.crm.kotlov-reply', [
            'body' => $body,
            'managerName' => $user->name,
            'company' => config('legal.seller'),
        ])->render();
    }

    public function brandedText(string $body, User $user): string
    {
        $phones = implode(', ', config('legal.seller.phones', []));

        return trim($body)."\n\n"
            .'С уважением, '.$user->name."\n"
            ."Команда KOTLOV.BY\n"
            .$phones."\n"
            .config('legal.seller.email', 'info@kotlov.by')." · https://kotlov.by";
    }
}

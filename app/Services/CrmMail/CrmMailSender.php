<?php

namespace App\Services\CrmMail;

use App\Models\CrmMailbox;
use App\Models\CrmMailMessage;
use App\Models\CrmMailThread;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;

class CrmMailSender
{
    public function sendDraft(CrmMailThread $thread, User $user): CrmMailMessage
    {
        $thread->loadMissing(['mailbox', 'messages']);

        if ($thread->status !== 'draft') {
            throw new RuntimeException('Это письмо уже не является черновиком.');
        }
        if (! filter_var($thread->participant_email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Укажите корректный email поставщика перед отправкой.');
        }

        $draft = $thread->messages->firstWhere('direction', 'draft');
        if (! $draft) {
            throw new RuntimeException('Текст почтового черновика не найден.');
        }

        $mailbox = $thread->mailbox;
        $messageId = Str::uuid().'@'.parse_url(config('app.url'), PHP_URL_HOST);
        $body = trim((string) $draft->text_body);
        $htmlBody = $this->brandedHtml($body, $user);
        $plainBody = $this->brandedText($body, $user);

        $email = (new Email)
            ->from(sprintf('%s <%s>', $mailbox->name, $mailbox->email))
            ->replyTo(sprintf('%s <%s>', $mailbox->name, $mailbox->email))
            ->to($thread->participant_email)
            ->subject((string) $thread->subject)
            ->text($plainBody)
            ->html($htmlBody);
        $email->getHeaders()->addIdHeader('Message-ID', $messageId);

        $this->mailer($mailbox)->send($email);

        return DB::transaction(function () use ($thread, $draft, $user, $messageId, $plainBody, $htmlBody): CrmMailMessage {
            $draft->forceFill([
                'sent_by_user_id' => $user->id,
                'message_id' => $messageId,
                'direction' => 'outgoing',
                'to' => [['name' => $thread->participant_name, 'email' => $thread->participant_email]],
                'subject' => $thread->subject,
                'text_body' => $plainBody,
                'html_body' => $htmlBody,
                'sent_at' => now(),
                'received_at' => now(),
            ])->save();

            $thread->forceFill([
                'assigned_user_id' => $thread->assigned_user_id ?: $user->id,
                'status' => 'waiting_customer',
                'messages_count' => 1,
                'last_message_at' => now(),
                'handled_at' => now(),
            ])->save();

            return $draft->fresh();
        });
    }

    public function reply(CrmMailThread $thread, string $body, User $user): CrmMailMessage
    {
        $mailbox = $thread->mailbox;

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

        $this->mailer($mailbox)->send($email);

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

    public function sendTest(CrmMailbox $mailbox, string $recipient, User $user): void
    {
        $body = 'Это тестовое письмо из модуля «Почта и лиды». Оно подтверждает, что общий почтовый ящик работает, а в подписи указан сотрудник, который отправил письмо.';

        $email = (new Email)
            ->from(sprintf('%s <%s>', $mailbox->name, $mailbox->email))
            ->replyTo(sprintf('%s <%s>', $mailbox->name, $mailbox->email))
            ->to($recipient)
            ->subject('Тест персональной подписи KOTLOV.BY')
            ->text($this->brandedText($body, $user))
            ->html($this->brandedHtml($body, $user));

        $this->mailer($mailbox)->send($email);
    }

    public function brandedHtml(string $body, User $user): string
    {
        return view('emails.crm.kotlov-reply', [
            'body' => $body,
            'managerName' => $user->name,
            'managerJobTitle' => $user->job_title,
            'managerPhone' => $user->phone,
            'company' => config('legal.seller'),
        ])->render();
    }

    public function brandedText(string $body, User $user): string
    {
        $phones = implode(', ', config('legal.seller.phones', []));

        $managerDetails = array_filter([
            $user->job_title,
            $user->phone,
        ]);

        return trim($body)."\n\n"
            .'С уважением, '.$user->name."\n"
            .($managerDetails ? implode("\n", $managerDetails)."\n" : '')
            .'Команда KOTLOV.BY'."\n"
            .'Общие телефоны: '.$phones."\n"
            .config('legal.seller.email', 'info@kotlov.by').' · https://kotlov.by';
    }

    private function mailer(CrmMailbox $mailbox): Mailer
    {
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

        return new Mailer($transport);
    }
}

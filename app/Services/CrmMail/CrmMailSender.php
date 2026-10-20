<?php

namespace App\Services\CrmMail;

use App\Models\CommercialDocument;
use App\Models\CrmMailbox;
use App\Models\CrmMailMessage;
use App\Models\CrmMailThread;
use App\Models\OrderAccountingRequest;
use App\Models\User;
use App\Services\CommercialDocuments\CommercialDocumentPortal;
use App\Services\Orders\SupplierOrderRequestWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;

class CrmMailSender
{
    public function sendDraft(CrmMailThread $thread, User $user): CrmMailMessage
    {
        if ($thread->supplier_order_request_id || $thread->product_id) {
            return $this->sendSupplierDraft($thread, $user);
        }

        return $this->deliverDraft($thread, $user, false);
    }

    public function sendSupplierDraft(CrmMailThread $thread, User $user): CrmMailMessage
    {
        if (! $user->isManager()) {
            throw new RuntimeException('Отправлять заявки поставщикам может только менеджер.');
        }
        if (! $thread->supplier_order_request_id && ! $thread->product_id) {
            throw new RuntimeException('Письмо не связано с заявкой поставщику или товаром.');
        }

        $message = $this->deliverDraft($thread, $user, true);

        if ($thread->supplier_order_request_id) {
            $request = $thread->supplierOrderRequest()->first();
            if ($request?->status === 'draft') {
                app(SupplierOrderRequestWorkflow::class)->markEmailSent(
                    $request,
                    $user,
                    $request->note,
                );
            }
        }

        return $message;
    }

    public function sendOrderProcurementDraft(CrmMailThread $thread, User $user): CrmMailMessage
    {
        if (! $user->isManager()) {
            throw new RuntimeException('Отправлять закупочный лист может только менеджер.');
        }
        if (! $thread->order_id) {
            throw new RuntimeException('Письмо не связано с заказом.');
        }

        $draft = $thread->messages()->where('direction', 'draft')->latest('id')->first();
        if (($draft?->metadata['source'] ?? null) !== 'order_procurement') {
            throw new RuntimeException('Это письмо не является закупочным листом заказа.');
        }

        return $this->deliverDraft($thread, $user, true);
    }

    public function sendOrderAccountingDraft(CrmMailThread $thread, User $user): CrmMailMessage
    {
        if (! $user->isManager()) {
            throw new RuntimeException('Передавать документы в бухгалтерию может только менеджер.');
        }
        if (! $thread->order_id) {
            throw new RuntimeException('Поручение не связано с заказом.');
        }

        $draft = $thread->messages()->where('direction', 'draft')->latest('id')->first();
        if (($draft?->metadata['source'] ?? null) !== 'order_accounting_payment') {
            throw new RuntimeException('Это письмо не является поручением бухгалтерии.');
        }

        return $this->deliverDraft($thread, $user, true);
    }

    private function deliverDraft(CrmMailThread $thread, User $user, bool $supplierServiceMail): CrmMailMessage
    {
        $thread->loadMissing(['mailbox', 'messages']);

        if ($thread->status !== 'draft') {
            throw new RuntimeException('Это письмо уже не является черновиком.');
        }
        $draft = $thread->messages
            ->where('direction', 'draft')
            ->sortByDesc('id')
            ->first();
        if (! $draft) {
            throw new RuntimeException('Текст почтового черновика не найден.');
        }
        $previousMessage = $thread->messages()
            ->reorder()
            ->where('id', '!=', $draft->id)
            ->whereNotNull('message_id')
            ->latest('received_at')
            ->latest('id')
            ->first();

        $mailbox = $thread->mailbox;
        if ($supplierServiceMail) {
            if (mb_strtolower(trim((string) $mailbox?->email)) !== 'order@kotlov.by') {
                throw new RuntimeException('Заявки поставщикам отправляются только с order@kotlov.by.');
            }
        } else {
            $this->assertOperatorSendingAllowed($mailbox);
        }
        if (! $mailbox?->is_enabled) {
            throw new RuntimeException('Почтовый ящик отключён или не найден.');
        }
        $messageId = Str::uuid().'@'.parse_url(config('app.url'), PHP_URL_HOST);
        $body = trim((string) $draft->text_body);
        $htmlBody = $this->brandedHtml($body, $user);
        $plainBody = $this->brandedText($body, $user);
        $recipients = $this->messageAddresses($draft->to);
        $cc = $this->messageAddresses($draft->cc);
        if ($recipients === []) {
            throw new RuntimeException('Укажите хотя бы одного корректного получателя письма.');
        }

        $email = (new Email)
            ->from(sprintf('%s <%s>', $mailbox->name, $mailbox->email))
            ->replyTo(sprintf('%s <%s>', $mailbox->name, $mailbox->email))
            ->to(...$recipients)
            ->subject((string) $thread->subject)
            ->text($plainBody)
            ->html($htmlBody);
        if ($cc !== []) {
            $email->cc(...$cc);
        }
        $this->embedBrandAssets($email);
        $this->attachFiles($email, $draft);
        $email->getHeaders()->addIdHeader('Message-ID', $messageId);
        if ($previousMessage?->message_id) {
            $email->getHeaders()->addIdHeader('In-Reply-To', $previousMessage->message_id);
            $email->getHeaders()->addTextHeader('References', $previousMessage->message_id);
        }

        $this->mailer($mailbox)->send($email);

        return DB::transaction(function () use ($thread, $draft, $previousMessage, $user, $messageId, $plainBody, $htmlBody): CrmMailMessage {
            $draft->forceFill([
                'sent_by_user_id' => $user->id,
                'message_id' => $messageId,
                'in_reply_to' => $previousMessage?->message_id,
                'direction' => 'outgoing',
                'to' => $draft->to,
                'cc' => $draft->cc,
                'subject' => $thread->subject,
                'text_body' => $plainBody,
                'html_body' => $htmlBody,
                'sent_at' => now(),
                'received_at' => now(),
            ])->save();

            $thread->forceFill([
                'assigned_user_id' => $thread->assigned_user_id ?: $user->id,
                'status' => 'waiting_customer',
                'messages_count' => $thread->messages()->count(),
                'last_message_at' => now(),
                'handled_at' => now(),
            ])->save();

            if (($draft->metadata['source'] ?? null) === 'order_accounting_payment') {
                OrderAccountingRequest::query()
                    ->where('mail_thread_id', $thread->id)
                    ->where('status', 'draft')
                    ->update([
                        'status' => 'sent',
                        'sent_at' => now(),
                        'last_error' => null,
                        'updated_at' => now(),
                    ]);
            }

            $commercialDocuments = $this->markCommercialDocumentsSent($thread);

            if ($commercialDocuments > 0) {
                $thread->markProposalSent($user->id);
            }

            return $draft->fresh();
        });
    }

    public function markCommercialDocumentsSent(CrmMailThread $thread): int
    {
        $documents = CommercialDocument::query()
            ->where('crm_mail_thread_id', $thread->id)
            ->where('status', 'draft')
            ->get();

        foreach ($documents as $document) {
            $document->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
                'follow_up_stage' => $document->type === 'quote' ? 'proposal_sent' : null,
                'next_follow_up_at' => $document->type === 'quote' ? now()->addWeekdays(2)->setTime(10, 0) : null,
                'follow_up_note' => $document->type === 'quote'
                    ? 'Связаться с клиентом после отправки коммерческого предложения.'
                    : null,
            ])->save();

            if ($document->type !== 'quote' || ! $document->revision_of_id) {
                continue;
            }

            CommercialDocument::query()
                ->where(function ($query) use ($document): void {
                    $query->whereKey($document->revision_of_id)
                        ->orWhere('revision_of_id', $document->revision_of_id);
                })
                ->where('id', '!=', $document->id)
                ->whereIn('status', ['sent', 'accepted'])
                ->update([
                    'status' => 'needs_revision',
                    'accepted_at' => null,
                    'outcome_reason' => null,
                    'outcome_note' => 'Заменено документом '.$document->reference().'.',
                    'closed_at' => null,
                ]);

            app(CommercialDocumentPortal::class)->revokeSupersededRevisionLinks($document);
        }

        return $documents->count();
    }

    public function reply(
        CrmMailThread $thread,
        string $body,
        User $user,
        array $cc = [],
        array $attachments = [],
        array $attachmentNames = [],
        ?CrmMailMessage $replyTo = null,
    ): CrmMailMessage {
        $mailbox = $thread->mailbox;
        $this->assertOperatorSendingAllowed($mailbox);

        if (! $mailbox || ! $mailbox->is_enabled) {
            throw new RuntimeException('Почтовый ящик отключён или не найден.');
        }
        if ($replyTo !== null && ($replyTo->thread_id !== $thread->id || $replyTo->direction === 'draft')) {
            throw new RuntimeException('Выбранное письмо не подходит для ответа в этой переписке.');
        }

        $recipientEmail = $replyTo?->direction === 'incoming' && filter_var($replyTo->from_email, FILTER_VALIDATE_EMAIL)
            ? mb_strtolower(trim((string) $replyTo->from_email))
            : mb_strtolower(trim((string) $thread->participant_email));
        if (! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Укажите корректный email получателя перед отправкой.');
        }

        $replyTo ??= $thread->messages()->reorder()
            ->where('direction', 'incoming')
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->first();
        $sourceSubject = (string) ($replyTo?->subject ?: $thread->subject);
        $subject = preg_match('/^re\s*:/i', $sourceSubject)
            ? $sourceSubject
            : 'Re: '.$sourceSubject;
        $messageId = Str::uuid().'@'.parse_url(config('app.url'), PHP_URL_HOST);
        $htmlBody = $this->brandedHtml($body, $user);
        $plainBody = $this->brandedText($body, $user);
        $cc = collect($cc)->map(fn (mixed $address): string => mb_strtolower(trim((string) $address)))
            ->filter(fn (string $address): bool => filter_var($address, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()->values()->all();

        $email = (new Email)
            ->from(sprintf('%s <%s>', $mailbox->name, $mailbox->email))
            ->replyTo(sprintf('%s <%s>', $mailbox->name, $mailbox->email))
            ->to($recipientEmail)
            ->subject($subject)
            ->text($plainBody)
            ->html($htmlBody);
        if ($cc !== []) {
            $email->cc(...$cc);
        }
        $this->embedBrandAssets($email);
        $attachmentMetadata = collect($attachments)->values()->map(function (mixed $path, int $index) use ($attachmentNames): array {
            $path = (string) $path;

            return [
                'path' => $path,
                'name' => (string) ($attachmentNames[$path] ?? $attachmentNames[$index] ?? basename($path)),
            ];
        })->filter(fn (array $file): bool => str_starts_with($file['path'], 'crm-mail-attachments/'))->values()->all();
        $this->attachMetadata($email, $attachmentMetadata);
        $email->getHeaders()->addIdHeader('Message-ID', $messageId);
        if ($replyTo?->message_id) {
            $email->getHeaders()->addIdHeader('In-Reply-To', $replyTo->message_id);
            $email->getHeaders()->addTextHeader('References', $replyTo->message_id);
        }

        $this->mailer($mailbox)->send($email);

        $message = CrmMailMessage::create([
            'thread_id' => $thread->id,
            'mailbox_id' => $mailbox->id,
            'sent_by_user_id' => $user->id,
            'message_id' => $messageId,
            'in_reply_to' => $replyTo?->message_id,
            'direction' => 'outgoing',
            'from_name' => $mailbox->name,
            'from_email' => $mailbox->email,
            'to' => [[
                'name' => $replyTo?->direction === 'incoming' && filled($replyTo->from_name)
                    ? $replyTo->from_name
                    : $thread->participant_name,
                'email' => $recipientEmail,
            ]],
            'cc' => collect($cc)->map(fn (string $email): array => ['name' => null, 'email' => $email])->all(),
            'subject' => $subject,
            'text_body' => $plainBody,
            'html_body' => $htmlBody,
            'attachments_count' => count($attachmentMetadata),
            'is_read' => true,
            'sent_at' => now(),
            'received_at' => now(),
            'metadata' => ['attachments' => $attachmentMetadata],
        ]);

        $thread->forceFill([
            'status' => 'waiting_customer',
            'subject' => $subject,
            'messages_count' => $thread->messages()->count(),
            'last_message_at' => now(),
            'handled_at' => now(),
        ])->save();

        if ($thread->isPotentialDeal()) {
            $thread->forceFill([
                'next_follow_up_at' => now()->addWeekdays(2)->setTime(10, 0),
                'follow_up_note' => 'Проверить ответ клиента после отправленного письма.',
            ])->save();
        }

        return $message;
    }

    public function forward(
        CrmMailThread $thread,
        CrmMailMessage $source,
        array $recipients,
        string $subject,
        string $body,
        User $user,
        array $cc = [],
        array $attachments = [],
        array $attachmentNames = [],
    ): CrmMailMessage {
        $mailbox = $thread->mailbox;
        $this->assertOperatorSendingAllowed($mailbox);

        if ($source->thread_id !== $thread->id) {
            throw new RuntimeException('Исходное письмо не относится к этой переписке.');
        }
        if (! $mailbox || ! $mailbox->is_enabled) {
            throw new RuntimeException('Почтовый ящик отключён или не найден.');
        }

        $recipients = $this->normalizeAddresses($recipients);
        $cc = array_values(array_diff($this->normalizeAddresses($cc), $recipients));
        if ($recipients === []) {
            throw new RuntimeException('Укажите хотя бы одного корректного получателя пересылки.');
        }

        $subject = trim($subject);
        if ($subject === '') {
            throw new RuntimeException('Укажите тему письма.');
        }

        $messageId = Str::uuid().'@'.parse_url(config('app.url'), PHP_URL_HOST);
        $htmlBody = $this->brandedHtml($body, $user);
        $plainBody = $this->brandedText($body, $user);
        $attachmentMetadata = collect($attachments)->values()->map(function (mixed $path, int $index) use ($attachmentNames): array {
            $path = (string) $path;

            return [
                'path' => $path,
                'name' => (string) ($attachmentNames[$path] ?? $attachmentNames[$index] ?? basename($path)),
            ];
        })->filter(fn (array $file): bool => str_starts_with($file['path'], 'crm-mail-attachments/'))
            ->unique('path')->values()->all();

        $email = (new Email)
            ->from(sprintf('%s <%s>', $mailbox->name, $mailbox->email))
            ->replyTo(sprintf('%s <%s>', $mailbox->name, $mailbox->email))
            ->to(...$recipients)
            ->subject($subject)
            ->text($plainBody)
            ->html($htmlBody);
        if ($cc !== []) {
            $email->cc(...$cc);
        }
        $this->embedBrandAssets($email);
        $this->attachMetadata($email, $attachmentMetadata);
        $email->getHeaders()->addIdHeader('Message-ID', $messageId);

        $this->mailer($mailbox)->send($email);

        $message = CrmMailMessage::create([
            'thread_id' => $thread->id,
            'mailbox_id' => $mailbox->id,
            'sent_by_user_id' => $user->id,
            'message_id' => $messageId,
            'direction' => 'outgoing',
            'from_name' => $mailbox->name,
            'from_email' => $mailbox->email,
            'to' => collect($recipients)->map(fn (string $email): array => ['name' => null, 'email' => $email])->all(),
            'cc' => collect($cc)->map(fn (string $email): array => ['name' => null, 'email' => $email])->all(),
            'subject' => $subject,
            'text_body' => $plainBody,
            'html_body' => $htmlBody,
            'attachments_count' => count($attachmentMetadata),
            'is_read' => true,
            'sent_at' => now(),
            'received_at' => now(),
            'metadata' => [
                'attachments' => $attachmentMetadata,
                'forwarded_message_id' => $source->id,
            ],
        ]);

        $thread->forceFill([
            'assigned_user_id' => $thread->assigned_user_id ?: $user->id,
            'messages_count' => $thread->messages_count + 1,
            'last_message_at' => now(),
            'handled_at' => now(),
        ])->save();

        return $message;
    }

    public function forwardedText(CrmMailMessage $message): string
    {
        $date = ($message->received_at ?? $message->sent_at)?->timezone('Europe/Minsk')->format('d.m.Y H:i') ?? 'не указана';
        $from = trim(collect([$message->from_name, $message->from_email])->filter()->implode(' <'));
        if ($message->from_name && $message->from_email) {
            $from .= '>';
        }
        $recipients = collect($message->to ?? [])->pluck('email')->filter()->implode(', ');
        $originalBody = app(CrmMailMessagePreview::class)->plainText($message);

        return "\n\n---------- Пересылаемое сообщение ----------\n"
            .'От: '.($from ?: 'не указан')."\n"
            .'Дата: '.$date."\n"
            .'Кому: '.($recipients ?: 'не указано')."\n"
            .'Тема: '.($message->subject ?: 'Без темы')."\n\n"
            .$originalBody;
    }

    /** @return list<string> */
    private function normalizeAddresses(array $addresses): array
    {
        return collect($addresses)
            ->map(fn (mixed $address): string => mb_strtolower(trim((string) $address)))
            ->filter(fn (string $address): bool => filter_var($address, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()->values()->all();
    }

    /** @return list<string> */
    private function messageAddresses(?array $records): array
    {
        return collect($records ?? [])->pluck('email')
            ->map(fn (mixed $address): string => mb_strtolower(trim((string) $address)))
            ->filter(fn (string $address): bool => filter_var($address, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()->values()->all();
    }

    private function attachFiles(Email $email, CrmMailMessage $message): void
    {
        $this->attachMetadata($email, $message->metadata['attachments'] ?? []);
    }

    private function attachMetadata(Email $email, array $attachments): void
    {
        foreach ($attachments as $attachment) {
            $path = (string) ($attachment['path'] ?? '');
            if (! str_starts_with($path, 'crm-mail-attachments/')) {
                continue;
            }
            if (! Storage::disk('local')->exists($path)) {
                throw new RuntimeException('Файл вложения «'.($attachment['name'] ?? basename($path)).'» не найден. Прикрепите его повторно.');
            }

            $email->attachFromPath(
                Storage::disk('local')->path($path),
                (string) ($attachment['name'] ?? basename($path)),
            );
        }
    }

    private function embedBrandAssets(Email $email): void
    {
        $logo = public_path('img/logo-black.png');

        if (is_file($logo)) {
            $email->embedFromPath($logo, 'kotlov-logo', 'image/png');
        }
    }

    public function sendTest(CrmMailbox $mailbox, string $recipient, User $user): void
    {
        $this->assertManualSendingAllowed($mailbox);

        $body = 'Это тестовое письмо из модуля «Почта и лиды». Оно подтверждает, что общий почтовый ящик работает, а в подписи указан сотрудник, который отправил письмо.';

        $email = (new Email)
            ->from(sprintf('%s <%s>', $mailbox->name, $mailbox->email))
            ->replyTo(sprintf('%s <%s>', $mailbox->name, $mailbox->email))
            ->to($recipient)
            ->subject('Тест персональной подписи KOTLOV.BY')
            ->text($this->brandedText($body, $user))
            ->html($this->brandedHtml($body, $user));

        $this->embedBrandAssets($email);

        $this->mailer($mailbox)->send($email);
    }

    private function assertManualSendingAllowed(?CrmMailbox $mailbox): void
    {
        if ($mailbox?->isServiceMailbox()) {
            throw new RuntimeException('Служебный ящик «'.$mailbox->email.'» используется только автоматикой. Ручная отправка запрещена.');
        }
    }

    private function assertOperatorSendingAllowed(?CrmMailbox $mailbox): void
    {
        if ($mailbox?->isServiceMailbox() && mb_strtolower(trim((string) $mailbox->email)) !== 'order@kotlov.by') {
            throw new RuntimeException('Служебный ящик «'.$mailbox->email.'» используется только автоматикой. Ручная отправка запрещена.');
        }
    }

    public function brandedHtml(string $body, User $user): string
    {
        preg_match('/Открыть предложение онлайн:\s*(https?:\/\/\S+)/u', $body, $portalMatch);

        return view('emails.crm.kotlov-reply', [
            'body' => $body,
            'portalUrl' => $portalMatch[1] ?? null,
            'managerName' => $user->name,
            'managerJobTitle' => $user->job_title,
            'managerPhone' => $user->phone,
            'company' => config('legal.commercial_documents'),
        ])->render();
    }

    public function brandedText(string $body, User $user): string
    {
        $company = config('legal.commercial_documents', []);
        $phones = implode(', ', $company['phones'] ?? []);

        $managerDetails = array_filter([
            $user->job_title,
            $user->phone,
        ]);

        return trim($body)."\n\n"
            .'С уважением, '.$user->name."\n"
            .($managerDetails ? implode("\n", $managerDetails)."\n" : '')
            .'Команда KOTLOV.BY'."\n"
            .'Общие телефоны: '.$phones."\n"
            .($company['email'] ?? 'info@kotlov.by').' · https://kotlov.by';
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

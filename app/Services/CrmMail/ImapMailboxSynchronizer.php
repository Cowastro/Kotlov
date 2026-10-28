<?php

namespace App\Services\CrmMail;

use App\Models\CrmMailbox;
use App\Models\CrmMailMessage;
use App\Models\CrmMailThread;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ImapMailboxSynchronizer
{
    public function __construct(
        private readonly LeadMatcher $leadMatcher,
        private readonly CrmMailThreadResolver $threadResolver,
        private readonly IncomingMailTelegramNotifier $telegramNotifier,
    ) {}

    /** @return array{imported: int, skipped: int} */
    public function sync(CrmMailbox $mailbox): array
    {
        if (! function_exists('imap_open')) {
            throw new RuntimeException('На сервере PHP не подключено расширение IMAP. Включите php-imap для фоновой загрузки писем.');
        }

        if (blank($mailbox->password)) {
            throw new RuntimeException('Не задан пароль почтового ящика.');
        }

        $imap = @imap_open($mailbox->imapPath(), $mailbox->username, $mailbox->password, OP_READONLY, 1);

        if ($imap === false) {
            throw new RuntimeException('IMAP: '.(imap_last_error() ?: 'не удалось подключиться к ящику'));
        }

        $mailbox->forceFill(['last_sync_at' => now(), 'last_error' => null])->save();
        $imported = 0;
        $skipped = 0;

        try {
            // `imap_search()` does not reliably support `UID n:*` as a search
            // criterion. Some servers return an empty result with a warning,
            // which made the first ALL import work and every later sync look
            // successful while importing nothing. Fetch UIDs with the portable
            // ALL criterion and select incremental candidates locally instead.
            $serverUids = @imap_search($imap, 'ALL', SE_UID) ?: [];
            $numbers = $this->candidateUids($serverUids, $mailbox->last_uid ? (int) $mailbox->last_uid : null);
            $deletedUids = Schema::hasTable('crm_mail_deleted_messages')
                ? DB::table('crm_mail_deleted_messages')
                    ->where('mailbox_id', $mailbox->id)
                    ->whereIn('imap_uid', $numbers)
                    ->pluck('imap_uid')
                    ->map(fn ($uid): int => (int) $uid)
                    ->all()
                : [];
            if ($deletedUids !== []) {
                $numbers = array_values(array_diff($numbers, $deletedUids));
                $skipped += count($deletedUids);
            }
            $existingUids = CrmMailMessage::query()
                ->where('mailbox_id', $mailbox->id)
                ->whereIn('imap_uid', $numbers)
                ->get()
                ->keyBy(fn (CrmMailMessage $message): int => (int) $message->imap_uid);

            foreach ($numbers as $uid) {
                if ($existingMessage = $existingUids->get((int) $uid)) {
                    $this->hydrateAttachments($imap, $mailbox, $existingMessage, (int) $uid);
                    $this->refreshReceivedAt($imap, $existingMessage, (int) $uid);
                    $skipped++;
                    $mailbox->last_uid = max((int) $mailbox->last_uid, (int) $uid);

                    continue;
                }

                $messageNumber = imap_msgno($imap, (int) $uid);
                $header = imap_headerinfo($imap, $messageNumber);
                $from = $header->from[0] ?? null;
                $fromEmail = $from ? mb_strtolower(($from->mailbox ?? '').'@'.($from->host ?? '')) : '';

                if (! filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                    $skipped++;
                    $mailbox->last_uid = max((int) $mailbox->last_uid, (int) $uid);

                    continue;
                }

                $fromName = $this->decodeHeader($from->personal ?? null);
                $subject = $this->decodeHeader($header->subject ?? null) ?: '(без темы)';
                $messageId = $this->cleanMessageId($header->message_id ?? null);
                $inReplyTo = $this->cleanMessageId($header->in_reply_to ?? null);

                // A server can assign a new IMAP UID to a message that was already
                // imported (for example after moving/restoring it). The RFC Message-ID
                // remains the same, and is the stable identity in that case.
                if ($messageId && CrmMailMessage::query()
                    ->where('mailbox_id', $mailbox->id)
                    ->where('message_id', $messageId)
                    ->exists()) {
                    $skipped++;
                    $mailbox->last_uid = max((int) $mailbox->last_uid, (int) $uid);

                    continue;
                }

                $structure = imap_fetchstructure($imap, $messageNumber);
                $bodies = $this->extractBodies($imap, $messageNumber, $structure);
                $attachments = $this->extractAttachments($imap, $messageNumber, $structure, $mailbox->id, (int) $uid);
                $latestSenderThread = CrmMailThread::query()
                    ->where('mailbox_id', $mailbox->id)
                    ->where('participant_email', $fromEmail)
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id')
                    ->first();
                $isBlockedSender = CrmMailThread::senderIsBlocked($mailbox->id, $fromEmail);

                if ($isBlockedSender) {
                    $counterparty = $latestSenderThread->counterparty;
                    $contact = $latestSenderThread->contact;
                } else {
                    [$counterparty, $contact] = $this->leadMatcher->resolve(
                        $fromEmail,
                        $fromName,
                        $mailbox->create_leads,
                    );
                }

                $message = DB::transaction(function () use (
                    $mailbox, $uid, $header, $fromEmail, $fromName, $subject, $messageId,
                    $inReplyTo, $bodies, $attachments, $counterparty, $contact, $isBlockedSender
                ): CrmMailMessage {
                    // Serialize persistence for one mailbox and repeat the identity
                    // check under the lock. This closes the race between two manual or
                    // scheduled synchronizations of the same mailbox.
                    $existingMessage = $this->existingMessageUnderLock(
                        $mailbox,
                        (int) $uid,
                        $messageId,
                    );

                    if ($existingMessage) {
                        return $existingMessage;
                    }

                    $receivedAt = $this->parseDate($header->date ?? null);
                    $thread = $this->threadResolver->resolve(
                        $mailbox,
                        $fromEmail,
                        $counterparty?->id,
                        $inReplyTo,
                        $subject,
                    );

                    if (! $thread) {
                        $thread = CrmMailThread::withTrashed()->firstOrCreate(
                            [
                                'mailbox_id' => $mailbox->id,
                                'thread_key' => hash('sha256', $fromEmail.'|'.mb_strtolower(trim($subject))),
                            ],
                            [
                                'crm_counterparty_id' => $counterparty?->id,
                                'crm_contact_id' => $contact?->id,
                                'subject' => $subject,
                                'participant_name' => $fromName,
                                'participant_email' => $fromEmail,
                                'status' => $isBlockedSender ? 'spam' : 'new',
                                'priority' => 'normal',
                            ],
                        );
                    }
                    $wasTrashed = $thread->trashed();

                    if ($wasTrashed) {
                        $thread->restore();
                    }

                    $message = CrmMailMessage::create([
                        'thread_id' => $thread->id,
                        'mailbox_id' => $mailbox->id,
                        'message_id' => $messageId,
                        'in_reply_to' => $inReplyTo,
                        'imap_uid' => $uid,
                        'direction' => 'incoming',
                        'from_name' => $fromName,
                        'from_email' => $fromEmail,
                        'to' => $this->addresses($header->to ?? []),
                        'cc' => $this->addresses($header->cc ?? []),
                        'subject' => $subject,
                        'text_body' => $bodies['text'],
                        'html_body' => $bodies['html'],
                        'attachments_count' => count($attachments),
                        'is_read' => $isBlockedSender,
                        'received_at' => $receivedAt,
                        'metadata' => [
                            'size' => $header->Size ?? null,
                            'attachments' => $attachments,
                            'attachments_scanned_at' => now()->toIso8601String(),
                        ],
                    ]);

                    $thread->forceFill([
                        'crm_counterparty_id' => $thread->crm_counterparty_id ?? $counterparty?->id,
                        'crm_contact_id' => $thread->crm_contact_id ?? $contact?->id,
                        'participant_name' => $fromName ?: $thread->participant_name,
                        'participant_email' => $fromEmail,
                        'subject' => $subject,
                        'messages_count' => $thread->messages()->count(),
                        'unread_count' => $isBlockedSender ? 0 : $thread->unread_count + 1,
                        'last_message_at' => $receivedAt,
                        'status' => $isBlockedSender
                            ? 'spam'
                            : ($wasTrashed || in_array($thread->status, ['closed', 'waiting_customer'], true)
                                ? 'waiting_manager'
                                : $thread->status),
                    ])->save();

                    if (! $isBlockedSender) {
                        $thread->markCustomerReplied();
                    }

                    $counterparty?->forceFill(['last_contact_at' => $receivedAt])->save();

                    return $message;
                });

                if (! $message->wasRecentlyCreated) {
                    $skipped++;
                    $mailbox->last_uid = max((int) $mailbox->last_uid, (int) $uid);

                    continue;
                }

                // The shared Telegram chat shows the letter text: personal mailboxes stay private.
                if (! $isBlockedSender && ! $mailbox->isPersonal()) {
                    $this->telegramNotifier->send($message);
                }

                $mailbox->last_uid = max((int) $mailbox->last_uid, (int) $uid);
                $imported++;
            }

            $mailbox->forceFill([
                'last_uid' => $mailbox->last_uid,
                'last_success_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            Log::error('CRM mailbox synchronization failed.', [
                'mailbox_id' => $mailbox->id,
                'mailbox_email' => $mailbox->email,
                'exception_class' => $exception::class,
                'exception_code' => (string) $exception->getCode(),
            ]);
            $mailbox->forceFill([
                'last_error' => 'Синхронизация не завершена. Подробности записаны в журнал.',
            ])->save();
            throw $exception;
        } finally {
            imap_close($imap);
        }

        return compact('imported', 'skipped');
    }

    private function existingMessageUnderLock(
        CrmMailbox $mailbox,
        int $uid,
        ?string $messageId,
    ): ?CrmMailMessage {
        CrmMailbox::query()->whereKey($mailbox->id)->lockForUpdate()->first();

        return CrmMailMessage::query()
            ->where('mailbox_id', $mailbox->id)
            ->where(function ($query) use ($uid, $messageId): void {
                $query->where('imap_uid', $uid);

                if ($messageId) {
                    $query->orWhere('message_id', $messageId);
                }
            })
            ->lockForUpdate()
            ->first();
    }

    /**
     * Keep incremental imports bounded without trusting a server-specific UID
     * search expression. When there are no new UIDs, revisit recent messages
     * so a previously missed message can be recovered by the database check.
     *
     * @param  array<int|string>  $serverUids
     * @return list<int>
     */
    private function candidateUids(array $serverUids, ?int $lastUid, int $limit = 250): array
    {
        $uids = collect($serverUids)
            ->map(fn ($uid): int => (int) $uid)
            ->filter(fn (int $uid): bool => $uid > 0)
            ->unique()
            ->sort()
            ->values();

        if ($lastUid !== null) {
            $newUids = $uids->filter(fn (int $uid): bool => $uid > $lastUid)->take($limit)->values();
            if ($newUids->isNotEmpty()) {
                return $newUids->all();
            }
        }

        return $uids->take(-$limit)->values()->all();
    }

    private function decodeHeader(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return app(MailEncodingNormalizer::class)->decodeHeader($value);
    }

    private function cleanMessageId(?string $value): ?string
    {
        return filled($value) ? trim($value, " <>\t\n\r\0\x0B") : null;
    }

    private function parseDate(?string $value): Carbon
    {
        return $this->tryParseDate($value) ?? now()->utc();
    }

    private function tryParseDate(?string $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->utc();
        } catch (Throwable) {
            return null;
        }
    }

    private function refreshReceivedAt($imap, CrmMailMessage $message, int $uid): void
    {
        $messageNumber = imap_msgno($imap, $uid);
        if ($messageNumber < 1) {
            return;
        }

        $header = @imap_headerinfo($imap, $messageNumber);
        if ($header === false) {
            return;
        }

        $receivedAt = $this->tryParseDate($header->date ?? null);
        if (! $receivedAt || $message->received_at?->equalTo($receivedAt)) {
            return;
        }

        $message->forceFill(['received_at' => $receivedAt])->save();

        $latestReceivedAt = $message->thread->messages()
            ->whereNotNull('received_at')
            ->max('received_at');

        if ($latestReceivedAt) {
            $message->thread->forceFill([
                'last_message_at' => Carbon::parse($latestReceivedAt, 'UTC')->utc(),
            ])->save();
        }
    }

    /** @return list<array{name: string|null, email: string}> */
    private function addresses(array $addresses): array
    {
        return collect($addresses)->map(function (object $address): array {
            return [
                'name' => $this->decodeHeader($address->personal ?? null),
                'email' => mb_strtolower(($address->mailbox ?? '').'@'.($address->host ?? '')),
            ];
        })->filter(fn (array $item): bool => filter_var($item['email'], FILTER_VALIDATE_EMAIL) !== false)
            ->values()->all();
    }

    /** @return array{text: string|null, html: string|null} */
    private function extractBodies($imap, int $messageNumber, object|false $structure): array
    {
        $result = ['text' => null, 'html' => null];

        if (! $structure) {
            return $result;
        }

        $this->walkParts($imap, $messageNumber, $structure, '', $result);

        // An attachment-only multipart message has no displayable body. The
        // raw IMAP body contains MIME headers and encoded attachment bytes.
        return $result;
    }

    /** @param array{text: string|null, html: string|null} $result */
    private function walkParts($imap, int $messageNumber, object $part, string $prefix, array &$result): void
    {
        if (! empty($part->parts)) {
            foreach ($part->parts as $index => $child) {
                $number = $prefix === '' ? (string) ($index + 1) : $prefix.'.'.($index + 1);
                $this->walkParts($imap, $messageNumber, $child, $number, $result);
            }

            return;
        }

        $isAttachment = isset($part->disposition) && in_array(strtoupper($part->disposition), ['ATTACHMENT', 'INLINE'], true)
            && ($part->dparameters ?? []);
        if ($isAttachment || ($part->type ?? null) !== 0
            || filled($this->attachmentFilename($part))
            || ! in_array(strtoupper($part->subtype ?? 'PLAIN'), ['PLAIN', 'HTML'], true)) {
            return;
        }

        $body = $this->readTextPart($imap, $messageNumber, $part, $prefix);
        $subtype = strtoupper($part->subtype ?? 'PLAIN');
        $key = $subtype === 'HTML' ? 'html' : 'text';
        $result[$key] ??= trim($body) ?: null;
    }

    protected function readTextPart($imap, int $messageNumber, object $part, string $prefix): string
    {
        $body = $prefix === ''
            ? (string) imap_body($imap, $messageNumber, FT_PEEK)
            : (string) imap_fetchbody($imap, $messageNumber, $prefix, FT_PEEK);
        $body = match ($part->encoding ?? ENC7BIT) {
            ENCBASE64 => (string) base64_decode($body, true),
            ENCQUOTEDPRINTABLE => quoted_printable_decode($body),
            default => $body,
        };
        return $this->toUtf8($body, $this->partCharset($part));
    }

    private function partCharset(object $part): ?string
    {
        foreach (array_merge($part->parameters ?? [], $part->dparameters ?? []) as $parameter) {
            if (strtolower((string) ($parameter->attribute ?? '')) === 'charset') {
                return (string) ($parameter->value ?? '');
            }
        }

        return null;
    }

    private function toUtf8(string $value, ?string $charset = null): string
    {
        return app(MailEncodingNormalizer::class)->toUtf8($value, $charset);
    }

    private function hydrateAttachments($imap, CrmMailbox $mailbox, CrmMailMessage $message, int $uid): void
    {
        $metadata = $message->metadata ?? [];
        if (filled($metadata['attachments_scanned_at'] ?? null)) {
            return;
        }

        $messageNumber = imap_msgno($imap, $uid);
        if ($messageNumber < 1) {
            return;
        }

        $structure = imap_fetchstructure($imap, $messageNumber);
        $attachments = $this->extractAttachments($imap, $messageNumber, $structure, $mailbox->id, $uid);
        $metadata['attachments'] = $attachments;
        $metadata['attachments_scanned_at'] = now()->toIso8601String();

        $message->forceFill([
            'attachments_count' => count($attachments),
            'metadata' => $metadata,
        ])->save();
    }

    /** @return list<array{name: string, path: string|null, mime_type: string, size: int, stored: bool, error: string|null}> */
    private function extractAttachments($imap, int $messageNumber, object|false $structure, int $mailboxId, int $uid): array
    {
        if (! $structure) {
            return [];
        }

        $attachments = [];
        $this->walkAttachments($imap, $messageNumber, $structure, '', $attachments, $mailboxId, $uid);

        return $attachments;
    }

    /** @param list<array{name: string, path: string|null, mime_type: string, size: int, stored: bool, error: string|null}> $attachments */
    private function walkAttachments(
        $imap,
        int $messageNumber,
        object $part,
        string $prefix,
        array &$attachments,
        int $mailboxId,
        int $uid,
    ): void {
        if (! empty($part->parts)) {
            foreach ($part->parts as $index => $child) {
                $number = $prefix === '' ? (string) ($index + 1) : $prefix.'.'.($index + 1);
                $this->walkAttachments($imap, $messageNumber, $child, $number, $attachments, $mailboxId, $uid);
            }

            return;
        }

        $filename = $this->attachmentFilename($part);
        $disposition = strtoupper((string) ($part->disposition ?? ''));
        if ($disposition !== 'ATTACHMENT' && ! ($disposition === 'INLINE' && filled($filename))) {
            return;
        }

        $body = $prefix === ''
            ? (string) imap_body($imap, $messageNumber, FT_PEEK)
            : (string) imap_fetchbody($imap, $messageNumber, $prefix, FT_PEEK);
        $body = $this->decodePartBody($body, (int) ($part->encoding ?? ENC7BIT));
        $size = strlen($body);
        $name = $this->safeAttachmentName($filename, count($attachments) + 1);
        $mimeType = $this->partMimeType($part);
        $path = null;
        $error = null;

        if ($size > 25 * 1024 * 1024) {
            $error = 'too_large';
        } else {
            $path = 'crm-mail-attachments/incoming/'.$mailboxId.'/'.$uid.'/'.sprintf('%02d-', count($attachments) + 1).$name;
            try {
                if (! Storage::disk('local')->put($path, $body)) {
                    $path = null;
                    $error = 'storage_failed';
                }
            } catch (Throwable) {
                $path = null;
                $error = 'storage_failed';
            }
        }

        $attachments[] = [
            'name' => $name,
            'path' => $path,
            'mime_type' => $mimeType,
            'size' => $size,
            'stored' => $path !== null,
            'error' => $error,
        ];
    }

    private function decodePartBody(string $body, int $encoding): string
    {
        return match ($encoding) {
            ENCBASE64 => (string) base64_decode($body, true),
            ENCQUOTEDPRINTABLE => quoted_printable_decode($body),
            default => $body,
        };
    }

    private function attachmentFilename(object $part): ?string
    {
        foreach (array_merge((array) ($part->dparameters ?? []), (array) ($part->parameters ?? [])) as $parameter) {
            $attribute = strtolower((string) ($parameter->attribute ?? ''));
            if (in_array($attribute, ['filename', 'name'], true)) {
                return $this->decodeHeader((string) ($parameter->value ?? ''));
            }

            if (in_array($attribute, ['filename*', 'name*'], true)) {
                $value = (string) ($parameter->value ?? '');
                $encodedName = str_contains($value, "''") ? explode("''", $value, 2)[1] : $value;

                return $this->decodeHeader(rawurldecode($encodedName));
            }
        }

        return null;
    }

    private function safeAttachmentName(?string $filename, int $index): string
    {
        $filename = basename(str_replace('\\', '/', trim((string) $filename)));
        $filename = preg_replace('/[\x00-\x1F\x7F<>:"\/\\|?*]+/u', '-', $filename) ?: '';

        return Str::limit($filename !== '' ? $filename : 'attachment-'.$index.'.bin', 180, '');
    }

    private function partMimeType(object $part): string
    {
        $types = ['text', 'multipart', 'message', 'application', 'audio', 'image', 'video', 'application'];
        $type = $types[(int) ($part->type ?? 7)] ?? 'application';
        $subtype = strtolower((string) ($part->subtype ?? 'octet-stream'));

        return $type.'/'.$subtype;
    }

    private function countAttachments(object|false $structure): int
    {
        if (! $structure) {
            return 0;
        }

        $count = 0;
        foreach ($structure->parts ?? [] as $part) {
            $disposition = strtoupper($part->disposition ?? '');
            if ($disposition === 'ATTACHMENT') {
                $count++;
            }
            $count += $this->countAttachments($part);
        }

        return $count;
    }
}

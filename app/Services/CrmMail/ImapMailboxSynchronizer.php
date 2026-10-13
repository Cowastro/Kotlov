<?php

namespace App\Services\CrmMail;

use App\Models\CrmMailbox;
use App\Models\CrmMailMessage;
use App\Models\CrmMailThread;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ImapMailboxSynchronizer
{
    public function __construct(private readonly LeadMatcher $leadMatcher) {}

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
            $existingUids = CrmMailMessage::query()
                ->where('mailbox_id', $mailbox->id)
                ->whereIn('imap_uid', $numbers)
                ->pluck('imap_uid')
                ->mapWithKeys(fn ($uid): array => [(int) $uid => true])
                ->all();

            foreach ($numbers as $uid) {
                if (isset($existingUids[(int) $uid])) {
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
                $structure = imap_fetchstructure($imap, $messageNumber);
                $bodies = $this->extractBodies($imap, $messageNumber, $structure);
                [$counterparty, $contact] = $this->leadMatcher->resolve(
                    $fromEmail,
                    $fromName,
                    $mailbox->create_leads,
                );

                DB::transaction(function () use (
                    $mailbox, $uid, $header, $fromEmail, $fromName, $subject, $messageId,
                    $inReplyTo, $structure, $bodies, $counterparty, $contact
                ): void {
                    $threadKey = $this->threadKey($fromEmail, $subject, $inReplyTo);
                    $receivedAt = $this->parseDate($header->date ?? null);
                    $thread = CrmMailThread::withTrashed()->firstOrCreate(
                        ['mailbox_id' => $mailbox->id, 'thread_key' => $threadKey],
                        [
                            'crm_counterparty_id' => $counterparty?->id,
                            'crm_contact_id' => $contact?->id,
                            'subject' => $subject,
                            'participant_name' => $fromName,
                            'participant_email' => $fromEmail,
                            'status' => 'new',
                            'priority' => 'normal',
                        ],
                    );
                    $wasTrashed = $thread->trashed();

                    if ($wasTrashed) {
                        $thread->restore();
                    }

                    CrmMailMessage::create([
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
                        'attachments_count' => $this->countAttachments($structure),
                        'is_read' => false,
                        'received_at' => $receivedAt,
                        'metadata' => ['size' => $header->Size ?? null],
                    ]);

                    $thread->forceFill([
                        'crm_counterparty_id' => $thread->crm_counterparty_id ?? $counterparty?->id,
                        'crm_contact_id' => $thread->crm_contact_id ?? $contact?->id,
                        'participant_name' => $thread->participant_name ?: $fromName,
                        'messages_count' => $thread->messages_count + 1,
                        'unread_count' => $thread->unread_count + 1,
                        'last_message_at' => $receivedAt,
                        'status' => $wasTrashed || in_array($thread->status, ['closed', 'waiting_customer'], true)
                            ? 'waiting_manager'
                            : $thread->status,
                    ])->save();

                    $counterparty?->forceFill(['last_contact_at' => $receivedAt])->save();
                });

                $mailbox->last_uid = max((int) $mailbox->last_uid, (int) $uid);
                $imported++;
            }

            $mailbox->forceFill([
                'last_uid' => $mailbox->last_uid,
                'last_success_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $mailbox->forceFill(['last_error' => $this->toUtf8($exception->getMessage())])->save();
            throw $exception;
        } finally {
            imap_close($imap);
        }

        return compact('imported', 'skipped');
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

    private function threadKey(string $email, string $subject, ?string $inReplyTo): string
    {
        if ($inReplyTo) {
            $existing = CrmMailMessage::query()->where('message_id', $inReplyTo)->first();

            if ($existing) {
                return $existing->thread->thread_key;
            }
        }

        $normalizedSubject = Str::of($subject)
            ->lower()
            ->replaceMatches('/^(re|fw|fwd|ответ)\s*:\s*/ui', '')
            ->squish()
            ->value();

        return hash('sha256', $email.'|'.$normalizedSubject);
    }

    private function parseDate(?string $value): Carbon
    {
        try {
            return filled($value) ? Carbon::parse($value) : now();
        } catch (Throwable) {
            return now();
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

        if (! $result['text'] && ! $result['html']) {
            $result['text'] = trim((string) imap_body($imap, $messageNumber, FT_PEEK)) ?: null;
        }

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
        if ($isAttachment || ($part->type ?? null) !== TYPETEXT) {
            return;
        }

        $body = $prefix === ''
            ? (string) imap_body($imap, $messageNumber, FT_PEEK)
            : (string) imap_fetchbody($imap, $messageNumber, $prefix, FT_PEEK);
        $body = match ($part->encoding ?? ENC7BIT) {
            ENCBASE64 => (string) base64_decode($body, true),
            ENCQUOTEDPRINTABLE => quoted_printable_decode($body),
            default => $body,
        };
        $body = $this->toUtf8($body, $this->partCharset($part));
        $subtype = strtoupper($part->subtype ?? 'PLAIN');
        $key = $subtype === 'HTML' ? 'html' : 'text';
        $result[$key] ??= trim($body) ?: null;
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

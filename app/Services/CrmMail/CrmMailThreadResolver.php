<?php

namespace App\Services\CrmMail;

use App\Models\CrmMailbox;
use App\Models\CrmMailMessage;
use App\Models\CrmMailThread;
use Illuminate\Database\Eloquent\Builder;

class CrmMailThreadResolver
{
    public function resolve(
        CrmMailbox $mailbox,
        string $participantEmail,
        ?int $counterpartyId = null,
        ?string $inReplyTo = null,
        ?string $subject = null,
    ): ?CrmMailThread {
        if (filled($inReplyTo)) {
            $message = CrmMailMessage::query()
                ->where('message_id', $inReplyTo)
                ->first();

            if ($message) {
                $thread = CrmMailThread::withTrashed()->find($message->thread_id);
                if ($thread && (int) $thread->mailbox_id === (int) $mailbox->id) {
                    return $thread;
                }
            }
        }

        $email = mb_strtolower(trim($participantEmail));

        $query = CrmMailThread::query()
            ->where('mailbox_id', $mailbox->id)
            ->where('status', '!=', 'spam')
            ->where(function (Builder $query) use ($counterpartyId, $email): void {
                if ($counterpartyId) {
                    $query->where('crm_counterparty_id', $counterpartyId);
                    if ($email !== '') {
                        $query->orWhereRaw('LOWER(participant_email) = ?', [$email]);
                    }

                    return;
                }

                $query->whereRaw('LOWER(participant_email) = ?', [$email]);
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        if (filled($subject)) {
            $normalizedSubject = $this->normalizeSubject((string) $subject);

            return $query->get()->first(
                fn (CrmMailThread $thread): bool => $this->normalizeSubject((string) $thread->subject) === $normalizedSubject,
            );
        }

        return $query->first();
    }

    public function latestForClient(
        CrmMailbox $mailbox,
        string $participantEmail,
        ?int $counterpartyId = null,
    ): ?CrmMailThread {
        $email = mb_strtolower(trim($participantEmail));

        return CrmMailThread::query()
            ->where('mailbox_id', $mailbox->id)
            ->whereNotIn('status', ['spam', 'draft'])
            ->where(function (Builder $query) use ($counterpartyId, $email): void {
                if ($counterpartyId) {
                    $query->where('crm_counterparty_id', $counterpartyId);

                    if ($email !== '') {
                        $query->orWhereRaw('LOWER(participant_email) = ?', [$email]);
                    }

                    return;
                }

                $query->whereRaw('LOWER(participant_email) = ?', [$email]);
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->first();
    }

    private function normalizeSubject(string $subject): string
    {
        $subject = mb_strtolower(trim($subject));

        do {
            $previous = $subject;
            $subject = preg_replace('/^(?:re|fw|fwd|ответ|пересылка)\s*:\s*/iu', '', $subject) ?? $subject;
        } while ($subject !== $previous);

        return preg_replace('/\s+/u', ' ', trim($subject)) ?? trim($subject);
    }
}

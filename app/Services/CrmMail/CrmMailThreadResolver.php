<?php

namespace App\Services\CrmMail;

use App\Models\CrmMailbox;
use App\Models\CrmMailMessage;
use App\Models\CrmMailThread;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CrmMailThreadResolver
{
    public function resolve(
        CrmMailbox $mailbox,
        string $participantEmail,
        ?int $counterpartyId = null,
        ?string $inReplyTo = null,
        ?string $subject = null,
        ?string $references = null,
    ): ?CrmMailThread {
        preg_match_all('/<([^<>]+)>/', $references ?? '', $referenceMatches);
        $referenceIds = $referenceMatches[1] ?: preg_split('/\s+/', trim($references ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        $parentIds = array_unique(array_filter([
            trim($inReplyTo ?? '', "<> \t\r\n"),
            ...array_reverse($referenceIds),
        ]));

        foreach ($parentIds as $parentId) {
            $message = CrmMailMessage::query()
                ->where('mailbox_id', $mailbox->id)
                ->where('message_id', $parentId)
                ->first();

            if ($message) {
                $thread = CrmMailThread::withTrashed()->find($message->thread_id);
                if ($thread && (int) $thread->mailbox_id === (int) $mailbox->id) {
                    return $thread;
                }
            }
        }

        if (filled($subject)) {
            $matches = $this->subjectMatches($mailbox, $participantEmail, $counterpartyId, $subject);

            return $matches->count() === 1 ? $matches->first() : null;
        }

        return $this->clientThreads($mailbox, $participantEmail, $counterpartyId)->first();
    }

    public function hasAmbiguousSubject(CrmMailbox $mailbox, string $email, ?int $counterpartyId, string $subject): bool
    {
        return $this->subjectMatches($mailbox, $email, $counterpartyId, $subject)->count() > 1;
    }

    private function subjectMatches(CrmMailbox $mailbox, string $email, ?int $counterpartyId, string $subject): Collection
    {
        $normalized = $this->normalizeSubject($subject);

        return $this->clientThreads($mailbox, $email, $counterpartyId)
            ->get()
            ->filter(function (CrmMailThread $thread) use ($normalized): bool {
                if ($this->normalizeSubject((string) $thread->subject) === $normalized) {
                    return true;
                }

                // A manager may rename a split request while the customer's mail
                // program continues replying with the original subject.
                return $thread->source_thread_id !== null && $thread->messages()
                    ->pluck('subject')->contains(fn ($subject): bool => $this->normalizeSubject((string) $subject) === $normalized);
            });
    }

    private function clientThreads(CrmMailbox $mailbox, string $participantEmail, ?int $counterpartyId): Builder
    {
        $email = mb_strtolower(trim($participantEmail));

        return CrmMailThread::query()
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

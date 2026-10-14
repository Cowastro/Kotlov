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
            ->orderByDesc('id')
            ->first();
    }
}

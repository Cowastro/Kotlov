<?php

namespace App\Services\CrmMail;

use App\Models\CrmMailThread;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CrmMailThreadMerger
{
    public function merge(CrmMailThread $source, CrmMailThread $target): CrmMailThread
    {
        if ($source->is($target)) {
            throw new RuntimeException('Нельзя объединить карточку саму с собой.');
        }

        if ((int) $source->mailbox_id !== (int) $target->mailbox_id) {
            throw new RuntimeException('Карточки относятся к разным почтовым ящикам.');
        }

        $sourceEmail = mb_strtolower(trim((string) $source->participant_email));
        $targetEmail = mb_strtolower(trim((string) $target->participant_email));
        if ($sourceEmail === '' || $sourceEmail !== $targetEmail) {
            throw new RuntimeException('Карточки относятся к разным адресатам.');
        }

        return DB::transaction(function () use ($source, $target): CrmMailThread {
            $source->messages()->update(['thread_id' => $target->id]);
            $source->commercialDocuments()->update(['crm_mail_thread_id' => $target->id]);

            $latestMessage = $target->messages()
                ->orderByDesc('received_at')
                ->orderByDesc('id')
                ->first();
            $target->forceFill([
                'crm_counterparty_id' => $target->crm_counterparty_id ?: $source->crm_counterparty_id,
                'crm_contact_id' => $target->crm_contact_id ?: $source->crm_contact_id,
                'assigned_user_id' => $target->assigned_user_id ?: $source->assigned_user_id,
                'participant_name' => $target->participant_name ?: $source->participant_name,
                'sales_stage' => $target->sales_stage ?: $source->sales_stage,
                'next_follow_up_at' => $target->next_follow_up_at ?: $source->next_follow_up_at,
                'follow_up_note' => $target->follow_up_note ?: $source->follow_up_note,
                'manager_note' => $target->manager_note ?: $source->manager_note,
                'subject' => $latestMessage?->subject ?: $target->subject,
                'messages_count' => $target->messages()->count(),
                'unread_count' => $target->messages()
                    ->where('direction', 'incoming')
                    ->where('is_read', false)
                    ->count(),
                'last_message_at' => $latestMessage?->received_at ?: $target->last_message_at,
            ])->save();

            $source->delete();

            if ($target->commercialDocuments()->where('status', 'sent')->exists()) {
                $target->markProposalSent($target->assigned_user_id);
            }

            return $target->fresh(['messages', 'commercialDocuments']);
        });
    }
}

<?php

namespace App\Services\CommercialDocuments;

use App\Models\CommercialDocument;
use App\Models\CrmMailThread;

class CommercialDocumentMailSync
{
    private static bool $syncing = false;

    public function fromMailThread(CrmMailThread $thread): void
    {
        if (self::$syncing) {
            return;
        }

        self::$syncing = true;

        try {
            $documents = $thread->commercialDocuments()
                ->currentRevision()
                ->where('type', 'quote')
                ->get();

            foreach ($documents as $document) {
                $this->applyThreadControl($thread, $document);
            }
        } finally {
            self::$syncing = false;
        }
    }

    private function applyThreadControl(CrmMailThread $thread, CommercialDocument $document): void
    {
        $requiresReaction = in_array($thread->status, ['new', 'waiting_manager'], true);
        $nextFollowUpAt = $requiresReaction
            ? ($thread->next_follow_up_at ?: now())
            : $thread->next_follow_up_at;
        $stage = match ($thread->sales_stage) {
            'proposal_sent' => 'proposal_sent',
            'contact_planned' => 'contact_planned',
            'decision_pending' => 'decision_pending',
            'negotiation' => 'negotiation',
            'needs_revision' => 'needs_revision',
            'won' => 'won',
            'lost' => 'lost',
            default => $requiresReaction ? 'negotiation' : $document->follow_up_stage,
        };
        $result = match ($stage) {
            'contact_planned' => $document->last_contact_result ?: 'callback_later',
            'decision_pending' => 'decision_pending',
            'negotiation' => 'negotiation',
            'needs_revision' => 'needs_revision',
            'won' => 'accepted',
            'lost' => 'rejected',
            default => $document->last_contact_result,
        };
        $note = filled($thread->follow_up_note)
            ? trim((string) $thread->follow_up_note)
            : (filled($thread->manager_note) ? trim((string) $thread->manager_note) : $document->follow_up_note);
        $status = match ($stage) {
            'needs_revision' => 'needs_revision',
            'won' => 'accepted',
            'lost' => 'rejected',
            default => $document->status,
        };

        $changedControl = $document->follow_up_stage !== $stage
            || $document->last_contact_result !== $result
            || $document->next_follow_up_at?->timestamp !== $nextFollowUpAt?->timestamp
            || $document->follow_up_note !== $note;

        $history = $document->contact_history ?? [];
        if ($changedControl && in_array($result, [
            'callback_later', 'decision_pending', 'negotiation', 'needs_revision', 'accepted', 'rejected',
        ], true)) {
            $history[] = [
                'contacted_at' => now()->toIso8601String(),
                'user_id' => $thread->assigned_user_id,
                'user_name' => $thread->assignedUser?->name,
                'source' => 'mail',
                'stage' => $stage,
                'result' => $result,
                'note' => $note ?: 'Изменение зафиксировано в связанной переписке.',
                'next_follow_up_at' => $nextFollowUpAt?->toIso8601String(),
            ];
        }

        $document->forceFill([
            'manager_id' => $thread->assigned_user_id ?: $document->manager_id,
            'status' => $status,
            'follow_up_stage' => $stage,
            'last_contact_result' => $result,
            'last_contacted_at' => $changedControl && $result ? now() : $document->last_contacted_at,
            'next_follow_up_at' => in_array($stage, ['won', 'lost'], true) ? null : $nextFollowUpAt,
            'follow_up_note' => $note,
            'contact_history' => array_slice($history, -100),
            'outcome_reason' => $stage === 'lost' ? ($document->outcome_reason ?: 'other') : $document->outcome_reason,
            'outcome_note' => $stage === 'lost' ? ($document->outcome_note ?: $note) : $document->outcome_note,
            'accepted_at' => $stage === 'won' ? ($document->accepted_at ?: now()) : $document->accepted_at,
            'closed_at' => $stage === 'lost' ? ($document->closed_at ?: now()) : $document->closed_at,
            'last_control_notified_at' => $changedControl ? null : $document->last_control_notified_at,
        ])->saveQuietly();
    }
}

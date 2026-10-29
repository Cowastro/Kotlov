<?php

namespace App\Services\CrmMail;

use App\Models\CommercialDocument;
use App\Models\CrmMailThread;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CrmMailClientContext
{
    /** @return Collection<int, CrmMailThread> */
    public function threads(CrmMailThread $thread): Collection
    {
        if ($thread->relationLoaded('clientHistoryThreads')) {
            return $thread->getRelation('clientHistoryThreads');
        }

        $threads = $this->relatedThreadsQuery($thread)
            ->with('assignedUser')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();

        $thread->setRelation('clientHistoryThreads', $threads);

        return $threads;
    }

    /** @return Collection<int, CommercialDocument> */
    public function commercialDocuments(CrmMailThread $thread): Collection
    {
        if ($thread->relationLoaded('clientHistoryCommercialDocuments')) {
            return $thread->getRelation('clientHistoryCommercialDocuments');
        }

        $relatedThreadIds = $this->threads($thread)->pluck('id');

        $documents = CommercialDocument::query()
            ->where(function (Builder $query) use ($thread, $relatedThreadIds): void {
                if ($thread->crm_counterparty_id) {
                    $query->where(function (Builder $counterpartyDocuments) use ($thread, $relatedThreadIds): void {
                        $counterpartyDocuments
                            ->where('crm_counterparty_id', $thread->crm_counterparty_id)
                            ->where(function (Builder $visibleDocuments) use ($relatedThreadIds): void {
                                $visibleDocuments->whereNull('crm_mail_thread_id');

                                if ($relatedThreadIds->isNotEmpty()) {
                                    $visibleDocuments->orWhereIn('crm_mail_thread_id', $relatedThreadIds);
                                }
                            });
                    });

                    if ($relatedThreadIds->isNotEmpty()) {
                        $query->orWhereIn('crm_mail_thread_id', $relatedThreadIds);
                    }

                    return;
                }

                $query->whereIn('crm_mail_thread_id', $relatedThreadIds);
            })
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->get();

        $thread->setRelation('clientHistoryCommercialDocuments', $documents);

        return $documents;
    }

    public function latestQuote(CrmMailThread $thread): ?CommercialDocument
    {
        return $this->commercialDocuments($thread)
            ->where('type', 'quote')
            ->where('status', '!=', 'cancelled')
            ->sortByDesc(fn (CommercialDocument $document): array => [
                $document->issued_at?->timestamp ?? $document->created_at?->timestamp ?? 0,
                (int) ($document->revision_number ?: 1),
                $document->id,
            ])
            ->first();
    }

    public function effectiveSalesStage(CrmMailThread $thread): ?string
    {
        if (filled($thread->sales_stage)) {
            return $thread->sales_stage;
        }

        $relatedStage = $this->threads($thread)
            ->first(fn (CrmMailThread $related): bool => filled($related->sales_stage))
            ?->sales_stage;

        if (filled($relatedStage)) {
            return $relatedStage;
        }

        return $this->commercialDocuments($thread)->contains(
            fn (CommercialDocument $document): bool => $document->type === 'quote'
                && in_array($document->status, ['sent', 'needs_revision', 'accepted', 'paid'], true),
        ) ? 'proposal_sent' : null;
    }

    public function previousThread(CrmMailThread $thread): ?CrmMailThread
    {
        return $this->threads($thread)->first(
            fn (CrmMailThread $related): bool => ! $related->is($thread),
        );
    }

    public function historySummary(CrmMailThread $thread): string
    {
        $threads = $this->threads($thread);
        $quotes = $this->commercialDocuments($thread)
            ->where('type', 'quote')
            ->where('status', '!=', 'cancelled')
            ->count();

        $parts = [trans_choice('{0} Нет других переписок|{1} :count переписка|[2,4] :count переписки|[5,*] :count переписок', $threads->count(), [
            'count' => $threads->count(),
        ])];

        if ($quotes > 0) {
            $parts[] = 'КП: '.$quotes;
        }

        return implode(' · ', $parts);
    }

    private function relatedThreadsQuery(CrmMailThread $thread): Builder
    {
        $email = mb_strtolower(trim((string) $thread->participant_email));

        return CrmMailThread::query()
            ->visibleTo(auth()->user())
            ->where('status', '!=', 'spam')
            ->where(function (Builder $query) use ($thread, $email): void {
                if ($thread->crm_counterparty_id) {
                    $query->where('crm_counterparty_id', $thread->crm_counterparty_id);

                    if ($email !== '') {
                        $query->orWhereRaw('LOWER(participant_email) = ?', [$email]);
                    }

                    return;
                }

                $query->whereRaw('LOWER(participant_email) = ?', [$email]);
            });
    }
}

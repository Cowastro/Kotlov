<?php

namespace App\Services\CommercialDocuments;

use App\Models\CommercialDocument;

class CommercialDocumentAnalytics
{
    /** @return array<string, int|float|string|null> */
    public function summary(): array
    {
        $periodStart = today()->subDays(29);
        $activeQuotes = CommercialDocument::query()->where('type', 'quote')->whereIn('status', ['sent', 'needs_revision']);
        $needsAction = CommercialDocument::query()
            ->where('type', 'quote')
            ->where(function ($control): void {
                $control->where('status', 'needs_revision')
                    ->orWhere(function ($sent): void {
                    $sent->where('status', 'sent')->whereDate('valid_until', '<=', today()->addDays(3));
                })->orWhere(function ($accepted): void {
                    $accepted->where('status', 'accepted')->whereDoesntHave('order');
                });
            });

        $won = CommercialDocument::query()
            ->where('type', 'quote')
            ->where('status', 'accepted')
            ->whereDate('accepted_at', '>=', $periodStart)
            ->count();
        $lostQuery = CommercialDocument::query()
            ->where('type', 'quote')
            ->whereIn('status', ['rejected', 'expired'])
            ->whereDate('closed_at', '>=', $periodStart);
        $lost = (clone $lostQuery)->count();
        $decided = $won + $lost;
        $topReason = (clone $lostQuery)
            ->whereNotNull('outcome_reason')
            ->selectRaw('outcome_reason, COUNT(*) as aggregate')
            ->groupBy('outcome_reason')
            ->orderByDesc('aggregate')
            ->first();
        $unpaidInvoices = CommercialDocument::query()
            ->where('type', 'invoice')
            ->where('status', 'sent');

        return [
            'active_count' => (clone $activeQuotes)->count(),
            'active_total' => (float) (clone $activeQuotes)->sum('total'),
            'needs_action_count' => (clone $needsAction)->count(),
            'needs_action_total' => (float) (clone $needsAction)->sum('total'),
            'won_count' => $won,
            'lost_count' => $lost,
            'conversion_percent' => $decided > 0 ? round($won / $decided * 100, 1) : 0.0,
            'unpaid_invoice_count' => (clone $unpaidInvoices)->count(),
            'unpaid_invoice_total' => (float) (clone $unpaidInvoices)->sum('total'),
            'top_loss_reason' => $topReason?->outcome_reason,
            'top_loss_reason_count' => (int) ($topReason?->aggregate ?? 0),
            'period_start' => $periodStart->format('d.m.Y'),
        ];
    }
}

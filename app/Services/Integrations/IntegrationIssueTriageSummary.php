<?php

namespace App\Services\Integrations;

use App\Models\IntegrationIssue;

class IntegrationIssueTriageSummary
{
    /**
     * @return array{
     *     priority:int,
     *     missing_price:int,
     *     ready_to_link:int,
     *     recommended:int,
     *     possible_duplicates:int,
     *     orders:int,
     *     exchange:int,
     *     mine:int
     * }
     */
    public function snapshot(?int $userId = null): array
    {
        $open = fn () => IntegrationIssue::query()->open();

        return [
            'priority' => $open()->priority()->count(),
            'missing_price' => $open()->missingPrice()->count(),
            'ready_to_link' => $open()->readyToLink()->count(),
            'recommended' => $open()->recommendedMatches()->count(),
            'possible_duplicates' => $open()->possibleDuplicates()->count(),
            'orders' => $open()->orders()->count(),
            'exchange' => $open()->exchange()->count(),
            'mine' => $userId ? $open()->assignedTo($userId)->count() : 0,
        ];
    }
}

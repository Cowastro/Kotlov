<?php

namespace App\Services;

use App\Models\B2bSpecificationItem;
use App\Models\Product;
use App\Models\User;

class B2bSpecificationItemDecision
{
    public function confirmTopCandidate(B2bSpecificationItem $item, User $reviewer): bool
    {
        if ($item->product_id || $item->status !== 'suggested') {
            return false;
        }

        $candidate = $item->topCandidate();
        $product = $candidate
            ? Product::query()->active()->notArchived()->find((int) $candidate['product_id'])
            : null;

        if (! $product) {
            return false;
        }

        $item->update([
            'product_id' => $product->id,
            'status' => 'matched',
            'confidence' => round((float) ($candidate['score'] ?? 0) * 100, 2),
            'match_reason' => 'manager_candidate',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        return true;
    }

    public function ignore(B2bSpecificationItem $item, User $reviewer): void
    {
        $item->update([
            'product_id' => null,
            'status' => 'ignored',
            'confidence' => null,
            'match_reason' => 'manager_ignore',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);
    }
}

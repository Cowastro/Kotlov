<?php

namespace App\Services\Integrations;

use App\Models\IntegrationProduct;
use App\Models\Product;

class IntegrationCategoryAdvisor
{
    /** @return array{category_id:int,category_name:string,confidence:float,reason:string}|null */
    public function suggest(IntegrationProduct $item): ?array
    {
        if ($item->product_id || $item->target_category_id || $item->integrationCategory?->category_id) {
            return null;
        }

        $candidates = collect($item->candidates ?? [])
            ->filter(fn (array $candidate): bool => filled($candidate['product_id'] ?? null))
            ->sortByDesc(fn (array $candidate): float => (float) ($candidate['score'] ?? 0))
            ->values();
        if ($candidates->isEmpty()) {
            return null;
        }

        $products = Product::query()
            ->with('category:id,name')
            ->whereIn('id', $candidates->pluck('product_id')->all())
            ->get(['id', 'category_id'])
            ->keyBy('id');
        $ranked = $candidates
            ->map(function (array $candidate) use ($products): ?array {
                $product = $products->get((int) $candidate['product_id']);
                if (! $product?->category) {
                    return null;
                }

                return [
                    'category_id' => (int) $product->category->id,
                    'category_name' => $product->category->name,
                    'score' => (float) ($candidate['score'] ?? 0),
                ];
            })
            ->filter()
            ->values();
        if ($ranked->isEmpty() || $ranked->first()['score'] < 0.65) {
            return null;
        }

        $best = $ranked->first();
        $second = $ranked->get(1);
        $sameCategory = $ranked->pluck('category_id')->unique()->count() === 1;
        $clearLead = ! $second || $best['score'] - $second['score'] >= 0.08;
        if (! $sameCategory && ! $clearLead) {
            return null;
        }

        $confidence = $sameCategory
            ? max(0.75, $best['score'])
            : $best['score'];

        return [
            'category_id' => $best['category_id'],
            'category_name' => $best['category_name'],
            'confidence' => round($confidence, 2),
            'reason' => $sameCategory && $ranked->count() > 1
                ? 'Все найденные похожие карточки относятся к одной категории.'
                : 'Лучший кандидат заметно точнее остальных и относится к этой категории.',
        ];
    }
}

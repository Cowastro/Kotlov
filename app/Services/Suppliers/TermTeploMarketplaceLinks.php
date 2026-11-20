<?php

namespace App\Services\Suppliers;

use Illuminate\Support\Collection;

class TermTeploMarketplaceLinks
{
    /** Match the supplier catalogue even when KOTLOV has no active retail card yet. */
    public function resolve(Collection $offers, Collection $remote): array
    {
        $articles = $offers->groupBy(fn ($offer) => mb_strtoupper(trim($offer->supplier_article)));
        $models = $offers->groupBy(fn ($offer) => TermTeploModel::key($offer->supplier_name ?? '', $offer->raw['title'] ?? '') ?? '')->forget('');
        $skuCounts = $remote->countBy(fn ($item) => mb_strtoupper(trim((string) ($item['sku'] ?? ''))));
        $modelCounts = $remote->countBy(fn ($item) => TermTeploModel::key($item['name'] ?? '') ?? '');
        $links = [];
        foreach ($remote as $item) {
            $sku = mb_strtoupper(trim((string) ($item['sku'] ?? '')));
            $name = $item['name'] ?? '';
            $model = TermTeploModel::key($name);
            if ($sku !== '') {
                if ($skuCounts[$sku] !== 1 || TermTeploModel::article($sku, $name) !== $sku || str_starts_with($sku, 'MODEL-')) {
                    continue;
                }
                $candidates = $articles->get($sku);
            } else {
                $candidates = $model && $modelCounts[$model] === 1 ? $models->get($model) : null;
            }
            if ($candidates?->count() !== 1 || empty($item['id'])) {
                continue;
            }
            $offer = $candidates->first();
            $expectedModel = TermTeploModel::key($offer->supplier_name ?? '', $offer->raw['title'] ?? '');
            if (($expectedModel && $model && $expectedModel !== $model)
                || ($offer->product_id && preg_match('/^kotlov-(\d+)$/i', $item['external_id'] ?? '', $m) && (int) $m[1] !== (int) $offer->product_id)
                || (! empty($offer->raw['deal_by_id']) && (int) $offer->raw['deal_by_id'] !== (int) $item['id'])) {
                continue;
            }
            $links[$offer->id][] = (int) $item['id'];
        }

        return array_map(fn ($ids) => $ids[0], array_filter($links, fn ($ids) => count($ids) === 1));
    }
}

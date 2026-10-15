<?php

namespace App\Services\Market;

use App\Models\MarketResearchReport;
use Illuminate\Support\Collection;

class MarketResearchReportPresenter
{
    /** @return array{research: array<string, mixed>, pricing: array<string, mixed>, decision: array<string, mixed>} */
    public function present(MarketResearchReport $report): array
    {
        $report->loadMissing(['product.supplierProducts.supplier']);

        $payload = is_array($report->research_payload) ? $report->research_payload : [];
        $offers = $this->offers($payload, $report);
        $market = $this->market((array) ($payload['market'] ?? []), $offers, $report);
        $quotePrice = $this->number($payload['quote_price'] ?? $report->price_before ?? $report->price_after);
        $median = $this->number($market['median'] ?? null);

        if ($quotePrice !== null && $quotePrice > 0 && $median !== null && $median > 0) {
            $market['quote_delta_percent'] = round(($quotePrice - $median) / $median * 100, 1);
        }

        return [
            'research' => [
                'status' => 'ready',
                'product_name' => $report->product?->name ?? ($payload['product_name'] ?? 'Товар удалён'),
                'sku' => $report->product?->sku ?? ($payload['sku'] ?? null),
                'quote_price' => $quotePrice,
                'confidence' => $report->confidence ?: ($payload['confidence'] ?? 'low'),
                'summary' => $report->summary ?: ($payload['summary'] ?? null),
                'verdict' => $report->verdict ?: ($payload['verdict'] ?? null),
                'search_queries' => array_values((array) ($report->search_queries ?: ($payload['search_queries'] ?? []))),
                'model' => $report->research_model ?: ($payload['model'] ?? null),
                'searched_at' => $report->researched_at ?: $report->applied_at,
                'market' => $market,
                'offers' => $offers,
                'sources' => $this->sources($payload, $offers),
                'rejected_candidates' => $this->rejected($payload, $report),
                'archive' => [
                    'id' => $report->getKey(),
                    'product_id' => $report->product_id,
                    'reused' => true,
                    'saved_at' => $report->applied_at,
                ],
            ],
            'pricing' => $this->pricing($report, $quotePrice),
            'decision' => [
                'batch_code' => $report->batch_code,
                'label' => $report->decisionLabel(),
                'reason' => $report->decision_reason,
                'review_reason' => $report->review_reason,
                'price_before' => $this->number($report->price_before),
                'recommended_price' => $this->number($report->recommended_price),
                'price_after' => $this->number($report->price_after),
                'requires_confirmation' => (bool) $report->requires_price_confirmation,
                'applied_at' => $report->applied_at,
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function offers(array $payload, MarketResearchReport $report): array
    {
        $sourceOffers = (array) ($payload['offers'] ?? []);
        if ($sourceOffers === []) {
            $sourceOffers = (array) ($report->observations_snapshot ?? []);
        }

        return collect($sourceOffers)
            ->filter(fn (mixed $offer): bool => is_array($offer))
            ->map(function (array $offer): array {
                $url = trim((string) ($offer['url'] ?? $offer['source_url'] ?? ''));
                $domain = trim((string) ($offer['domain'] ?? ''));
                if ($domain === '' && $url !== '') {
                    $domain = (string) parse_url($url, PHP_URL_HOST);
                }

                $price = $this->number($offer['price'] ?? $offer['observed_price'] ?? $offer['price_byn'] ?? null);
                $priceByn = $this->number($offer['price_byn'] ?? $offer['observed_price'] ?? $offer['price'] ?? null);

                return [
                    'seller' => ($offer['seller'] ?? $offer['source_name'] ?? $offer['source'] ?? $domain) ?: 'Источник',
                    'domain' => $domain,
                    'title' => $offer['title'] ?? $offer['external_name'] ?? $offer['model'] ?? 'Карточка товара',
                    'url' => $url,
                    'price' => $price,
                    'currency' => $offer['currency'] ?? 'BYN',
                    'price_byn' => $priceByn,
                    'availability' => $offer['availability'] ?? $offer['availability_status'] ?? $offer['stock_status'] ?? 'Наличие не подтверждено',
                    'vat' => $offer['vat'] ?? null,
                    'package' => $offer['package'] ?? null,
                    'match' => $offer['match'] ?? $offer['match_status'] ?? 'exact',
                    'evidence' => $offer['evidence'] ?? $offer['reason'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    /** @param array<int, array<string, mixed>> $offers */
    private function market(array $market, array $offers, MarketResearchReport $report): array
    {
        $prices = collect($offers)
            ->map(fn (array $offer): ?float => $this->number($offer['price_byn'] ?? $offer['price'] ?? null))
            ->filter(fn (?float $price): bool => $price !== null && $price > 0)
            ->sort()
            ->values();

        if ($prices->isNotEmpty()) {
            $market['count'] = $market['count'] ?? $prices->count();
            $market['minimum'] = $market['minimum'] ?? $prices->first();
            $market['median'] = $market['median'] ?? $this->median($prices);
            $market['maximum'] = $market['maximum'] ?? $prices->last();
        }

        $market['count'] = (int) ($market['count'] ?? count($offers));
        $market['minimum'] = $this->number($market['minimum'] ?? null);
        $market['median'] = $this->number($market['median'] ?? $report->recommended_price);
        $market['maximum'] = $this->number($market['maximum'] ?? null);

        return $market;
    }

    /** @param Collection<int, float> $prices */
    private function median(Collection $prices): float
    {
        $count = $prices->count();
        $middle = intdiv($count, 2);

        return $count % 2 === 0
            ? round(((float) $prices[$middle - 1] + (float) $prices[$middle]) / 2, 2)
            : (float) $prices[$middle];
    }

    /** @param array<int, array<string, mixed>> $offers
     *  @return array<int, array{title: string, url: string}>
     */
    private function sources(array $payload, array $offers): array
    {
        $sources = collect((array) ($payload['sources'] ?? []))
            ->filter(fn (mixed $source): bool => is_array($source) && filled($source['url'] ?? null))
            ->map(fn (array $source): array => [
                'title' => (string) ($source['title'] ?? $source['name'] ?? parse_url((string) $source['url'], PHP_URL_HOST) ?: 'Источник'),
                'url' => (string) $source['url'],
            ]);

        if ($sources->isEmpty()) {
            $sources = collect($offers)
                ->filter(fn (array $offer): bool => filled($offer['url'] ?? null))
                ->map(fn (array $offer): array => [
                    'title' => (string) ($offer['seller'] ?? $offer['domain'] ?? 'Источник'),
                    'url' => (string) $offer['url'],
                ]);
        }

        return $sources->unique('url')->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function rejected(array $payload, MarketResearchReport $report): array
    {
        return collect([
            ...(array) ($payload['rejected_candidates'] ?? []),
            ...(array) ($report->rejected_candidates ?? []),
            ...(array) ($report->excluded_offers ?? []),
        ])->filter(fn (mixed $candidate): bool => is_array($candidate))
            ->unique(fn (array $candidate): string => (string) ($candidate['url'] ?? '').'|'.(string) ($candidate['title'] ?? ''))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function pricing(MarketResearchReport $report, ?float $quotePrice): array
    {
        $offer = $report->product?->supplierProducts
            ?->sortByDesc(fn ($item): int => (int) ($item->stock_quantity ?? 0))
            ->first(fn ($item): bool => (float) ($item->price_byn ?? 0) > 0);
        $purchasePrice = $offer ? (float) $offer->price_byn : null;
        $margin = $purchasePrice !== null && $quotePrice !== null ? round($quotePrice - $purchasePrice, 2) : null;
        $minimumMargin = (float) config('shop.order_management.minimum_margin_percent', 10);

        return [
            'supplier_name' => $offer?->supplier?->name,
            'purchase_price' => $purchasePrice,
            'margin_byn' => $margin,
            'margin_percent' => $margin !== null && $quotePrice > 0 ? round($margin / $quotePrice * 100, 1) : null,
            'minimum_safe_price' => $purchasePrice !== null && $minimumMargin < 100
                ? round($purchasePrice / (1 - $minimumMargin / 100), 2)
                : null,
        ];
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}

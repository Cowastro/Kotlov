<?php

namespace App\Services\Market;

use App\Models\Category;
use App\Models\MarketPriceObservation;
use App\Models\OrderItem;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class MarketCategoryResearch
{
    private const TREND_PERIODS = [7, 30, 90];

    private const MINIMUM_TREND_PAIRS = 3;

    private const CONFIRMED_DEMAND_STATUSES = ['confirmed', 'processing', 'shipped', 'delivered'];

    /** @return Collection<int, array<string, mixed>> */
    public function rows(?CarbonInterface $asOf = null): Collection
    {
        $asOf ??= now();
        $observations = MarketPriceObservation::query()
            ->with([
                'source:id,name,is_active,freshness_hours,minimum_match_confidence',
                'product:id,category_id,name,sku',
            ])
            ->whereHas('source', fn ($query) => $query->where('is_active', true))
            ->whereHas('product', fn ($query) => $query->whereNotNull('category_id'))
            ->where('observed_at', '>=', $asOf->copy()->subDays(max(self::TREND_PERIODS)))
            ->get()
            ->filter(fn (MarketPriceObservation $observation): bool => $this->isComparable($observation));

        if ($observations->isEmpty()) {
            return collect();
        }

        $categories = Category::query()
            ->whereIn('id', $observations->pluck('product.category_id')->filter()->unique())
            ->get(['id', 'name', 'slug'])
            ->keyBy('id');
        $demand = $this->confirmedDemand($asOf);

        return $observations
            ->groupBy(fn (MarketPriceObservation $observation): int => (int) $observation->product->category_id)
            ->map(function (Collection $categoryObservations, int|string $categoryId) use ($asOf, $categories, $demand): array {
                $current = $this->latestFreshOffers($categoryObservations, $asOf);
                $availabilityBase = $current->filter(fn (MarketPriceObservation $observation): bool => in_array(
                    $observation->availability_status,
                    ['in_stock', 'order', 'out_of_stock'],
                    true,
                ));
                $availableCount = $availabilityBase->whereIn('availability_status', ['in_stock', 'order'])->count();
                $availabilityPercent = $availabilityBase->isEmpty()
                    ? null
                    : round($availableCount / $availabilityBase->count() * 100, 1);
                $categoryDemand = $demand->get((int) $categoryId, [
                    'orders' => 0,
                    'quantity' => 0,
                    'revenue' => 0.0,
                ]);

                return [
                    'category_id' => (int) $categoryId,
                    'category_name' => $categories->get((int) $categoryId)?->name ?? 'Категория #'.$categoryId,
                    'category_slug' => $categories->get((int) $categoryId)?->slug,
                    'products_count' => $categoryObservations->pluck('product_id')->unique()->count(),
                    'fresh_products_count' => $current->pluck('product_id')->unique()->count(),
                    'fresh_offers_count' => $current->count(),
                    'fresh_sources_count' => $current->pluck('market_price_source_id')->unique()->count(),
                    'available_offers_count' => $availableCount,
                    'availability_percent' => $availabilityPercent,
                    'price_leader' => $this->priceLeader($current),
                    'trends' => collect(self::TREND_PERIODS)->mapWithKeys(fn (int $days): array => [
                        $days => $this->trend($categoryObservations, $asOf, $days),
                    ])->all(),
                    'demand_orders_90d' => $categoryDemand['orders'],
                    'demand_quantity_90d' => $categoryDemand['quantity'],
                    'demand_revenue_90d' => $categoryDemand['revenue'],
                    'opportunity' => $this->opportunity($categoryDemand['quantity'], $availabilityPercent),
                    'last_checked_at' => $current->max('observed_at') ?? $categoryObservations->max('observed_at'),
                ];
            })
            ->sortByDesc(fn (array $row): array => [
                $row['demand_quantity_90d'],
                $row['fresh_products_count'],
                $row['fresh_offers_count'],
            ])
            ->values();
    }

    private function isComparable(MarketPriceObservation $observation): bool
    {
        return $observation->source !== null
            && $observation->source->is_active === true
            && $observation->is_confirmed === true
            && $observation->is_comparable === true
            && (float) $observation->price_byn > 0
            && (float) $observation->match_confidence >= (float) $observation->source->minimum_match_confidence
            && array_values(array_filter((array) $observation->validation_flags)) === []
            && $observation->observed_at !== null;
    }

    /** @param Collection<int, MarketPriceObservation> $observations
     * @return Collection<int, MarketPriceObservation>
     */
    private function latestFreshOffers(Collection $observations, CarbonInterface $asOf): Collection
    {
        return $observations
            ->sortByDesc('observed_at')
            ->groupBy(fn (MarketPriceObservation $observation): string => $observation->product_id.'|'.$observation->market_price_source_id)
            ->map->first()
            ->filter(fn (MarketPriceObservation $observation): bool => $observation->observed_at->gte(
                $asOf->copy()->subHours((int) $observation->source->freshness_hours),
            ))
            ->values();
    }

    /** @param Collection<int, MarketPriceObservation> $observations
     * @return array{percent:?float,pairs:int,status:string}
     */
    private function trend(Collection $observations, CarbonInterface $asOf, int $days): array
    {
        $changes = $observations
            ->filter(fn (MarketPriceObservation $observation): bool => $observation->observed_at->between(
                $asOf->copy()->subDays($days),
                $asOf,
            ))
            ->groupBy(fn (MarketPriceObservation $observation): string => $observation->product_id.'|'.$observation->market_price_source_id)
            ->map(function (Collection $history): ?float {
                $history = $history->sortBy('observed_at')->values();
                if ($history->count() < 2) {
                    return null;
                }

                $first = (float) $history->first()->price_byn;
                $last = (float) $history->last()->price_byn;

                return $first > 0 ? round(($last - $first) / $first * 100, 2) : null;
            })
            ->filter(fn (?float $change): bool => $change !== null)
            ->values();

        if ($changes->count() < self::MINIMUM_TREND_PAIRS) {
            return ['percent' => null, 'pairs' => $changes->count(), 'status' => 'insufficient'];
        }

        return [
            'percent' => round($this->median($changes), 1),
            'pairs' => $changes->count(),
            'status' => 'ready',
        ];
    }

    /** @param Collection<int, MarketPriceObservation> $offers
     * @return array{name:string,wins:int,products:int}|null
     */
    private function priceLeader(Collection $offers): ?array
    {
        if ($offers->isEmpty()) {
            return null;
        }

        $wins = collect();
        foreach ($offers->groupBy('product_id') as $productOffers) {
            $minimum = (float) $productOffers->min('price_byn');
            foreach ($productOffers->filter(fn (MarketPriceObservation $offer): bool => (float) $offer->price_byn === $minimum) as $winner) {
                $sourceId = (int) $winner->market_price_source_id;
                $wins->put($sourceId, (int) $wins->get($sourceId, 0) + 1);
            }
        }

        $leaderId = $wins->sortDesc()->keys()->first();
        if ($leaderId === null) {
            return null;
        }

        $leader = $offers->firstWhere('market_price_source_id', $leaderId)?->source;

        return [
            'name' => $leader?->name ?? 'Источник #'.$leaderId,
            'wins' => (int) $wins->get($leaderId),
            'products' => $offers->pluck('product_id')->unique()->count(),
        ];
    }

    /** @return Collection<int, array{orders:int,quantity:int,revenue:float}> */
    private function confirmedDemand(CarbonInterface $asOf): Collection
    {
        return OrderItem::query()
            ->with(['order:id,status,payment_status,created_at', 'product:id,category_id'])
            ->whereNotNull('product_id')
            ->whereHas('product', fn ($query) => $query->whereNotNull('category_id'))
            ->whereHas('order', fn ($query) => $query
                ->where('created_at', '>=', $asOf->copy()->subDays(90))
                ->where(fn ($confirmed) => $confirmed
                    ->where('payment_status', 'paid')
                    ->orWhereIn('status', self::CONFIRMED_DEMAND_STATUSES)))
            ->get()
            ->groupBy(fn (OrderItem $item): int => (int) $item->product->category_id)
            ->map(fn (Collection $items): array => [
                'orders' => $items->pluck('order_id')->unique()->count(),
                'quantity' => (int) $items->sum('quantity'),
                'revenue' => round((float) $items->sum('total'), 2),
            ]);
    }

    /** @return array{label:string,tone:string,description:string} */
    private function opportunity(int $demandQuantity, ?float $availabilityPercent): array
    {
        return match (true) {
            $demandQuantity > 0 && $availabilityPercent !== null && $availabilityPercent <= 40 => [
                'label' => 'Спрос при дефиците рынка',
                'tone' => 'danger',
                'description' => 'Есть подтверждённый спрос, а на рынке мало свежих предложений в наличии.',
            ],
            $demandQuantity > 0 && $availabilityPercent === null => [
                'label' => 'Спрос есть, рынок не подтверждён',
                'tone' => 'warning',
                'description' => 'Перед выводом нужны свежие данные о наличии у конкурентов.',
            ],
            $demandQuantity > 0 => [
                'label' => 'Спрос подтверждён',
                'tone' => 'success',
                'description' => 'Сопоставьте динамику цен с маржой и остатком перед решением.',
            ],
            default => [
                'label' => 'Спрос не подтверждён',
                'tone' => 'muted',
                'description' => 'Нет оплаченных или подтверждённых заказов за 90 дней.',
            ],
        };
    }

    /** @param Collection<int, float> $values */
    private function median(Collection $values): float
    {
        $values = $values->sort()->values();
        $middle = intdiv($values->count(), 2);

        return $values->count() % 2 === 1
            ? (float) $values[$middle]
            : ((float) $values[$middle - 1] + (float) $values[$middle]) / 2;
    }
}

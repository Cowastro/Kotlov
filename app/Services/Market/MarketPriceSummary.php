<?php

namespace App\Services\Market;

use App\Models\MarketPriceObservation;
use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class MarketPriceSummary
{
    public const MINIMUM_SOURCES = 3;

    public function forProduct(Product $product, ?CarbonInterface $asOf = null): array
    {
        $asOf ??= now();
        $observations = $product->relationLoaded('marketPriceObservations')
            ? $product->marketPriceObservations
            : $product->marketPriceObservations()->with('source')->get();

        return $this->fromObservations($product, $observations, $asOf);
    }

    /** @param Collection<int, MarketPriceObservation> $observations */
    public function fromObservations(Product $product, Collection $observations, CarbonInterface $asOf): array
    {
        $latestBySource = $observations
            ->filter(fn (MarketPriceObservation $observation): bool => $observation->source?->is_active === true)
            ->sortByDesc('observed_at')
            ->groupBy('market_price_source_id')
            ->map->first();

        $eligible = $latestBySource
            ->filter(fn (MarketPriceObservation $observation): bool => $this->isEligible($observation, $asOf))
            ->values();

        $evidence = $latestBySource
            ->values()
            ->map(fn (MarketPriceObservation $observation): array => [
                'observation' => $observation,
                ...$this->assessment($observation, $asOf),
            ]);

        $base = [
            'status' => 'insufficient',
            'label' => 'Недостаточно данных',
            'offers_count' => $eligible->count(),
            'sources_count' => $eligible->pluck('market_price_source_id')->unique()->count(),
            'our_price' => $product->price !== null ? (float) $product->price : null,
            'minimum' => null,
            'median' => null,
            'maximum' => null,
            'delta_byn' => null,
            'delta_percent' => null,
            'position' => null,
            'latest_observed_at' => $eligible->max('observed_at'),
            'last_checked_at' => $latestBySource->max('observed_at'),
            'observations' => $eligible,
            'evidence' => $evidence,
        ];

        if ($eligible->count() < self::MINIMUM_SOURCES) {
            return $base + [
                'reason' => 'Нужно минимум '.self::MINIMUM_SOURCES.' свежих подтверждённых независимых источника.',
            ];
        }

        $prices = $eligible->pluck('price_byn')->map(fn ($price): float => (float) $price)->sort()->values();
        $minimum = (float) $prices->first();
        $maximum = (float) $prices->last();
        $median = $this->median($prices);
        $ourPrice = $base['our_price'];

        if ($ourPrice === null || $ourPrice <= 0) {
            return $base + [
                'minimum' => $minimum,
                'median' => $median,
                'maximum' => $maximum,
                'reason' => 'На сайте не задана розничная цена для сравнения.',
            ];
        }

        $delta = round($ourPrice - $median, 2);
        $deltaPercent = $median > 0 ? round(($delta / $median) * 100, 1) : null;
        $position = match (true) {
            $deltaPercent < -5 => 'below',
            $deltaPercent > 5 => 'above',
            default => 'market',
        };

        return [
            ...$base,
            'status' => 'ready',
            'label' => match ($position) {
                'below' => 'Ниже рынка',
                'above' => 'Выше рынка',
                default => 'В рынке',
            },
            'minimum' => $minimum,
            'median' => $median,
            'maximum' => $maximum,
            'delta_byn' => $delta,
            'delta_percent' => $deltaPercent,
            'position' => $position,
            'reason' => null,
        ];
    }

    private function isEligible(MarketPriceObservation $observation, CarbonInterface $asOf): bool
    {
        return $this->assessment($observation, $asOf)['eligible'];
    }

    /** @return array{eligible: bool, code: string, label: string, color: string} */
    private function assessment(MarketPriceObservation $observation, CarbonInterface $asOf): array
    {
        $source = $observation->source;
        $flags = array_values(array_filter((array) $observation->validation_flags));

        [$eligible, $code, $label, $color] = match (true) {
            $source === null => [false, 'missing_source', 'Источник недоступен', 'danger'],
            ! $observation->is_confirmed => [false, 'unconfirmed', 'Не подтверждено', 'warning'],
            ! $observation->is_comparable => [false, 'not_comparable', 'Не сопоставимо', 'warning'],
            (float) $observation->price_byn <= 0 => [false, 'invalid_price', 'Некорректная цена', 'danger'],
            (float) $observation->match_confidence < (float) $source->minimum_match_confidence => [false, 'low_confidence', 'Низкая уверенность', 'warning'],
            $flags !== [] => [false, 'validation_flags', 'Требует проверки', 'warning'],
            $observation->observed_at === null => [false, 'missing_date', 'Нет времени проверки', 'danger'],
            $observation->observed_at->lt($asOf->copy()->subHours($source->freshness_hours)) => [false, 'stale', 'Данные устарели', 'warning'],
            default => [true, 'eligible', 'Учитывается', 'success'],
        };

        return compact('eligible', 'code', 'label', 'color');
    }

    /** @param Collection<int, float> $values */
    private function median(Collection $values): float
    {
        $count = $values->count();
        $middle = intdiv($count, 2);

        return $count % 2 === 1
            ? (float) $values[$middle]
            : round(((float) $values[$middle - 1] + (float) $values[$middle]) / 2, 2);
    }
}

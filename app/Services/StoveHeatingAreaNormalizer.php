<?php

namespace App\Services;

class StoveHeatingAreaNormalizer
{
    public const UNDER_50 = 'Менее 50 м2';

    public const FROM_50_TO_100 = '50 м2 - 100 м2';

    public const OVER_100 = 'Более 100 м2';

    private const AREA_KEYS = [
        'площадь отапливаемого помещения',
        'площадь отопления',
        'площадь обогрева',
        'отапливаемая площадь',
        'максимальная площадь обогрева',
        'максимальная отапливаемая площадь',
    ];

    /**
     * Return a canonical range only when an explicit heated-area fact exists.
     * Conflicting facts and volume/power based estimates are deliberately ignored.
     *
     * @param  array<int|string, mixed>  $specs
     * @param  iterable<int|string, mixed>  $attributes
     */
    public function detect(array $specs = [], iterable $attributes = []): ?string
    {
        $facts = [];

        foreach ($attributes as $key => $value) {
            if (is_array($value) && isset($value['name'], $value['value'])) {
                $this->addFact($facts, (string) $value['name'], $value['value']);

                continue;
            }

            if (is_string($key)) {
                $this->addFact($facts, $key, $value);
            }
        }

        foreach ($specs as $key => $spec) {
            if (is_array($spec) && isset($spec['key'], $spec['value'])) {
                $this->addFact($facts, (string) $spec['key'], $spec['value']);

                continue;
            }

            if (is_string($key)) {
                $this->addFact($facts, $key, $spec);
            }
        }

        $facts = array_values(array_unique($facts));

        return count($facts) === 1 ? $facts[0] : null;
    }

    public function classify(string $value): ?string
    {
        $normalized = $this->normalize($value);

        if (preg_match('/(?:более|свыше|от)\s*100(?:\D|$)/u', $normalized)) {
            return self::OVER_100;
        }

        if (preg_match('/(?:менее|до)\s*50(?:\D|$)/u', $normalized)) {
            return self::UNDER_50;
        }

        if (preg_match('/50\s*(?:-|до)\s*100(?:\D|$)/u', $normalized)) {
            return self::FROM_50_TO_100;
        }

        if (! preg_match_all('/\d+(?:[.,]\d+)?/u', $normalized, $matches) || $matches[0] === []) {
            return null;
        }

        $numbers = array_map(
            fn (string $number) => (float) str_replace(',', '.', $number),
            $matches[0]
        );
        $area = max($numbers);

        if ($area <= 0 || $area > 2000) {
            return null;
        }

        if ($area < 50) {
            return self::UNDER_50;
        }

        if ($area <= 100) {
            return self::FROM_50_TO_100;
        }

        return self::OVER_100;
    }

    public function isAreaKey(string $key): bool
    {
        return in_array($this->normalize($key), self::AREA_KEYS, true);
    }

    private function addFact(array &$facts, string $key, mixed $value): void
    {
        if (! $this->isAreaKey($key) || ! is_scalar($value)) {
            return;
        }

        $fact = $this->classify((string) $value);
        if ($fact !== null) {
            $facts[] = $fact;
        }
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['ё', '²', '—', '–'], ['е', '2', '-', '-'], $value);

        return preg_replace('/\s+/u', ' ', $value) ?? '';
    }
}

<?php

namespace App\Services;

class StoveHeatingVolumeNormalizer
{
    public const UP_TO_100 = 'До 100 м3';

    public const FROM_101_TO_200 = '101 м3 - 200 м3';

    public const OVER_200 = 'Более 200 м3';

    private const VOLUME_KEYS = [
        'объем помещения',
        'объем отапливаемого помещения',
        'объем обогрева',
        'отапливаемый объем',
        'максимальный объем помещения',
    ];

    /** @param array<int|string, mixed> $specs */
    public function detect(array $specs): ?string
    {
        $facts = [];

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

        if (! preg_match_all('/\d+(?:[.,]\d+)?/u', $normalized, $matches) || $matches[0] === []) {
            return null;
        }

        $numbers = array_map(
            fn (string $number) => (float) str_replace(',', '.', $number),
            $matches[0]
        );
        $maximum = max($numbers);

        if ($maximum <= 0 || $maximum > 5000) {
            return null;
        }

        if ($maximum <= 100) {
            return self::UP_TO_100;
        }

        if ($maximum <= 200) {
            return self::FROM_101_TO_200;
        }

        return self::OVER_200;
    }

    public function isVolumeKey(string $key): bool
    {
        $normalized = $this->normalize($key);
        $withoutUnit = preg_replace('/\s*[,;:]?\s*(?:м3|куб\.?\s*м)\.?\s*$/u', '', $normalized) ?? $normalized;

        return in_array(trim($withoutUnit), self::VOLUME_KEYS, true);
    }

    private function addFact(array &$facts, string $key, mixed $value): void
    {
        if (! $this->isVolumeKey($key) || ! is_scalar($value)) {
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
        $value = str_replace(['ё', '³', '—', '–'], ['е', '3', '-', '-'], $value);

        return preg_replace('/\s+/u', ' ', $value) ?? '';
    }
}

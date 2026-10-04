<?php

namespace App\Services;

class StoveHeatingAreaFromVolumeConverter
{
    public const STANDARD_CEILING_HEIGHT = 2.5;

    private const VOLUME_KEYS = [
        'объем отапливаемого помещения',
        'отапливаемый объем',
        'объем помещения',
        'объем обогрева',
        'максимальный объем обогрева',
        'объем отопления',
    ];

    /**
     * Convert the manufacturer's maximum heated volume to an indicative area
     * using the catalogue-wide standard ceiling height requested by KOTLOV.
     */
    public function convert(string $volume): ?float
    {
        $normalized = str_replace(',', '.', $volume);

        if (! preg_match_all('/\d+(?:\.\d+)?/u', $normalized, $matches) || $matches[0] === []) {
            return null;
        }

        $maximum = max(array_map('floatval', $matches[0]));
        if ($maximum <= 0 || $maximum > 5000) {
            return null;
        }

        return round($maximum / self::STANDARD_CEILING_HEIGHT, 1);
    }

    /**
     * Return one unambiguous derived area from structured specification facts.
     * Free-form descriptions are intentionally excluded.
     *
     * @param  array<int|string, mixed>  $specs
     * @param  iterable<int|string, mixed>  $attributes
     */
    public function detect(array $specs = [], iterable $attributes = []): ?float
    {
        $areas = [];

        foreach ($attributes as $key => $value) {
            if (is_array($value) && isset($value['name'], $value['value'])) {
                $this->addFact($areas, (string) $value['name'], $value['value']);

                continue;
            }

            if (is_string($key)) {
                $this->addFact($areas, $key, $value);
            }
        }

        foreach ($specs as $key => $spec) {
            if (is_array($spec) && isset($spec['key'], $spec['value'])) {
                $this->addFact($areas, (string) $spec['key'], $spec['value']);

                continue;
            }

            if (is_string($key)) {
                $this->addFact($areas, $key, $spec);
            }
        }

        $areas = array_values(array_unique($areas));

        return count($areas) === 1 ? $areas[0] : null;
    }

    public function isVolumeKey(string $key): bool
    {
        $normalized = $this->normalize($key);
        $withoutUnit = preg_replace(
            '/\s*[,;:]?\s*(?:м3|куб\.?\s*м)\.?\s*$/u',
            '',
            $normalized,
        ) ?? $normalized;

        return in_array(trim($withoutUnit), self::VOLUME_KEYS, true);
    }

    public function label(float $area): string
    {
        $formatted = rtrim(rtrim(number_format($area, 1, ',', ''), '0'), ',');

        return "до {$formatted} м² (при высоте потолка 2,5 м)";
    }

    private function addFact(array &$areas, string $key, mixed $value): void
    {
        if (! $this->isVolumeKey($key) || ! is_scalar($value)) {
            return;
        }

        $area = $this->convert((string) $value);
        if ($area !== null) {
            $areas[] = $area;
        }
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['ё', '³', '—', '–'], ['е', '3', '-', '-'], $value);

        return preg_replace('/\s+/u', ' ', $value) ?? '';
    }
}

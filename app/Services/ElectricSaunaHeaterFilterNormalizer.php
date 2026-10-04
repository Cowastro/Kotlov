<?php

namespace App\Services;

use Illuminate\Support\Str;

class ElectricSaunaHeaterFilterNormalizer
{
    public function extract(array $specs = [], array $attributes = []): array
    {
        $facts = array_merge($this->flattenSpecs($specs), $attributes);

        return [
            'power' => $this->numberFromFacts($facts, fn (string $key) => str_contains($key, 'мощност'), 100),
            'volume' => $this->numberFromFacts(
                $facts,
                fn (string $key) => preg_match('/объ[её]м.*(?:парн|парил)|(?:парн|парил).*объ[её]м/u', $key) === 1,
                100,
            ),
            'stones' => $this->numberFromFacts(
                $facts,
                fn (string $key) => preg_match('/(?:масс|вес|заклад).*камн|камн.*(?:масс|вес|заклад)/u', $key) === 1,
                500,
            ),
        ];
    }

    public function powerRange(?float $power): ?string
    {
        if ($power === null || $power <= 0 || $power > 100) {
            return null;
        }

        return match (true) {
            $power <= 5 => 'до 5',
            $power <= 8 => '5 — 8',
            $power <= 11 => '8 — 11',
            $power <= 15 => '11 — 15',
            default => '15 и более',
        };
    }

    public function volumeRange(?float $volume): ?string
    {
        if ($volume === null || $volume <= 0 || $volume > 100) {
            return null;
        }

        return match (true) {
            $volume <= 5 => 'до 5',
            $volume <= 10 => '5 — 10',
            $volume <= 15 => '10 — 15',
            $volume <= 20 => '15 — 20',
            $volume <= 30 => '20 — 30',
            default => '30 и более',
        };
    }

    public function stonesRange(?float $weight): ?string
    {
        if ($weight === null || $weight <= 0 || $weight > 500) {
            return null;
        }

        return match (true) {
            $weight <= 15 => 'до 15',
            $weight <= 25 => '15 — 25',
            $weight <= 35 => '25 — 35',
            default => '35 и более',
        };
    }

    private function numberFromFacts(array $facts, callable $matchesKey, float $maximum): ?float
    {
        foreach ($facts as $key => $value) {
            if (! $matchesKey($this->normalize((string) $key))) {
                continue;
            }

            $number = $this->maximumNumber((string) $value, $maximum);
            if ($number !== null) {
                return $number;
            }
        }

        return null;
    }

    private function maximumNumber(string $value, float $maximum): ?float
    {
        preg_match_all('/(?<!\d)(\d{1,3}(?:[.,]\d+)?)(?!\d)/u', html_entity_decode($value), $matches);

        $numbers = collect($matches[1] ?? [])
            ->map(fn (string $number) => (float) str_replace(',', '.', $number))
            ->filter(fn (float $number) => $number > 0 && $number <= $maximum);

        return $numbers->isEmpty() ? null : (float) $numbers->max();
    }

    private function flattenSpecs(array $specs): array
    {
        $facts = [];

        foreach ($specs as $key => $value) {
            if (is_array($value) && isset($value['key'], $value['value'])) {
                $facts[(string) $value['key']] = (string) $value['value'];
            } elseif (is_string($key) && is_scalar($value)) {
                $facts[$key] = (string) $value;
            }
        }

        return $facts;
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', Str::lower(str_replace('ё', 'е', $value))) ?? '');
    }
}

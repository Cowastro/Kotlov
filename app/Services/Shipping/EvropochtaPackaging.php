<?php

namespace App\Services\Shipping;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class EvropochtaPackaging
{
    public function suggest(Product $product): ?array
    {
        $measurements = $this->measurements($product);
        if ($measurements === null) {
            return null;
        }

        return $this->fit($measurements);
    }

    public function measurements(Product $product): ?array
    {
        $specs = [];
        foreach ($product->specs ?? [] as $key => $spec) {
            if (is_array($spec)) {
                $key = $spec['key'] ?? '';
                $spec = trim(($spec['value'] ?? '').' '.($spec['unit'] ?? ''));
            }

            if (is_scalar($spec)) {
                $specs[$this->normalize((string) $key)] = (string) $spec;
            }
        }

        foreach ($product->attributeValues as $value) {
            if ($value->hasDisplayValue()) {
                $specs[$this->normalize($value->attribute->name)] ??= $value->display_value;
            }
        }

        $groups = [];
        $weights = [];
        foreach ($specs as $key => $value) {
            $packed = str_contains($key, 'упаковк') || str_contains($key, 'брутто');
            $group = $packed ? 'factory' : 'product';

            if (preg_match('/^(?:вес|масса)(?:\b|$)/u', $key)) {
                $weights[$group] ??= $this->weight($value, $key);
            }

            if (preg_match('/^(?:габариты|габаритные размеры|размеры)(?:\b|$)/u', $key)) {
                $groups[$group]['combined'] ??= $this->dimensions($value, $key);
            }

            if (preg_match('/^(длина|ширина|высота|глубина)(?:\b|$)/u', $key, $match)) {
                $groups[$group][$match[1]] ??= $this->dimension($value, $key);
            }
        }

        foreach (['factory', 'product'] as $source) {
            $values = $groups[$source] ?? [];
            $axes = $values['combined'] ?? [
                $values['длина'] ?? $values['глубина'] ?? null,
                $values['ширина'] ?? null,
                $values['высота'] ?? null,
            ];
            $weight = $weights['factory'] ?? $weights['product'] ?? $product->weight;
            if (in_array(null, $axes, true) || ! is_numeric($weight) || (float) $weight <= 0) {
                continue;
            }

            rsort($axes);

            return ['item_dimensions' => $axes, 'item_weight' => (float) $weight, 'source' => $source];
        }

        return null;
    }

    public function approve(Product $product): bool
    {
        return DB::transaction(function () use ($product): bool {
            $record = Product::query()->active()->notArchived()->lockForUpdate()->find($product->getKey());
            if (! $record || array_filter($record->shipping_parcel ?? []) !== []) {
                return false;
            }

            $suggestion = $this->suggest($record);

            return $suggestion !== null && $record->update(['shipping_parcel' => $suggestion]);
        });
    }

    private function fit(array $measurements): ?array
    {
        [$largest, $mid, $smallest] = $measurements['item_dimensions'];
        foreach (config('evropochta_packaging', []) as $box => $profile) {
            [$long, $middle, $short] = $profile['fit'];
            if ($largest > $long || $mid > $middle || $smallest > $short) {
                continue;
            }

            $estimated = $measurements['item_weight'] + $profile['extra_kg'];
            foreach ([1, 2, 5] as $cap) {
                if (round($estimated, 4) <= $cap) {
                    [$length, $width, $height] = $profile['outer'];

                    return [
                        'weight' => $cap, 'length' => $length, 'width' => $width, 'height' => $height,
                        'box' => $box, 'weight_mode' => 'band',
                        ...$measurements,
                    ];
                }
            }
        }

        return null;
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value)));
    }

    private function dimensions(string $value, string $key): ?array
    {
        if (! preg_match('/^\s*(\d+(?:[.,]\d+)?)\s*[×хx*]\s*(\d+(?:[.,]\d+)?)\s*[×хx*]\s*(\d+(?:[.,]\d+)?)\s*(мм|см)?\.?\s*$/ui', $value, $parts)) {
            return null;
        }

        $unit = mb_strtolower($parts[4] ?? '') ?: $this->unit($key, 'мм|см');
        if ($unit === null) {
            return null;
        }

        $axes = array_map(fn (string $size): float => $this->number($size) / ($unit === 'мм' ? 10 : 1), array_slice($parts, 1, 3));

        return min($axes) > 0 ? $axes : null;
    }

    private function dimension(string $value, string $key): ?float
    {
        return $this->measurement($value, $key, ['мм' => 0.1, 'см' => 1]);
    }

    private function weight(string $value, string $key): ?float
    {
        return $this->measurement($value, $key, ['кг' => 1, 'г' => 0.001]);
    }

    private function measurement(string $value, string $key, array $factors): ?float
    {
        $units = implode('|', array_keys($factors));
        if (! preg_match('/^\s*(\d+(?:[.,]\d+)?)\s*('.$units.')?\.?\s*$/ui', $value, $parts)) {
            return null;
        }

        $unit = mb_strtolower($parts[2] ?? '') ?: $this->unit($key, $units);
        $number = $this->number($parts[1]);

        return $unit !== null && $number > 0 ? $number * $factors[$unit] : null;
    }

    private function unit(string $key, string $units): ?string
    {
        return preg_match('/\b('.$units.')\b/u', $key, $match) ? $match[1] : null;
    }

    private function number(string $value): float
    {
        return (float) str_replace(',', '.', $value);
    }
}

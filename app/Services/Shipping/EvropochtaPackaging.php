<?php

namespace App\Services\Shipping;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class EvropochtaPackaging
{
    // Geometry uses whole millimetres to keep clearances conservative.
    private const SEPARATION_MM = 50;

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

    public function approve(Product $product, ?array $expected = null): bool
    {
        return DB::transaction(function () use ($product, $expected): bool {
            $record = Product::query()->active()->notArchived()->lockForUpdate()->find($product->getKey());
            if (! $record || array_filter($record->shipping_parcel ?? []) !== []) {
                return false;
            }

            $suggestion = $this->suggest($record);

            return $suggestion !== null
                && ($expected === null || $suggestion === $expected)
                && $record->update(['shipping_parcel' => $suggestion]);
        });
    }

    /**
     * @param list<array{quantity: int, parcel: array}> $lines Approved package data, one line per product.
     */
    public function combine(array $lines, float $maxWeight): ?array
    {
        $weight = 0;
        $volume = 0;
        $parts = [];
        foreach ($lines as $line) {
            [$dimensions, $unitWeight] = $this->unitForCombinedBox($line['parcel']);
            $size = array_map(static fn ($side): int => (int) ceil(round($side * 10, 8)), $dimensions);
            $quantity = $line['quantity'];
            $weight += $unitWeight * $quantity;
            $volume += array_product($size) * $quantity;
            $parts[] = ['size' => $size, 'quantity' => $quantity];
        }

        usort($parts, static fn (array $a, array $b): int => array_product($b['size']) <=> array_product($a['size']));
        $boxes = config('evropochta_packaging', []);
        uasort($boxes, static fn (array $a, array $b): int => array_product($a['outer']) <=> array_product($b['outer']));

        foreach ($boxes as $box => $profile) {
            $packedWeight = ceil(round(($weight + $profile['extra_kg']) * 100, 8)) / 100;
            $space = array_map(static fn ($side): int => (int) floor($side * 10), $profile['fit']);
            if ($packedWeight > $maxWeight || $volume > array_product($space) || ! $this->fitsTogether($parts, $space)) {
                continue;
            }

            [$length, $width, $height] = $profile['outer'];

            return ['box' => $box, 'weight' => (float) $packedWeight, 'length' => $length, 'width' => $width, 'height' => $height];
        }

        return null;
    }

    private function unitForCombinedBox(array $parcel): array
    {
        $outer = [$parcel['length'], $parcel['width'], $parcel['height']];
        rsort($outer);
        $dimensions = $parcel['item_dimensions'] ?? [];
        $weight = $parcel['item_weight'] ?? null;

        if (($parcel['weight_mode'] ?? null) === 'band'
            && is_array($dimensions) && count($dimensions) === 3
            && collect($dimensions)->every(static fn ($side): bool => is_numeric($side) && $side >= 0.01 && $side <= 250)
            && is_numeric($weight) && $weight >= 0.01 && $weight <= $parcel['weight']) {
            rsort($dimensions);
            if ($dimensions[0] <= $outer[0] && $dimensions[1] <= $outer[1] && $dimensions[2] <= $outer[2]) {
                return [$dimensions, (float) $weight];
            }
        }

        // With only shipping measurements available, allow room for the entire individual box.
        return [$outer, (float) $parcel['weight']];
    }

    private function fitsTogether(array $parts, array $space): bool
    {
        // Try different cut directions so long or flat items can share the same carton.
        foreach ($this->orientations($space) as $orientation) {
            $free = [$orientation];
            foreach ($parts as $part) {
                $sizes = $this->orientations($part['size']);
                for ($placed = 0; $placed < $part['quantity']; $placed++) {
                    if (! $this->placePiece($free, $sizes)) {
                        continue 3;
                    }
                }
            }

            return true;
        }

        return false;
    }

    private function placePiece(array &$free, array $sizes): bool
    {
        foreach ($free as $index => [$length, $width, $height]) {
            foreach ($sizes as [$x, $y, $z]) {
                if ($x > $length || $y > $width || $z > $height) {
                    continue;
                }

                // The three remaining spaces do not overlap.
                unset($free[$index]);
                foreach ([
                    [$length - $x - self::SEPARATION_MM, $width, $height],
                    [$x, $width - $y - self::SEPARATION_MM, $height],
                    [$x, $y, $height - $z - self::SEPARATION_MM],
                ] as $remaining) {
                    if (min($remaining) > 0) {
                        $free[] = $remaining;
                    }
                }

                return true;
            }
        }

        return false;
    }

    private function orientations(array $sides): array
    {
        sort($sides);
        [$x, $y, $z] = $sides;

        return array_unique([[$x, $y, $z], [$x, $z, $y], [$y, $x, $z], [$y, $z, $x], [$z, $x, $y], [$z, $y, $x]], SORT_REGULAR);
    }

    private function fit(array $measurements): ?array
    {
        [$largest, $mid, $smallest] = $measurements['item_dimensions'];
        foreach (config('evropochta_packaging', []) as $box => $profile) {
            [$long, $middle, $short] = $profile['fit'];
            if (($profile['combined_only'] ?? false) || $largest > $long || $mid > $middle || $smallest > $short) {
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

<?php

namespace App\Services\Suppliers;

use App\Models\SupplierProduct;

class SupplierOrderRules
{
    public static function fromOffer(SupplierProduct $offer): array
    {
        if ($offer->supplier?->code !== 'td_komplekt') {
            return [];
        }
        $rules = [];
        foreach (['minimum' => 'prop_quantity_min', 'multiple' => 'prop_multiplicity', 'box' => 'prop_multiplicity_box'] as $key => $field) {
            $raw = str_replace([' ', ','], ['', '.'], trim((string) ($offer->raw[$field] ?? '')));
            if (is_numeric($raw) && is_finite((float) $raw) && (float) $raw > 0) {
                $rules[$key] = (float) $raw;
            }
        }
        $unit = trim((string) ($offer->raw['prop_unit'] ?? ''));
        if ($rules !== [] && $unit !== '') {
            $rules['unit'] = $unit;
        }

        return $rules;
    }

    public static function label(array $rules): string
    {
        $parts = [];
        foreach (['minimum' => 'Мин.', 'multiple' => 'Кратность', 'box' => 'В коробке'] as $key => $title) {
            if (isset($rules[$key])) {
                $parts[] = $title.' '.rtrim(rtrim(number_format((float) $rules[$key], 3, ',', ' '), '0'), ',').(isset($rules['unit']) ? ' '.$rules['unit'] : '');
            }
        }

        return implode(' · ', $parts);
    }

    /** Draft demand may be smaller; show the constraint without rewriting it. */
    public static function warning(array $rules, float $quantity): string
    {
        if ($quantity <= 0) {
            return '';
        }
        if (isset($rules['minimum']) && $quantity + 0.00000001 < (float) $rules['minimum']) {
            return 'Количество меньше минимума поставщика: '.rtrim(self::label(array_intersect_key($rules, array_flip(['minimum', 'unit']))), '.').'.';
        }
        $multiple = (float) ($rules['multiple'] ?? 0);
        if ($multiple > 0 && abs($quantity / $multiple - round($quantity / $multiple)) > 0.00000001) {
            return 'Проверьте кратность заказа: '.rtrim(self::label(array_intersect_key($rules, array_flip(['multiple', 'unit']))), '.').'.';
        }

        return '';
    }

    /** A proposal for a new integer quantity, never a change to saved demand. */
    public static function initialQuantity(array $rules): int
    {
        $minimum = min(999, max(1, (int) ceil((float) ($rules['minimum'] ?? 1))));
        for ($quantity = $minimum; $quantity <= 999; $quantity++) {
            if (self::warning($rules, $quantity) === '') {
                return $quantity;
            }
        }

        return $minimum;
    }
}

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
}

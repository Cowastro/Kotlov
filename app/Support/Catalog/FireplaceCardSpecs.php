<?php

namespace App\Support\Catalog;

use App\Models\Product;

final class FireplaceCardSpecs
{
    private const FIELDS = [
        'Мощность' => ['номинальная мощность', 'мощность номинальная', 'номинальная тепловая мощность', 'тепловая мощность', 'мощность'],
        'Площадь обогрева' => ['площадь отапливаемого помещения', 'площадь обогрева', 'отапливаемая площадь', 'обогреваемая площадь', 'площадь отопления'],
        'Материал' => ['материал корпуса', 'материал'],
    ];

    /** @return array<string, string> */
    public static function forProduct(Product $product): array
    {
        $values = [];
        $options = [];

        foreach ($product->attributeValues as $entry) {
            if (! $entry->hasDisplayValue()) {
                continue;
            }

            $name = self::normalizeName($entry->attribute->name);
            if ($entry->attribute->type === 'select') {
                $options[$name] ??= [(string) $entry->option->name, ''];
            } elseif ($entry->attribute->type !== 'check') {
                $values[$name] ??= [(string) $entry->value, (string) $entry->attribute->suffix];
            }
        }

        $specs = [];
        foreach ((array) $product->specs as $key => $spec) {
            $name = is_array($spec) ? ($spec['key'] ?? $spec['name'] ?? $spec['title'] ?? $key) : $key;
            $value = is_array($spec) ? ($spec['value'] ?? $spec['val'] ?? null) : $spec;
            $unit = is_array($spec) ? ($spec['unit'] ?? '') : '';
            if (is_string($name) && (is_string($value) || is_numeric($value)) && is_string($unit)) {
                $specs[self::normalizeName($name)] ??= [(string) $value, $unit];
            }
        }

        $result = [];
        foreach (self::FIELDS as $label => $names) {
            // Prefer recorded measurements over filter options such as “50–100 м²”.
            foreach ([$values, $specs, $options] as $source) {
                foreach ($names as $name) {
                    if (isset($source[$name]) && ($display = self::format($label, ...$source[$name])) !== null) {
                        $result[$label] = $display;
                        break 2;
                    }
                }
            }
        }

        return $result;
    }

    private static function normalizeName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', mb_strtolower($name)));

        return trim(preg_replace('/[\s,(]+(?:квт|kw|[мm][²2]|кв\.\s*м)\.?\)?$/u', '', $name));
    }

    private static function format(string $label, string $value, string $unit): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value));
        if ($label === 'Материал') {
            return preg_match('/\p{L}/u', $value) && ! in_array(mb_strtolower($value), ['нет', 'не указан', 'не указано', 'нет данных', 'н/д', 'да'], true)
                ? $value
                : null;
        }

        [$unitPattern, $suffix] = $label === 'Мощность'
            ? ['квт|kw', 'кВт']
            : ['[мm][²2]|кв\.\s*м\.?', 'м²'];

        if (trim($unit) !== '' && ! preg_match('/^(?:'.$unitPattern.')$/iu', trim($unit))) {
            return null;
        }

        $number = trim(preg_replace('/(?<=\d)\s*(?:'.$unitPattern.')(?=\s|[-–—]|$)/iu', '', mb_strtolower($value)));
        $decimal = '\d+(?:[,.]\d+)?';
        if (! preg_match('/^(?:(?:от|до|более|менее)\s+)?'.$decimal.'(?:(?:\s*[-–—]\s*|\s+до\s+)'.$decimal.')?$/u', $number)
            || ! preg_match('/[1-9]/', $number)) {
            return null;
        }

        $number = preg_replace('/^от ('.$decimal.') до ('.$decimal.')$/u', '$1–$2', $number);
        $number = preg_replace('/\s*[-–—]\s*/u', '–', str_replace('.', ',', $number));

        return $number.' '.$suffix;
    }
}

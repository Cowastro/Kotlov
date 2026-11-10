<?php

namespace App\Support\Catalog;

use App\Models\Product;

final class FireplaceSpecifications
{
    private const CARD_FIELDS = [
        'Мощность' => ['номинальная мощность', 'мощность номинальная', 'номинальная тепловая мощность', 'тепловая мощность', 'мощность'],
        'Площадь обогрева' => ['площадь отапливаемого помещения', 'площадь обогрева', 'отапливаемая площадь', 'обогреваемая площадь', 'площадь отопления'],
        'Материал' => ['материал корпуса', 'материал'],
    ];

    private const DETAIL_ALIASES = [
        'Объём отапливаемого помещения' => ['объем отапливаемого помещения', 'объем отапливания', 'отапливаемый объем'],
        'Диаметр дымохода' => ['диаметр дымохода', 'диаметр дымоходного патрубка'],
        'Подключение дымохода' => ['подключение дымохода', 'вариант подключения дымохода'],
        'Вид топлива' => ['вид топлива', 'тип топлива'],
        'Варочная панель' => ['варочная панель', 'варочная плита'],
        'Вторичный дожиг' => ['вторичный дожиг', 'система вторичного дожига'],
        'Система «чистое стекло»' => ['система чистое стекло'],
        'Подача воздуха извне' => ['подача воздуха извне', 'подача воздуха из вне'],
    ];

    private const UNITS = '[мm][²2]|[мm][³3]|кв\.\s*м\.?|квт|kw|мм|см|кг|мес\.?|ч\.?|%';

    /** @return array<string, string> */
    public static function card(Product $product): array
    {
        return self::summary(self::sources($product, $product->attributeValues));
    }

    /** @return array{label: string, value: string, is_area: bool}|null */
    public static function heatingFact(Product $product): ?array
    {
        [$values, $specs] = self::sources($product, $product->attributeValues);
        $areaLabel = 'Площадь обогрева';
        $areaNames = [...self::CARD_FIELDS[$areaLabel], 'максимальная площадь обогрева', 'максимальная отапливаемая площадь'];
        $area = self::summary([$values, $specs], [$areaLabel => $areaNames])[$areaLabel] ?? null;
        if ($area !== null) {
            return ['label' => $areaLabel, 'value' => $area, 'is_area' => true];
        }

        foreach ($areaNames as $name) {
            // The existing volume importer records the ceiling height in the stored estimate.
            $stored = $specs[$name][1] ?? '';
            if (preg_match('/^(.+?)\s*\(при высоте потолка 2[,.]5 м\)$/iu', trim($stored), $matches)) {
                $estimate = self::format($areaLabel, $matches[1], '');
                if ($estimate !== null) {
                    return ['label' => 'Оценка при потолке 2,5 м', 'value' => $estimate, 'is_area' => true];
                }
            }
        }

        $volumeLabel = 'Объём обогрева';
        $volumeNames = [
            ...self::DETAIL_ALIASES['Объём отапливаемого помещения'],
            'объем обогрева', 'максимальный объем обогрева', 'максимальный объем отапливаемого помещения', 'объем помещения', 'объем отопления',
        ];
        $volume = self::summary([$values, $specs], [$volumeLabel => $volumeNames])[$volumeLabel] ?? null;

        return $volume === null ? null : ['label' => $volumeLabel, 'value' => $volume, 'is_area' => false];
    }

    /** @return array<string, string> */
    public static function details(Product $product, iterable $attributes): array
    {
        $sources = self::sources($product, $attributes);
        $rows = self::summary($sources);
        $labels = [];
        foreach (self::CARD_FIELDS + self::DETAIL_ALIASES as $label => $names) {
            foreach ($names as $name) {
                $labels[$name] = $label;
            }
        }

        $seen = [];
        foreach ($sources as $source) {
            foreach ($source as $key => [$name, $value, $unit]) {
                if (in_array($key, ['главная', 'диапазон мощности для фильтра'], true)) {
                    continue;
                }

                $label = $labels[$key] ?? $name;
                if (isset($rows[$label]) || isset($seen[$key])) {
                    continue;
                }

                if (isset(self::CARD_FIELDS[$label]) && in_array(mb_strtolower(trim($value)), ['нет', '—', '-', '0', 'нет данных', 'не указано'], true)) {
                    continue;
                }

                $rows[$label] = self::withUnit($value, $unit);
                $seen[$key] = true;
            }
        }

        return $rows;
    }

    private static function sources(Product $product, iterable $attributes): array
    {
        $values = [];
        $options = [];

        foreach ($attributes as $entry) {
            if (! $entry->hasDisplayValue()) {
                continue;
            }

            [$name, $unit] = self::splitNameAndUnit($entry->attribute->name, (string) $entry->attribute->suffix);
            $key = self::normalizeName($name);
            if ($entry->attribute->type === 'select') {
                $options[$key] ??= [$name, (string) $entry->option->name, $unit];
            } elseif ($entry->attribute->type === 'check') {
                $values[$key] ??= [$name, $entry->is_checked ? 'Да' : 'Нет', ''];
            } else {
                $values[$key] ??= [$name, (string) $entry->value, $unit];
            }
        }

        $specs = [];
        foreach ((array) $product->specs as $key => $spec) {
            $name = is_array($spec) ? ($spec['key'] ?? $spec['name'] ?? $spec['title'] ?? $key) : $key;
            $value = is_array($spec) ? ($spec['value'] ?? $spec['val'] ?? null) : $spec;
            $unit = is_array($spec) ? ($spec['unit'] ?? '') : '';
            if (is_string($name) && trim($name) !== '' && is_scalar($value) && trim((string) $value) !== '' && is_scalar($unit)) {
                [$name, $unit] = self::splitNameAndUnit($name, (string) $unit);
                $specs[self::normalizeName($name)] ??= [$name, (string) $value, $unit];
            }
        }

        return [$values, $specs, $options];
    }

    private static function summary(array $sources, array $fields = self::CARD_FIELDS): array
    {
        $result = [];
        foreach ($fields as $label => $names) {
            // Prefer recorded measurements over filter options such as “50–100 м²”.
            foreach ($sources as $source) {
                foreach ($names as $name) {
                    if (! isset($source[$name])) {
                        continue;
                    }

                    [, $value, $unit] = $source[$name];
                    if (($display = self::format($label, $value, $unit)) !== null) {
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
        return trim(preg_replace('/[\s«»"]+/u', ' ', str_replace('ё', 'е', mb_strtolower($name))));
    }

    private static function splitNameAndUnit(string $name, string $unit): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        if (preg_match('/[\s,(]+('.self::UNITS.')\)?$/iu', $name, $match, PREG_OFFSET_CAPTURE)) {
            $name = substr($name, 0, $match[0][1]);
            $unit = trim($unit) ?: $match[1][0];
        }

        return [$name, self::normalizeUnit(trim($unit))];
    }

    private static function withUnit(string $value, string $unit): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value));
        $value = preg_replace_callback('/(?<=\d)\s*('.self::UNITS.')(?=\s|[-–—]|$)/iu',
            fn ($match) => ' '.self::normalizeUnit($match[1]), $value);

        if ($unit === '' || ! preg_match('/\d/u', $value) || mb_stripos($value, $unit) !== false) {
            return $value;
        }

        return $value.' '.$unit;
    }

    private static function normalizeUnit(string $unit): string
    {
        return match (mb_strtolower($unit)) {
            'м2', 'm2', 'm²', 'кв.м', 'кв.м.', 'кв. м', 'кв. м.' => 'м²',
            'м3', 'm3', 'm³' => 'м³',
            'квт', 'kw' => 'кВт',
            default => $unit,
        };
    }

    private static function format(string $label, string $value, string $unit): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value));
        if ($label === 'Материал') {
            return preg_match('/\p{L}/u', $value) && ! in_array(mb_strtolower($value), ['нет', 'не указан', 'не указано', 'нет данных', 'н/д', 'да'], true)
                ? $value
                : null;
        }

        [$unitPattern, $suffix] = match ($label) {
            'Мощность' => ['квт|kw', 'кВт'],
            'Объём обогрева' => ['[мm][³3]|куб\.?\s*м\.?', 'м³'],
            default => ['[мm][²2]|кв\.\s*м\.?', 'м²'],
        };

        if (trim($unit) !== '' && ! preg_match('/^(?:'.$unitPattern.')$/iu', trim($unit))) {
            return null;
        }

        $number = trim(preg_replace('/(?<=\d)\s*(?:'.$unitPattern.')(?=\s|[-–—]|$)/iu', '', mb_strtolower($value)));
        $decimal = '\d+(?:[,.]\d+)?';
        if (! preg_match('/^(?:(?:от|до|свыше|(?:не )?(?:более|менее))\s+)?'.$decimal.'(?:(?:\s*[-–—]\s*|\s+до\s+)'.$decimal.')?$/u', $number)
            || ! preg_match('/[1-9]/', $number)) {
            return null;
        }

        $number = preg_replace('/^от ('.$decimal.') до ('.$decimal.')$/u', '$1–$2', $number);
        $number = preg_replace('/\s*[-–—]\s*/u', '–', str_replace('.', ',', $number));

        return $number.' '.$suffix;
    }
}

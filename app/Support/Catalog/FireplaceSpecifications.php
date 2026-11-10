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

    private const ELECTRIC_FIELDS = [
        'Диагональ экрана' => ['диагональ экрана', 'диагональ очага', 'диагональ'],
        'Мощность обогрева' => ['мощность обогрева', 'мощность нагрева', 'тепловая мощность', 'мощность нагревателя'],
        'Площадь обогрева' => self::CARD_FIELDS['Площадь обогрева'],
        'Обогрев' => ['обогрев', 'режим обогрева', 'функция обогрева'],
        'Эффект пламени' => ['имитация огня', 'технология имитации пламени'],
    ];

    private const OPENING_AXES = [
        'width' => 'Ширина ниши',
        'height' => 'Высота ниши',
        'depth' => 'Глубина ниши',
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

    private const UNITS = '[мm][²2]|[мm][³3]|кв\.\s*м\.?|квт|kw|вт|w|мм|см|кг|мес\.?|ч\.?|%';

    /** @return array<string, string> */
    public static function card(Product $product): array
    {
        return self::summary(self::sources($product, $product->attributeValues));
    }

    /** @return array{label: string, value: string, is_area: bool}|null */
    public static function heatingFact(Product $product): ?array
    {
        return self::resolveHeatingFact(self::sources($product, $product->attributeValues));
    }

    /** @return array<string, string> */
    public static function catalogCard(Product $product): array
    {
        $sources = self::sources($product, $product->attributeValues);
        $otherSpecs = self::summary($sources, [
            'Мощность' => self::CARD_FIELDS['Мощность'],
            'Материал' => self::CARD_FIELDS['Материал'],
        ]);
        $heating = self::resolveHeatingFact($sources);

        return array_intersect_key($otherSpecs, ['Мощность' => true])
            + ($heating === null ? [] : [$heating['label'] => $heating['value']])
            + array_intersect_key($otherSpecs, ['Материал' => true]);
    }

    /** @return array{card: array<string, string>, heating: ?bool, steam: bool, openingWidth: ?int} */
    public static function electricCatalogFacts(Product $product): array
    {
        $sources = self::sources($product, $product->attributeValues);
        if (self::isBioFireplace($product, $sources)) {
            return ['card' => ['Тип камина' => 'Биокамин'], 'heating' => null, 'steam' => false, 'openingWidth' => null];
        }

        $rows = self::electricRows($product, $sources);
        $heating = match ($rows['Обогрев'] ?? null) {
            'Да' => true,
            'Нет' => false,
            default => null,
        };
        $steam = ($rows['Эффект пламени'] ?? null) === 'Водяной пар';
        $card = array_intersect_key($rows, array_flip(['Диагональ экрана', 'Мощность обогрева', 'Площадь обогрева', 'Эффект пламени']));
        if ($heating === false || ($heating === true && ! isset($card['Мощность обогрева']))) {
            $card['Обогрев'] = $heating ? 'С обогревом' : 'Без обогрева';
        }

        $opening = self::opening($product, $sources);
        $openingWidth = $opening['width'] ?? null;
        if (count($opening) === 3) {
            $card['Размеры ниши (Ш×В×Г)'] = 'Ниша '.implode(' × ', $opening).' мм';
        } elseif ($openingWidth !== null) {
            $card['Ширина ниши'] = 'Ниша '.$openingWidth.' мм по ширине';
        }

        return compact('card', 'heating', 'steam', 'openingWidth');
    }

    public static function electricType(Product $product): ?string
    {
        $sources = self::sources($product, $product->attributeValues);
        if (self::isBioFireplace($product, $sources)) {
            return null;
        }

        $types = collect($sources)->flatMap(fn (array $source) => array_values(array_intersect_key($source, array_flip([
            'тип', 'тип камина', 'тип электрокамина', 'тип товара', 'тип установки', 'способ установки',
        ]))))->map(fn (array $row) => self::normalizeName($row[1]));

        if (preg_match('/каминокомплект|\bс порталом\b/iu', $product->name)
            || $types->contains(fn (string $value) => str_contains($value, 'каминокомплект'))) {
            return 'kit';
        }

        if ($types->contains(fn (string $value) => preg_match('/(?:электроочаг|^очаг$|встраива)/u', $value))
            || preg_match('/(?:^|\s)(?:электроочаг|очаг)(?:\s|$)/iu', $product->name)
            || config('electric-fireplaces.openings.'.$product->slug) !== null) {
            return 'insert';
        }

        // These model families are sold as separate fireboxes in the supplier's catalog.
        return preg_match(
            '/\b(?:Dimplex\s+(?:Symphony|XHD\d+[A-Z]*|Cassette|Revillusion)|(?:Royal\s*Flame|Real\s*Flame)\s+(?:Vision|Dioramic|Moonblaze|Majestic|Fobos|Galaxy|Emerald|Jupiter|Crystal|Aspen|FireSpace|5D\s+V-ART))\b/iu',
            $product->name
        ) === 1 ? 'insert' : null;
    }

    /** @return array<string, string>|null */
    public static function electricDetails(Product $product, iterable $attributes): ?array
    {
        $sources = self::sources($product, $attributes);
        if (self::isBioFireplace($product, $sources)) {
            return null;
        }

        $openingRows = self::openingRows(self::opening($product, $sources));
        $rows = self::electricRows($product, $sources) + $openingRows;
        $coveredFields = array_intersect_key(self::ELECTRIC_FIELDS, $rows);
        $coveredFields['Площадь обогрева'] = self::ELECTRIC_FIELDS['Площадь обогрева'];
        $aliases = array_merge(...array_values($coveredFields));
        $seen = [];
        foreach ($sources as $source) {
            foreach ($source as $key => [$name, $value, $unit]) {
                $key = self::electricKey($key);
                if (isset($seen[$key]) || ($key !== 'технология имитации пламени' && in_array($key, $aliases, true))
                    || (isset($openingRows['Ширина ниши'], $openingRows['Высота ниши'], $openingRows['Глубина ниши'])
                        && self::isOpeningField($key))) {
                    continue;
                }

                $seen[$key] = true;
                $rows[html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8')] ??= self::withUnit(
                    html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $unit
                );
            }
        }

        return $rows;
    }

    /** @return array<string, string> */
    public static function electricOpeningRows(Product $product): array
    {
        $sources = self::sources($product, $product->attributeValues);

        return self::isBioFireplace($product, $sources)
            ? []
            : self::openingRows(self::opening($product, $sources));
    }

    private static function openingRows(array $opening): array
    {
        $rows = [];
        foreach ($opening as $axis => $value) {
            $rows[self::OPENING_AXES[$axis]] = $value.' мм';
        }

        return $rows;
    }

    private static function opening(Product $product, array $sources): array
    {
        $verified = config('electric-fireplaces.openings.'.$product->slug.'.mm', []);
        $candidates = [];
        foreach (array_slice($sources, 0, 2) as $source) {
            foreach ($source as $key => [, $value, $unit]) {
                if (! self::isOpeningField($key)) {
                    continue;
                }

                $dimensions = [];
                if (preg_match('/^(ширина|высота|глубина) /u', $key, $axis)) {
                    $dimensions[match ($axis[1]) {
                        'ширина' => 'width',
                        'высота' => 'height',
                        'глубина' => 'depth',
                    }] = self::openingMillimetres($value, $unit);
                } elseif (preg_match('/([швг])\s*[xх×]\s*([швг])\s*[xх×]\s*([швг])/u', $key, $axes)
                    && count(array_unique(array_slice($axes, 1))) === 3
                    && preg_match('/^\s*(\d+(?:[,.]\d+)?)\s*[xх×]\s*(\d+(?:[,.]\d+)?)\s*[xх×]\s*(\d+(?:[,.]\d+)?)\s*(мм|см|mm|cm)?\s*$/iu', $value, $numbers)) {
                    $axisKeys = ['ш' => 'width', 'в' => 'height', 'г' => 'depth'];
                    foreach ([1, 2, 3] as $index) {
                        $dimensions[$axisKeys[$axes[$index]]] = self::openingMillimetres($numbers[$index], $numbers[4] ?? $unit);
                    }
                }

                foreach ($dimensions as $axis => $mm) {
                    if ($mm !== null) {
                        $candidates[$axis][$mm] = $mm;
                    }
                }
            }
        }

        $opening = [];
        foreach (self::OPENING_AXES as $axis => $label) {
            $values = $candidates[$axis] ?? [];
            if (isset($verified[$axis])) {
                $opening[$axis] = $verified[$axis];
            } elseif (count($values) === 1) {
                $opening[$axis] = reset($values);
            }
        }

        return $opening;
    }

    private static function isOpeningField(string $key): bool
    {
        return preg_match('/^(?:(?:ширина|высота|глубина) (?:ниши|встраивания|монтажного про[её]ма)|размеры? (?:ниши|встраивания)|установочные размеры|установочный размер|монтажные размеры|монтажный размер|габариты встраивания)(?:$|[\s,(])/u', $key) === 1;
    }

    private static function openingMillimetres(string $value, string $unit): ?int
    {
        if (! preg_match('/^\s*(\d+(?:[,.]\d+)?)\s*(мм|см|mm|cm)?\s*$/iu', $value, $number)) {
            return null;
        }

        $factor = match (mb_strtolower(trim($number[2] ?? $unit))) {
            'мм', 'mm' => 1,
            'см', 'cm' => 10,
            default => null,
        };
        $mm = (float) str_replace(',', '.', $number[1]) * ($factor ?? 0);

        return $mm >= 1 && $mm <= 10000 && $mm === floor($mm) ? (int) $mm : null;
    }

    private static function isBioFireplace(Product $product, array $sources): bool
    {
        if (preg_match('/биокамин/iu', $product->name)
            || config('electric-fireplaces.models.'.$product->slug.'.specs.Тип камина') === 'Биокамин') {
            return true;
        }

        foreach ($sources as $source) {
            $fuel = $source['тип топлива'][1] ?? $source['вид топлива'][1] ?? '';
            if (mb_strtolower(trim($fuel)) === 'биотопливо') {
                return true;
            }
        }

        return false;
    }

    private static function electricRows(Product $product, array $sources): array
    {
        $verified = config('electric-fireplaces.models.'.$product->slug.'.specs', []);
        $values = [];
        foreach ($sources as $sourceIndex => $source) {
            foreach ($source as $key => [$name, $value, $unit]) {
                $values[self::electricKey($key)][] = [$value, $unit, $sourceIndex];
            }
        }

        $rows = [];
        foreach (self::ELECTRIC_FIELDS as $label => $keys) {
            $candidates = [];
            foreach ($keys as $key) {
                foreach ($values[$key] ?? [] as [$value, $unit, $sourceIndex]) {
                    // A range option is for filtering. Exact diagonal and yes/no options are facts.
                    if ($sourceIndex === 2 && ! in_array($label, ['Диагональ экрана', 'Обогрев', 'Эффект пламени'], true)) {
                        continue;
                    }
                    $normalized = self::electricValue($label, $value, $unit);
                    if ($normalized !== null) {
                        $candidates[$normalized] = $normalized;
                    }
                }
            }
            if (count($candidates) === 1) {
                $rows[$label] = reset($candidates);
            }
        }

        if (preg_match('/парово[йие]\s+(?:электрический\s+)?очаг|очаг\s+парово[йие]/iu', $product->name)
            || collect($values['эффект opti-myst'] ?? [])->contains(fn ($row) => mb_strtolower(trim($row[0])) === 'да')) {
            $rows['Эффект пламени'] = 'Водяной пар';
        }

        $rows = $verified + $rows;
        if (($rows['Обогрев'] ?? null) === 'Нет') {
            unset($rows['Мощность обогрева'], $rows['Площадь обогрева']);
        } elseif (isset($rows['Мощность обогрева']) || isset($rows['Площадь обогрева'])
            || collect($values['режим работы'] ?? [])->contains(fn ($row) => preg_match('/(?:^|[,;])\s*с обогревом(?:\s|[,;]|$)/iu', $row[0]))) {
            $rows['Обогрев'] = 'Да';
        }

        return array_replace(array_intersect_key(self::ELECTRIC_FIELDS, $rows), $rows);
    }

    private static function electricKey(string $key): string
    {
        $key = html_entity_decode($key, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/[\s,(]+(?:[мm][²2]|квт|kw|вт|w)\)?$/iu', '', $key), " ,");
    }

    private static function electricValue(string $label, string $value, string $unit): ?string
    {
        $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $unit = trim($unit);

        return match ($label) {
            'Диагональ экрана' => preg_match('/^(\d{1,3}(?:[,.]\d+)?)\s*(?:["″]|дюйм(?:а|ов)?\.?)?$/iu', $value, $match)
                ? str_replace('.', ',', $match[1]).'″' : null,
            'Мощность обогрева' => self::electricPower($value, $unit),
            'Площадь обогрева' => self::format($label, $value, $unit),
            'Обогрев' => match (mb_strtolower($value)) {
                'да', 'есть', 'с обогревом' => 'Да',
                'нет', 'без обогрева' => 'Нет',
                default => null,
            },
            'Эффект пламени' => match (true) {
                (bool) preg_match('/водян(?:ой|ого) пар|парово[йе]|opti[- ]?myst/iu', $value) => 'Водяной пар',
                (bool) preg_match('/^([235]d)(?:[ -]|$)/iu', $value, $match) => strtoupper($match[1]).'-пламя',
                default => null,
            },
        };
    }

    private static function electricPower(string $value, string $unit): ?string
    {
        if (preg_match('/^(\d+)\s*(вт|w)?$/iu', $value, $match)
            && (isset($match[2]) || in_array(mb_strtolower($unit), ['вт', 'w'], true))
            && (int) $match[1] > 0) {
            return str_replace('.', ',', (string) ((int) $match[1] / 1000)).' кВт';
        }

        return self::format('Мощность', $value, $unit);
    }

    private static function resolveHeatingFact(array $sources): ?array
    {
        [$values, $specs] = $sources;
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

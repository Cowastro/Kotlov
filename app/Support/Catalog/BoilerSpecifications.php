<?php

namespace App\Support\Catalog;

use App\Models\Product;

class BoilerSpecifications
{
    /** @var array<string,array<string,mixed>> */
    public const AUDITED_RANGES = [
        'gas' => [
            'slug' => 'gazovye',
            'ranges' => [
                'area' => [
                    'attribute' => 'Обогреваемая площадь (m2)',
                    'options' => [
                        ['до 150 м²', null, 150],
                        ['150–250 м²', 150, 250],
                        ['250–350 м²', 250, 350],
                        ['свыше 350 м²', 350, null],
                    ],
                ],
                'power' => [
                    'attribute' => 'Мощность (кВт)',
                    'options' => [
                        ['до 12 кВт', null, 12],
                        ['12–24 кВт', 12, 24],
                        ['24–35 кВт', 24, 35],
                        ['35–50 кВт', 35, 50],
                        ['свыше 50 кВт', 50, null],
                    ],
                ],
            ],
        ],
        'solid' => [
            'slug' => 'tverdotoplivnye',
            'ranges' => [
                'area' => [
                    'attribute' => 'Обогреваемая площадь (m2)',
                    'options' => [
                        ['до 150 м²', null, 150],
                        ['150–300 м²', 150, 300],
                        ['300–500 м²', 300, 500],
                        ['свыше 500 м²', 500, null],
                    ],
                ],
                'power' => [
                    'attribute' => 'Мощность (кВт)',
                    'options' => [
                        ['до 20 кВт', null, 20],
                        ['20–50 кВт', 20, 50],
                        ['50–100 кВт', 50, 100],
                        ['100–200 кВт', 100, 200],
                        ['свыше 200 кВт', 200, null],
                    ],
                ],
            ],
        ],
        'electric' => [
            'slug' => 'elektricheskie',
            'ranges' => [
                'area' => [
                    'attribute' => 'Обогреваемая площадь (m2)',
                    'options' => [
                        ['до 90 м²', null, 90],
                        ['90–180 м²', 90, 180],
                        ['свыше 180 м²', 180, null],
                    ],
                ],
                'power' => [
                    'attribute' => 'Мощность (кВт)',
                    'options' => [
                        ['до 6 кВт', null, 6],
                        ['6–12 кВт', 6, 12],
                        ['12–24 кВт', 12, 24],
                        ['свыше 24 кВт', 24, null],
                    ],
                ],
            ],
        ],
    ];

    public const PELLET_POWER = [
        'up-to-25' => ['до 25 кВт', null, 25],
        '26-50' => ['свыше 25 до 50 кВт', 25, 50],
        '51-100' => ['свыше 50 до 100 кВт', 50, 100],
        'over-100' => ['свыше 100 кВт', 100, null],
    ];

    public function ranges(string $slug): array
    {
        foreach (self::AUDITED_RANGES as $catalog) {
            if ($catalog['slug'] === $slug) {
                return array_map(
                    fn (array $range) => array_combine(
                        array_map(fn ($option) => $option[2] === null ? 'over-'.$option[1] : 'to-'.$option[2], $range['options']),
                        $range['options'],
                    ),
                    $catalog['ranges'],
                );
            }
        }

        return $slug === 'kotly-na-pelletah' ? [
            'power' => self::PELLET_POWER,
            'hopper_volume' => [
                'to-100' => ['до 100 л', null, 100],
                'to-200' => ['свыше 100 до 200 л', 100, 200],
                'to-350' => ['свыше 200 до 350 л', 200, 350],
                'over-350' => ['свыше 350 л', 350, null],
            ],
            'hopper' => [
                'to-50' => ['до 50 кг', null, 50],
                'to-100' => ['свыше 50 до 100 кг', 50, 100],
                'over-100' => ['свыше 100 кг', 100, null],
            ],
            'area' => [
                'to-150' => ['до 150 м²', null, 150],
                'to-300' => ['150–300 м²', 150, 300],
                'to-500' => ['300–500 м²', 300, 500],
                'over-500' => ['свыше 500 м²', 500, null],
            ],
        ] : [];
    }

    public function dimensions(string $slug): array
    {
        $ranges = $this->ranges($slug);

        return [
            'power' => ['Мощность', array_map(fn (array $range) => $range[0], $ranges['power'])],
            'area' => ['Площадь обогрева', array_map(fn (array $range) => $range[0], $ranges['area'])],
            ...($slug === 'kotly-na-pelletah' ? [
                'hopper_volume' => ['Объём бункера', array_map(fn (array $range) => $range[0], $ranges['hopper_volume'])],
                'hopper' => ['Вместимость бункера', array_map(fn (array $range) => $range[0], $ranges['hopper'])],
            ] : []),
            ...match ($slug) {
                'gazovye' => [
                    'mount' => ['Установка', ['wall' => 'Настенный', 'floor' => 'Напольный']],
                    'circuits' => ['Количество контуров', ['one' => 'Одноконтурный', 'two' => 'Двухконтурный']],
                    'chamber' => ['Камера сгорания', ['open' => 'Открытая', 'closed' => 'Закрытая']],
                    'technology' => ['Тип нагрева', ['condensing' => 'Конденсационный', 'conventional' => 'Традиционный']],
                ],
                'elektricheskie' => [
                    'voltage' => ['Напряжение сети', ['220' => '220–230 В', '380' => '380–400 В']],
                    'heating' => ['Нагревательный элемент', ['ten' => 'ТЭНовый', 'electrode' => 'Электродный', 'induction' => 'Индукционный']],
                ],
                'tverdotoplivnye' => [
                    'fuel' => ['Топливо', ['wood' => 'Дрова', 'coal' => 'Уголь', 'briquettes' => 'Брикеты', 'pellets' => 'Пеллеты']],
                    'firebox' => ['Материал топки', ['steel' => 'Сталь', 'cast-iron' => 'Чугун']],
                ],
                'kotly-na-pelletah' => [
                    'fuel' => ['Топливо', ['pellets' => 'Пеллеты', 'wood' => 'Дрова', 'coal' => 'Уголь', 'briquettes' => 'Брикеты']],
                ],
                default => [],
            },
        ];
    }

    public function catalogFacts(Product $product, string $slug, array $dimensions): array
    {
        $options = [];
        $card = [];
        $numericRanges = $this->ranges($slug);
        foreach ($numericRanges as $kind => $ranges) {
            $fact = $this->numericFact($product, $kind);
            if ($fact['value'] === null) {
                continue;
            }

            $value = $fact['value'];
            $unit = match ($kind) {
                'power' => 'кВт',
                'area' => 'м²',
                'hopper_volume' => 'л',
                'hopper' => 'кг',
            };
            $card[$dimensions[$kind][0]] = str_replace('.', ',', (string) $value).' '.$unit;
            foreach ($ranges as $key => [, $min, $max]) {
                if (($min === null || $value > $min) && ($max === null || $value <= $max)) {
                    $options[$kind] = [$key];
                    break;
                }
            }
        }

        $rows = [];
        foreach ($product->allAttributeValues as $attributeValue) {
            if ($attributeValue->attribute && ($value = $attributeValue->option?->name ?? $attributeValue->value)) {
                $rows[] = [$this->normalizeName($attributeValue->attribute->name), (string) $value];
            }
        }
        foreach ($product->specs ?: [] as $key => $spec) {
            if (is_array($spec)) {
                $key = $spec['key'] ?? $spec['name'] ?? '';
                $spec = $spec['value'] ?? '';
            }
            if (is_string($key) && is_scalar($spec)) {
                $rows[] = [$this->normalizeName($key), (string) $spec];
            }
        }

        foreach (array_diff_key($dimensions, $numericRanges) as $key => [$label, $choices]) {
            $values = $this->categoricalFact($key, $rows, $product->name);
            if ($values === []) {
                continue;
            }

            $options[$key] = $values;
            $card[$label] = implode(' / ', array_map(fn ($value) => $choices[$value], $values));
        }

        return ['options' => $options, 'card' => array_slice($card, 0, 3, true)];
    }

    private function categoricalFact(string $kind, array $rows, string $name): array
    {
        [$keyPattern, $patterns, $inName] = match ($kind) {
            'mount' => ['установк|монтаж|размещени|исполнени', ['wall' => '\bнастенн\pL*', 'floor' => '\bнапольн\pL*'], true],
            'circuits' => ['контур|назначение котла|тип котла|^тип$', ['one' => '\b(?:одно\s*контур\pL*|1(?:\s+контур\pL*)?)\b', 'two' => '\b(?:двух\s*контур\pL*|2(?:\s+контур\pL*)?)\b'], true],
            'chamber' => ['камер.*сгоран|камер.*горен', ['open' => '\bоткрыт\pL*', 'closed' => '\bзакрыт\pL*'], false],
            'technology' => ['тип.*котла|тип.*нагрев|технологи|принцип.*работы|^тип$', ['condensing' => '\bконденсацион\pL*', 'conventional' => '\b(?:традицион|конвекцион)\pL*'], true],
            'voltage' => ['напряжен|электропитани|питающ.*сет', ['220' => '\b(?:220|230)\s*(?:в|v)?\b', '380' => '\b(?:380|400)\s*(?:в|v)?\b'], true],
            'heating' => ['тип.*нагрев|нагревательн.*элемент|принцип.*работы|тип котла', ['ten' => '\bтэн\pL*', 'electrode' => '\bэлектродн\pL*', 'induction' => '\bиндукцион\pL*'], true],
            'fuel' => ['топлив|горюч', ['wood' => '\bдров\pL*', 'coal' => '\bуг(?:оль|л\pL*)', 'briquettes' => '\bбрикет\pL*', 'pellets' => '\bпеллет\pL*'], true],
            'firebox' => ['материал.*(?:топк|камер.*сгоран)', ['steel' => '\bстал[ьи]\pL*', 'cast-iron' => '\bчугун\pL*'], false],
        };

        $values = [];
        foreach ($rows as [$key, $raw]) {
            if (preg_match('/'.$keyPattern.'/u', $key)) {
                array_push($values, ...$this->matchingOptions($patterns, $raw));
            }
        }
        if ($values === [] && $inName) {
            $input = match ($kind) {
                'voltage' => preg_match_all('~\b(?:220|230|380|400)(?:\s*[/–-]\s*(?:220|230|380|400))?\s*(?:в|v)\b~ui', $name, $matches) ? implode(' ', $matches[0]) : '',
                'circuits' => preg_replace('/\d+/', '', $name),
                'fuel' => preg_match('/\bна\s+(?:дровах|угле|пеллетах|брикетах)\b/ui', $name) ? $name : '',
                default => $name,
            };
            $values = $this->matchingOptions($patterns, $input);
        }
        $values = array_values(array_unique($values));

        return count($values) > 1 && ! in_array($kind, ['voltage', 'fuel'], true) ? [] : $values;
    }

    private function matchingOptions(array $patterns, string $input): array
    {
        $input = $this->normalizeName($input);

        return array_map('strval', array_keys(array_filter($patterns, fn (string $pattern) => preg_match('/'.$pattern.'/u', $input))));
    }

    /** @return array{status:string,value:?float,source:?string} */
    public function numericFact(Product $product, string $kind): array
    {
        $candidates = collect();
        foreach ($product->allAttributeValues as $value) {
            if ($value->option_id || ! $value->attribute || blank($value->value)) {
                continue;
            }
            $score = $this->factKeyScore($value->attribute->name, $kind);
            if ($score > 0 && ($numeric = $this->numericValue((string) $value->value, $kind, $value->attribute->name.' '.$value->attribute->suffix)) !== null) {
                $candidates->push(['score' => 300 + $score, 'value' => $numeric, 'source' => 'attribute']);
            }
        }
        foreach ($product->specs ?: [] as $specKey => $spec) {
            if (is_array($spec)) {
                $key = $spec['key'] ?? $spec['name'] ?? '';
                $key = is_string($key) ? $key : '';
                $value = is_scalar($spec['value'] ?? null) ? (string) $spec['value'] : '';
            } elseif (is_string($specKey) && (is_string($spec) || is_numeric($spec))) {
                // Часть старых карточек хранит характеристики как
                // {"Мощность":"35 кВт"}, а не списком key/value.
                $key = $specKey;
                $value = (string) $spec;
            } else {
                continue;
            }
            $score = $this->factKeyScore($key, $kind);
            if ($score > 0 && ($numeric = $this->numericValue($value, $kind, $key)) !== null) {
                $candidates->push(['score' => 200 + $score, 'value' => $numeric, 'source' => 'spec']);
            }
        }

        if ($numeric = $this->numericFromName($product->name, $kind)) {
            $candidates->push(['score' => 100, 'value' => $numeric, 'source' => 'name']);
        }
        if ($candidates->isEmpty()) {
            return ['status' => 'missing_numeric_fact', 'value' => null, 'source' => null];
        }

        $topScore = (int) $candidates->max('score');
        $top = $candidates->where('score', $topScore);
        $values = $top->pluck('value')->map(fn ($value): float => round((float) $value, 3))->unique()->values();
        if ($values->count() !== 1) {
            return ['status' => 'conflicting_numeric_facts', 'value' => null, 'source' => null];
        }

        return [
            'status' => 'resolved',
            'value' => (float) $values->first(),
            'source' => (string) $top->first()['source'],
        ];
    }

    private function factKeyScore(string $key, string $kind): int
    {
        $key = $this->normalizeName($key);
        if (in_array($kind, ['hopper', 'hopper_volume'], true)) {
            return str_contains($key, 'бункер') && preg_match('/объем|вместимост|емкост|вес.*пеллет|загрузк/u', $key) ? 20 : 0;
        }
        if ($kind === 'area') {
            if (! str_contains($key, 'площад') || (! str_contains($key, 'отоп') && ! str_contains($key, 'отапл') && ! str_contains($key, 'обогрев'))) {
                return 0;
            }

            return str_contains($key, 'помещ') ? 30 : 20;
        }
        if (! str_contains($key, 'мощност') || str_contains($key, 'площад')) {
            return 0;
        }
        foreach (['гвс', 'электричес', 'потребляем', 'насос', 'вентилятор', 'горелк', 'ступен'] as $excluded) {
            if (str_contains($key, $excluded)) {
                return 0;
            }
        }

        return match (true) {
            str_contains($key, 'номиналь') => 40,
            $key === 'мощность', $key === 'мощность квт', str_contains($key, 'мощность котла') => 35,
            str_contains($key, 'тепловая') => 30,
            default => 10,
        };
    }

    private function numericValue(string $raw, string $kind, string $field): ?float
    {
        $raw = str_replace(',', '.', trim($raw));
        $unitPattern = match ($kind) {
            'power' => '(?:квт|kw)',
            'area' => '(?:м²|м2|m2|m²|кв\.?\s*м)',
            'hopper_volume' => '(?:л(?:итр\pL*)?|l|дм[³3]|dm[³3])',
            'hopper' => '(?:кг|kg)',
        };
        preg_match_all('/(\d+(?:\.\d+)?)\s*'.$unitPattern.'(?![\pL\pN])/ui', $raw, $withUnit);
        $numbers = array_map('floatval', $withUnit[1] ?? []);
        if ($numbers === []) {
            if (in_array($kind, ['hopper', 'hopper_volume'], true) && ! preg_match('/\b'.$unitPattern.'(?![\pL\pN])/ui', $field)) {
                return null;
            }
            if (preg_match('/\pL|[³²%]/u', $raw) && ! preg_match('/^(?:до|от)\s*\d+(?:\.\d+)?$/ui', $raw)) {
                return null;
            }
            preg_match_all('/\d+(?:\.\d+)?/', $raw, $plain);
            $numbers = array_map('floatval', $plain[0] ?? []);
        }
        if ($numbers === []) {
            return null;
        }

        $value = max($numbers);
        $limit = $kind === 'power' ? 5000 : 100000;

        return $value > 0 && $value <= $limit ? $value : null;
    }

    private function numericFromName(string $name, string $kind): ?float
    {
        if (in_array($kind, ['hopper', 'hopper_volume'], true)) {
            return null;
        }

        $pattern = $kind === 'power'
            ? '/(\d+(?:[.,]\d+)?)\s*(?:квт|kw)\b/ui'
            : '/(?:до\s*)?(\d+(?:[.,]\d+)?)\s*(?:м²|м2|m2|m²|кв\.?\s*м)\b/ui';
        preg_match_all($pattern, $name, $matches);
        if (($matches[1] ?? []) === []) {
            return null;
        }

        return max(array_map(fn ($value): float => (float) str_replace(',', '.', $value), $matches[1]));
    }

    private function normalizeName(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['ё', '²'], ['е', '2'], $value);
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value);

        return trim(preg_replace('/\s+/u', ' ', $value));
    }
}

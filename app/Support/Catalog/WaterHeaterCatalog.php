<?php

namespace App\Support\Catalog;

use App\Models\Product;

class WaterHeaterCatalog extends SpecificationCatalog
{
    public const ROOT = 'vodonagrevateli';

    public const GAS = 'vodogreynaya-kolonka';

    public const PARTS = 'komplektuyushchie-dlya-vodonagrevateley';

    public const BUFFER = 'bufernye-emkosti';

    public const TYPES = [
        'electric' => ['name' => 'Электрические водонагреватели', 'short' => 'Электрические', 'description' => 'Накопительные и проточные'],
        self::GAS => ['name' => 'Газовые водонагреватели', 'short' => 'Газовые', 'description' => 'Газовые колонки и водонагреватели'],
        'kosvennye' => ['name' => 'Бойлеры косвенного нагрева', 'short' => 'Косвенного нагрева', 'description' => 'Нагрев от системы отопления'],
        'kombinirovannye' => ['name' => 'Комбинированные водонагреватели', 'short' => 'Комбинированные', 'description' => 'Несколько источников нагрева'],
    ];

    public const LANDINGS = [
        'nakopitelnye' => [
            'title' => 'Электрические накопительные водонагреватели',
            'short' => 'Накопительные',
            'description' => 'Электрические бойлеры с баком. Сравните объём, мощность, покрытие бака и варианты установки.',
            'filters' => ['kind' => 'storage'],
        ],
        'protochnye' => [
            'title' => 'Электрические проточные водонагреватели',
            'short' => 'Проточные',
            'description' => 'Сравните мощность и расход горячей воды у проточных моделей. Расход зависит от перепада температуры.',
            'filters' => ['kind' => 'flow'],
        ],
    ];

    private const NUMERIC = [
        'volume' => ['Объём бака', 'л', '(?:л(?:итр\pL*)?|l|дм[³3])', 10000],
        'flow' => ['Производительность', 'л/мин', '(?:л|l)\s*/\s*(?:мин|min)', 100],
        'power' => ['Мощность нагрева', 'кВт', '(?:квт|kw)', 250],
    ];

    private const OPTIONS = [
        'kind' => ['Тип нагрева', ['storage' => 'Накопительный', 'flow' => 'Проточный', 'indirect' => 'Косвенный', 'combined' => 'Комбинированный']],
        'mount' => ['Монтаж', ['wall' => 'Настенный', 'floor' => 'Напольный']],
        'orientation' => ['Положение при установке', ['vertical' => 'Вертикальное', 'horizontal' => 'Горизонтальное', 'universal' => 'Универсальное']],
        'tank' => ['Внутренний бак', ['enamel' => 'Эмалированный', 'glass' => 'Стеклокерамика', 'stainless' => 'Нержавеющая сталь']],
        'element' => ['Нагревательный элемент', ['dry' => 'Сухой ТЭН', 'wet' => 'Мокрый ТЭН']],
        'chamber' => ['Камера сгорания', ['open' => 'Открытая', 'closed' => 'Закрытая']],
        'ignition' => ['Тип розжига', ['piezo' => 'Пьезорозжиг', 'electronic' => 'Электронный']],
        'exchangers' => ['Число теплообменников', ['one' => '1 теплообменник', 'two' => '2 теплообменника']],
    ];

    public static function name(string $slug): ?string
    {
        return match ($slug) {
            self::ROOT => 'Водонагреватели и бойлеры',
            self::BUFFER => 'Буферные ёмкости',
            'gas' => self::TYPES[self::GAS]['name'],
            default => self::TYPES[$slug]['name'] ?? null,
        };
    }

    public function dimensions(string $slug): array
    {
        $slug = $slug === 'gas' ? self::GAS : $slug;
        $volume = match ($slug) {
            self::BUFFER => [200, 300, 500, 750, 1000, 1500],
            'electric' => [15, 30, 50, 80, 100, 150, 200, 300],
            default => [30, 50, 80, 100, 150, 200, 300, 500, 750, 1000],
        };
        $power = $slug === self::GAS ? [16, 20, 24, 30] : [2, 3, 6, 12, 18, 24];
        $ranges = [
            'volume' => $volume,
            'flow' => [3, 5, 8, 10, 12, 16],
            'power' => $power,
        ];
        $fields = match ($slug) {
            self::ROOT => ['kind', 'volume', 'flow', 'power', 'mount', 'orientation', 'tank', 'element', 'chamber', 'ignition', 'exchangers'],
            self::GAS => ['flow', 'power', 'chamber', 'ignition', 'mount', 'volume'],
            'electric' => ['kind', 'volume', 'flow', 'power', 'mount', 'orientation', 'tank', 'element'],
            'kosvennye', 'kombinirovannye' => ['volume', 'mount', 'orientation', 'tank', 'exchangers', 'element'],
            self::BUFFER => ['volume', 'mount', 'orientation', 'exchangers'],
            default => [],
        };

        $dimensions = [];
        foreach ($fields as $field) {
            if (! isset($ranges[$field])) {
                $dimensions[$field] = self::OPTIONS[$field];
                continue;
            }

            [$label, $unit] = self::NUMERIC[$field];
            $labels = [];
            $previous = null;
            foreach ($ranges[$field] as $limit) {
                $labels['to-'.$limit] = ($previous === null ? 'до '.$limit : $previous.'–'.$limit).' '.$unit;
                $previous = $limit;
            }
            $labels['over'] = 'свыше '.$previous.' '.$unit;
            $dimensions[$field] = [$label, $labels];
        }

        return $dimensions;
    }

    public function productFacts(Product $product, string $slug, array $dimensions): array
    {
        $rows = [];
        foreach ($product->allAttributeValues as $value) {
            $raw = $value->option?->name ?? $value->value;
            if ($value->attribute && filled($raw)) {
                $rows[] = [$this->fieldKey($value->attribute->name, (string) $value->attribute->suffix), (string) $raw, 2];
            }
        }
        foreach ($product->specs ?: [] as $key => $raw) {
            $unit = '';
            if (is_array($raw)) {
                $key = $raw['key'] ?? $raw['name'] ?? '';
                $unit = is_string($raw['unit'] ?? null) ? $raw['unit'] : '';
                $raw = $raw['value'] ?? '';
            }
            if (is_string($key) && is_scalar($raw)) {
                $rows[] = [$this->fieldKey($key, $unit), (string) $raw, 1];
            }
        }

        $type = $product->category?->slug ?? $slug;
        $kind = match ($type) {
            'kosvennye' => 'indirect',
            'kombinirovannye' => 'combined',
            self::GAS, 'gas' => $this->choice('kind', $rows, $product->name) ?? 'flow',
            default => $this->choice('kind', $rows, $product->name),
        };

        $numeric = [];
        foreach (array_keys(array_intersect_key(self::NUMERIC, $dimensions)) as $field) {
            if (($field === 'volume' && $kind === 'flow') || ($field === 'flow' && in_array($kind, ['storage', 'combined', 'indirect'], true))
                || ($field === 'power' && in_array($kind, ['indirect', 'combined'], true))) {
                continue;
            }
            $value = $this->number($field, $rows, $product->name);
            if ($value !== null) {
                $numeric[$field] = $value;
            }
        }
        if ($kind === null && $type === 'electric' && isset($numeric['volume']) && ! isset($numeric['flow'])) {
            $kind = 'storage';
        }

        $options = [];
        $card = [];
        foreach ($dimensions as $field => [$label, $choices]) {
            if (isset(self::NUMERIC[$field])) {
                if (isset($numeric[$field])) {
                    $value = $numeric[$field];
                    foreach (array_keys($choices) as $key) {
                        if ($key === 'over' || $value <= (int) substr($key, 3)) {
                            $options[$field] = [$key];
                            break;
                        }
                    }
                    $card[$label] = str_replace('.', ',', (string) $value).' '.self::NUMERIC[$field][1];
                }

                continue;
            }

            $value = $field === 'kind' ? $kind : $this->choice($field, $rows, $product->name);
            if ($value !== null && isset($choices[$value])) {
                $options[$field] = [$value];
                if ($field !== 'kind') {
                    $card[$label] = $choices[$value];
                }
            }
        }

        return ['options' => $options, 'card' => array_slice($card, 0, 3, true)];
    }

    private function number(string $field, array $rows, string $name): ?float
    {
        [, , $unit, $max] = self::NUMERIC[$field];
        $keyPattern = match ($field) {
            'volume' => '/^(?:объем|вместимость)(?: номинальный| полезный)?(?: (?:внутреннего |накопительного )?(?:бака|водонагревателя|емкости|воды))?(?: л| литр[а-я]*| l| дм[³3])?$/u',
            'flow' => '/(?:производительност|расход (?:горячей )?воды|проток)/u',
            'power' => '/^(?:номинальная |максимальная |потребляемая |тепловая )?мощность(?: нагрева| нагревателя| водонагревателя| тэна)?(?: квт| kw| вт| w)?$/u',
        };

        $values = [];
        foreach ($rows as [$key, $raw, $source]) {
            $watts = $field === 'power' && (preg_match('/(?:^| )(?:вт|w)$/u', $key) || preg_match('/(?<=\d)\s*(?:вт|w)\.?$/ui', trim($raw)));
            $units = $watts ? '(?:вт|w)' : $unit;
            $unitOptional = $field !== 'flow' || str_contains($key, 'л мин') || str_contains($key, 'l min');
            if (preg_match($keyPattern, $key)
                && preg_match('~^(?:до\s*)?(\d+(?:[,.]\d+)?)\s*(?:'.$units.')'.($unitOptional ? '?' : '').'\.?$~ui', trim($raw), $match)) {
                $value = (float) str_replace(',', '.', $match[1]) / ($watts ? 1000 : 1);
                if ($value > 0 && $value <= $max) {
                    $priority = str_contains($key, 'номиналь') ? 20 : 0;
                    $values[$priority + $source][] = $value;
                }
            }
        }

        if ($values !== []) {
            $preferred = array_unique($values[max(array_keys($values))]);

            return count($preferred) === 1 ? (float) reset($preferred) : null;
        }

        if (preg_match_all('~(?<![\d.,])(\d+(?:[,.]\d+)?)\s*'.$unit.'(?![\pL\pN]|\s*/)~ui', $name, $matches)
            && count(array_unique($matches[1])) === 1) {
            $value = (float) str_replace(',', '.', $matches[1][0]);

            return $value > 0 && $value <= $max ? $value : null;
        }

        return null;
    }

    private function choice(string $field, array $rows, string $name): ?string
    {
        [$keyPattern, $choices, $readName] = match ($field) {
            'kind' => ['^(?:тип(?: нагрева| водонагревателя)?|принцип работы|способ нагрева)$', ['storage' => 'накопительн', 'flow' => 'проточн'], true],
            'mount' => ['(?:тип |способ |вариант )?(?:установк|монтаж|креплен)', ['wall' => 'настенн', 'floor' => 'напольн'], true],
            'orientation' => ['установк|монтаж|ориентац|положени', ['vertical' => 'вертикальн', 'horizontal' => 'горизонтальн', 'universal' => 'универсальн'], true],
            'tank' => ['(?:покрытие|материал).*(?:бак|емкост)', ['enamel' => 'эмал', 'glass' => 'стеклокерамик|биостеклофарфор', 'stainless' => 'нержаве'], false],
            'element' => ['тэн|нагревательн.*элемент', ['dry' => 'сух(?:ой|ие|ого)', 'wet' => 'мокр(?:ый|ые|ого)|погружн'], true],
            'chamber' => ['камер.*сгоран', ['open' => 'открыт', 'closed' => 'закрыт'], true],
            'ignition' => ['розжиг', ['piezo' => 'пьезо', 'electronic' => 'электрон|автомат|батаре'], false],
            'exchangers' => ['(?:число|количество).*(?:теплообменник|змеевик)', ['one' => '\b1\b|один', 'two' => '\b2\b|два'], false],
        };

        $matches = [];
        foreach ($rows as [$key, $raw]) {
            if (preg_match('/'.$keyPattern.'/u', $key)) {
                foreach ($choices as $value => $pattern) {
                    if (preg_match('/'.$pattern.'/u', $this->normalize($raw))) {
                        $matches[$value] = true;
                    }
                }
            }
        }
        if ($matches === [] && $readName) {
            foreach ($choices as $value => $pattern) {
                if (preg_match('/'.$pattern.'/u', $this->normalize($name))) {
                    $matches[$value] = true;
                }
            }
        }
        if ($field === 'orientation' && (isset($matches['universal']) || count($matches) === 2)) {
            return 'universal';
        }

        return count($matches) === 1 ? array_key_first($matches) : null;
    }

    private function fieldKey(string $name, string $unit): string
    {
        $key = $this->normalize($name);
        $suffix = $this->normalize($unit);

        return $suffix === '' || str_ends_with($key, ' '.$suffix) ? $key : $key.' '.$suffix;
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/[^\pL\pN]+/u', ' ', str_replace('ё', 'е', mb_strtolower($value))));
    }
}

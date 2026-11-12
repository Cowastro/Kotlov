<?php

namespace App\Support\Catalog;

use App\Models\Product;

class HeaterCatalog extends SpecificationCatalog
{
    public const NAMES = [
        'obogrevateli' => 'Обогреватели',
        'elektricheskie-konvektoryi' => 'Электрические конвекторы',
        'maslyanye-obrevateli' => 'Масляные обогреватели',
        'infrakrasnye-obrevateli' => 'Инфракрасные обогреватели',
        'teplovye-pushki' => 'Тепловые пушки',
        'teploventilyatory' => 'Тепловентиляторы',
        'teplovye-zavesy' => 'Тепловые завесы',
        'elektricheskie-teploakkumulyatory' => 'Электрические теплоаккумуляторы',
        'elektricheskie-teplye-plintusy' => 'Электрические тёплые плинтусы',
    ];

    public function dimensions(string $slug): array
    {
        $range = function (array $limits, string $unit): array {
            $options = [];
            $previous = null;
            foreach ($limits as $limit) {
                $key = 'to-'.str_replace('.', '-', (string) $limit);
                $options[$key] = ($previous === null ? 'до ' : 'свыше '.str_replace('.', ',', (string) $previous).' до ').str_replace('.', ',', (string) $limit).' '.$unit;
                $previous = $limit;
            }
            $options['over'] = 'свыше '.str_replace('.', ',', (string) $previous).' '.$unit;
            return $options;
        };
        return [
            'power' => ['Мощность обогрева', $range([0.5, 1, 1.5, 2, 2.5, 3, 5], 'кВт')],
            'area' => ['Площадь обогрева', $range([10, 15, 20, 25, 30, 50], 'м²')],
            'mount' => ['Тип установки', ['wall' => 'Настенный', 'floor' => 'Напольный']],
        ];
    }

    public static function records(): array
    {
        static $records;
        return $records ??= json_decode(file_get_contents(resource_path('data/heater-manufacturer-facts.json')), true, flags: JSON_THROW_ON_ERROR)['products'];
    }

    public static function verified(Product $product): array
    {
        return self::records()[$product->slug] ?? [];
    }

    public function productFacts(Product $product, string $slug, array $dimensions): array
    {
        $fact = self::verified($product);
        $options = [];
        $card = [];
        foreach (['power' => ['power_kw', 'Мощность обогрева', 'кВт'], 'area' => ['area_max_m2', 'Площадь обогрева', 'м²']] as $field => [$key, $label, $unit]) {
            if (! isset($fact[$key])) continue;
            $number = (float) $fact[$key];
            foreach ($dimensions[$field][1] as $option => $text) {
                if ($option === 'over' || $number <= (float) str_replace('-', '.', substr($option, 3))) {
                    $options[$field] = [$option];
                    break;
                }
            }
            $value = $this->number($number);
            if ($field === 'area') {
                $value = isset($fact['area_min_m2']) ? $this->number($fact['area_min_m2']).'–'.$value : 'до '.$value;
            }
            $card[$label] = $value.' '.$unit;
        }
        if (! empty($fact['mount'])) {
            $options['mount'] = array_values(array_intersect(['wall', 'floor'], $fact['mount']));
            $card['Тип установки'] = implode(' / ', array_map(fn ($key) => $dimensions['mount'][1][$key], $options['mount']));
        }
        return ['options' => $options, 'card' => $card];
    }

    public function details(Product $product, iterable $attributes): array
    {
        $rows = $this->productFacts($product, $product->category->slug, $this->dimensions($product->category->slug))['card'];
        if (isset(self::verified($product)['consumption_kw'])) {
            $rows['Потребляемая мощность'] = $this->number((float) self::verified($product)['consumption_kw']).' кВт';
        }
        foreach ($attributes as $attribute) {
            $name = (string) $attribute->attribute?->name;
            if ($name !== '' && ! self::managed($name)) {
                $rows[$name] = $attribute->display_value;
            }
        }
        foreach ($product->specs ?: [] as $key => $raw) {
            if (is_array($raw)) {
                $key = $raw['key'] ?? $raw['name'] ?? '';
                $raw = ($raw['value'] ?? '').(! empty($raw['unit']) ? ' '.$raw['unit'] : '');
            }
            if (is_string($key) && $key !== '' && is_scalar($raw) && ! self::managed($key)) $rows[$key] ??= (string) $raw;
        }
        if ($note = self::verified($product)['mount_note'] ?? null) $rows['Комплектация для установки'] = $note;
        return $rows;
    }

    public static function managed(string $name): bool
    {
        return (bool) preg_match('/^(?:(?:макс(?:имальная)?[.]?|номинальная|потребляемая|тепловая)\s+)?мощность|площадь.*(?:обогрева|отопления|помещения)|(?:обогреваемая|отапливаемая|обслуживаемая) площадь|эффективен для|^(?:тип установки|способ установки|вариант.*(?:установки|размещения)|вид установки|монтаж|установка)(?:$|[ ,(])/ui', trim($name));
    }

    private function number(float $value): string
    {
        return str_replace('.', ',', (string) $value);
    }
}

<?php

namespace App\Services;

use App\Models\Product;

class HeatPumpProductPresenter
{
    public const ROOT = 'teplovyie-nasosyi';
    public const AIR_WATER = 'teplovye-nasosy-vozduh-voda';
    public const PARTS = 'komplektuyushchie-dlya-teplovykh-nasosov';

    public const PUMP_CATEGORIES = [
        self::ROOT, self::AIR_WATER, 'geotermalnye-teplovye-nasosy', 'teplovye-nasosy-dlya-basseynov',
    ];

    public function rtcModel(Product $product): ?string
    {
        if ($product->category?->slug !== self::AIR_WATER
            || ! preg_match('/\bRegulus RTC (6i|13e)\b/i', $product->name, $match)) {
            return null;
        }

        return strtolower($match[1]);
    }

    public function catalogSpecs(Product $product): array
    {
        // Regulus publishes these values in the technical sheets for 17735 and 19437.
        $rtc = $this->rtcModel($product);
        if ($rtc !== null) {
            return [
                'Тепловая мощность A7/W35' => $rtc === '6i' ? '5,42 кВт (A7/W35, 85 Гц)' : '10,13 кВт (A7/W35, 79 Гц)',
                'SCOP' => $rtc === '6i' ? 'SCOP 4,47' : 'SCOP 4,71',
                'Температура подачи' => 'До 55 °C',
                'Питание' => '230 В, 1 фаза',
            ];
        }

        $specs = $this->normalizeSpecs($product->specs);
        foreach ($product->allAttributeValues as $attributeValue) {
            if ($attributeValue->hasDisplayValue()) {
                $specs[mb_strtolower(trim($attributeValue->attribute->name))] ??= $attributeValue->display_value;
            }
        }

        $card = [];
        foreach ($specs as $name => $value) {
            if (preg_match('/(?:тепловая мощность|мощность обогрева|мощность отопления).*a\s*7\s*\/\s*w\s*35/ui', $name)) {
                $card['Тепловая мощность A7/W35'] = preg_match('/a\s*7\s*\/\s*w\s*35/ui', $value) ? $value : $value.' (A7/W35)';
                break;
            }
        }

        $power = $this->value($specs, ['тепловая мощность', 'мощность теплового насоса', 'мощность отопления']);
        if ($card === [] && $power !== null) {
            $card['Тепловая мощность'] = $power;
        }
        $scop = $this->value($specs, ['scop', 'сезонный коэффициент эффективности (scop)']);
        if ($scop !== null) {
            $card['SCOP'] = str_starts_with(mb_strtolower($scop), 'scop') ? $scop : 'SCOP '.$scop;
        }
        $temperature = $this->value($specs, [
            'максимальная температура в контуре отопления', 'максимальная температура воды',
            'макс. температура воды', 'максимальная температура подачи',
        ]);
        if ($temperature !== null) {
            $card['Температура подачи'] = $temperature;
        }
        $supply = $this->value($specs, ['питание', 'напряжение питания', 'электропитание']);
        if ($supply !== null) {
            $card['Питание'] = $supply;
        }

        return $card;
    }

    public function build(Product $product): ?array
    {
        $brandName = trim((string) ($product->brand?->name ?? ''));
        $productName = trim((string) $product->name);

        if (mb_strtoupper($brandName) !== 'KOTLOV GE'
            && ! str_contains(mb_strtoupper($productName), 'KOTLOV GE')) {
            return null;
        }

        $specs = $this->normalizeSpecs($product->specs);
        $refrigerant = $this->value($specs, ['хладагент']);
        $isR290 = str_contains(mb_strtoupper($refrigerant . ' ' . $productName), 'R290');

        return [
            'model' => $this->value($specs, ['модель']) ?: $this->modelFromName($productName),
            'power' => $this->value($specs, ['мощность', 'тепловая мощность']),
            'refrigerant' => $refrigerant ?: ($isR290 ? 'R290' : null),
            'power_supply' => $this->value($specs, ['питание', 'напряжение питания', 'электропитание']),
            'flow_temperature' => $this->value($specs, ['температура воды', 'максимальная температура воды', 'макс. температура воды']),
            'series' => $this->value($specs, ['серия']),
            'positioning' => $isR290
                ? 'Высокотемпературная модель для новых и реконструируемых систем, в том числе объектов с радиаторным отоплением.'
                : 'Модель для энергоэффективного отопления, охлаждения и горячего водоснабжения дома.',
            'uses' => $isR290
                ? ['Радиаторное отопление', 'Тёплый пол', 'Горячее водоснабжение', 'Реконструкция котельной']
                : ['Тёплый пол', 'Низкотемпературные радиаторы', 'Отопление и охлаждение', 'Горячее водоснабжение'],
            'landing_url' => $isR290 ? '/teplovye-nasosy-r290' : '/teplovye-nasosy-dlya-doma',
            'landing_label' => $isR290 ? 'О серии KOTLOV GE R290' : 'Подбор теплового насоса',
        ];
    }

    private function normalizeSpecs(mixed $rawSpecs): array
    {
        if (is_string($rawSpecs)) {
            $rawSpecs = json_decode($rawSpecs, true);
        }

        if (! is_array($rawSpecs)) {
            return [];
        }

        $normalized = [];

        foreach ($rawSpecs as $key => $row) {
            if (is_array($row)) {
                $name = trim((string) ($row['key'] ?? $row['name'] ?? ''));
                $value = trim((string) ($row['value'] ?? ''));
                $unit = trim((string) ($row['unit'] ?? ''));
                if ($value !== '' && $unit !== '' && ! str_contains($value, $unit)) {
                    $value .= ' '.$unit;
                }
            } elseif (is_string($key)) {
                $name = trim($key);
                $value = trim((string) $row);
            } else {
                continue;
            }

            if ($name !== '' && $value !== '') {
                $normalized[mb_strtolower($name)] = $value;
            }
        }

        return $normalized;
    }

    private function value(array $specs, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $specs[mb_strtolower($key)] ?? null;
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function modelFromName(string $name): ?string
    {
        if (preg_match('/\b(?:NL-)?FLM[\w-]+(?:\/R(?:290|32))?\b/ui', $name, $match)) {
            return $match[0];
        }

        if (preg_match('/\bOlympus\b[^,]*/ui', $name, $match)) {
            return trim($match[0]);
        }

        return null;
    }
}

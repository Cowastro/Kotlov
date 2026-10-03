<?php

namespace App\Services;

use App\Models\Product;

class HeatPumpProductPresenter
{
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
            'landing_label' => $isR290 ? 'Подробнее о тепловых насосах R290' : 'Как выбрать тепловой насос для дома',
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

<?php

namespace App\Services;

class MembraneTankVolumeNormalizer
{
    private const ACCESSORY_PATTERN = '/\b(?:мембран(?:а|ы)|кронштейн|комплект|группа\s+подключения|автоматик(?:а|и)|хомут|креплен(?:ие|ия))\b/ui';

    private const TANK_PATTERN = '/\b(?:бак|гидроаккумулятор)\b/ui';

    public function extract(string $name, array $specs = [], array $attributes = []): ?int
    {
        if (preg_match(self::ACCESSORY_PATTERN, $name)) {
            return null;
        }

        foreach ($attributes as $attributeName => $value) {
            if ($this->isVolumeLabel((string) $attributeName)) {
                $volume = $this->number($value);

                if ($volume !== null) {
                    return $volume;
                }
            }
        }

        foreach ($this->flattenSpecs($specs) as $attributeName => $value) {
            if ($this->isVolumeLabel((string) $attributeName)) {
                $volume = $this->number($value);

                if ($volume !== null) {
                    return $volume;
                }
            }
        }

        if (preg_match('/(?<!\d)(\d{1,4}(?:[.,]\d+)?)\s*(?:л(?:итр(?:а|ов)?)?|l)\b/ui', $name, $matches)) {
            return $this->number($matches[1]);
        }

        if (! preg_match(self::TANK_PATTERN, $name)) {
            return null;
        }

        if (preg_match('/(?<![\d.,])(\d{1,4})(?![\d.,])(?:\s+[A-ZА-Я]{1,3})?\s*(?:[«"(]|$)/u', trim($name), $matches)) {
            return $this->number($matches[1]);
        }

        return null;
    }

    private function isVolumeLabel(string $name): bool
    {
        $normalized = mb_strtolower(str_replace('ё', 'е', trim($name)));

        return preg_match('/(?:^|\s)(?:объем|емкость|литраж)(?:\s|$|,|\()/u', $normalized) === 1
            && ! str_contains($normalized, 'парил')
            && ! str_contains($normalized, 'камер');
    }

    private function number(mixed $value): ?int
    {
        if (is_array($value)) {
            $value = implode(' ', array_filter($value, 'is_scalar'));
        }

        if (! is_scalar($value) || ! preg_match('/\d{1,4}(?:[.,]\d+)?/', (string) $value, $matches)) {
            return null;
        }

        $number = (float) str_replace(',', '.', $matches[0]);
        $rounded = (int) round($number);

        return $number >= 2 && $number <= 5000 && abs($number - $rounded) < 0.01
            ? $rounded
            : null;
    }

    private function flattenSpecs(array $specs): array
    {
        $flattened = [];

        foreach ($specs as $key => $value) {
            if (is_array($value) && isset($value['name'], $value['value'])) {
                $flattened[(string) $value['name']] = $value['value'];

                continue;
            }

            if (is_string($key) && ! is_array($value)) {
                $flattened[$key] = $value;
            }
        }

        return $flattened;
    }
}

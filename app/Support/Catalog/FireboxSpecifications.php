<?php

namespace App\Support\Catalog;

use App\Models\Product;

class FireboxSpecifications
{
    public const CATEGORY = 'topki';

    public const GLASS = [
        'straight' => 'Прямое',
        'corner' => 'Угловое',
        'three-sided' => 'Трёхстороннее',
        'tunnel' => 'Туннельное',
    ];

    public const DOOR = ['side' => 'Вбок', 'lift' => 'Вверх'];

    /** @return array{glass: ?string, doors: list<string>, card: array<string, string>} */
    public function facts(Product $product): array
    {
        $glassOptions = $this->shapes($product->name);
        $doorOptions = [];
        $dualOpening = false;
        foreach ($product->specs ?? [] as $name => $row) {
            $this->readFact(
                is_array($row) ? ($row['key'] ?? $row['name'] ?? '') : (string) $name,
                is_array($row) ? ($row['value'] ?? '') : $row,
                $glassOptions,
                $doorOptions,
                $dualOpening,
            );
        }
        foreach ($product->allAttributeValues as $attribute) {
            if ($attribute->hasDisplayValue()) {
                $this->readFact(
                    $attribute->attribute->name,
                    $attribute->option?->name ?? $attribute->value,
                    $glassOptions,
                    $doorOptions,
                    $dualOpening,
                );
            }
        }

        // A straight front panel can be part of a tunnel or corner firebox.
        $specificShapes = array_diff(array_unique($glassOptions), ['straight', 'multiple']);
        $glass = match (count($specificShapes)) {
            0 => in_array('straight', $glassOptions, true) && ! in_array('multiple', $glassOptions, true) ? 'straight' : null,
            1 => reset($specificShapes),
            default => null,
        };
        $doors = array_values(array_unique($doorOptions));
        if (count($doors) > 1 && ! $dualOpening) {
            $doors = [];
        }
        $card = [];
        if ($glass !== null) {
            $card['Стекло'] = self::GLASS[$glass];
        }
        if ($doors !== []) {
            $card['Дверца'] = count($doors) === 2 ? 'Вверх и вбок' : self::DOOR[$doors[0]];
        }

        return ['glass' => $glass, 'doors' => $doors, 'card' => $card];
    }

    private function readFact(mixed $name, mixed $value, array &$glassOptions, array &$doorOptions, bool &$dualOpening): void
    {
        if (! is_scalar($name) || ! is_scalar($value)) {
            return;
        }

        $name = str_replace('ё', 'е', mb_strtolower(trim((string) $name)));
        $value = str_replace('ё', 'е', mb_strtolower(trim((string) $value)));

        if (preg_match('/^(?:форма (?:стекла|остекления)|тип остекления|вид топки|главная|остекление)$/u', $name)) {
            $glassOptions = array_merge($glassOptions, $this->shapes($value));
            if (preg_match('/^(?:левое|правое)(?:\s|$)/u', $value)) {
                $glassOptions[] = 'corner';
            }
            if (preg_match('/^(?:прямое|плоское)(?:\s|$)/u', $value)) {
                $glassOptions[] = 'straight';
            }
        }

        if (preg_match('/^(?:тип |способ )?открыван(?:ие|ия)(?: дверц[ыи])?$|^механизм открывания/u', $name)) {
            $lift = preg_match('/\b(?:вверх|подъем\p{L}*|вертикальн\p{L}*|гильотин\p{L}*)\b/u', $value);
            $side = preg_match('/\b(?:вбок|влево|вправо|налево|направо|боков\p{L}*|распашн\p{L}*|в сторону)\b/u', $value);
            if ($lift) {
                $doorOptions[] = 'lift';
            }
            if ($side) {
                $doorOptions[] = 'side';
            }
            $dualOpening = $dualOpening || ($lift && $side);
        }
    }

    private function shapes(string $value): array
    {
        $value = str_replace('ё', 'е', mb_strtolower($value));
        $patterns = [
            'tunnel' => '/\bт[уо]ннел\p{L}*/u',
            'three-sided' => '/\b(?:трехсторон\p{L}*|(?:три|3)\s+стекла|п[- ]образн\p{L}*)/u',
            'corner' => '/\b(?:углов\p{L}*|г[- ]образн\p{L}*|(?:два|2)\s+стекла\s+(?:лева\p{L}*|права\p{L}*))/u',
            'multiple' => '/\b(?:(?:двух|дву)сторон\p{L}*|(?:два|2)\s+стекла)/u',
            'straight' => '/\bпрям\p{L}*\s+(?:стекл\p{L}*|остеклен\p{L}*)/u',
        ];

        return array_keys(array_filter($patterns, fn (string $pattern) => preg_match($pattern, $value)));
    }
}

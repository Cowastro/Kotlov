<?php

namespace App\Support\Catalog;

use App\Models\Product;
use Illuminate\Support\Collection;

class ThreadedFittingCatalog extends SpecificationCatalog
{
    public const ROOT = 'rezbovye-fitingi';

    public const MATERIALS = [
        'latunnye' => 'Латунные',
        'chugunnye' => 'Чугунные',
        'stalnye' => 'Стальные',
    ];

    public function dimensions(string $slug): array
    {
        return ['material' => ['Материал', self::MATERIALS + ['unknown' => 'Материал не указан']]];
    }

    public static function landing(string $selection): ?array
    {
        if (! isset(self::MATERIALS[$selection])) return null;

        return [
            'slug' => $selection,
            'title' => self::MATERIALS[$selection].' резьбовые фитинги',
            'short' => self::MATERIALS[$selection],
            'description' => 'Резьбовые фитинги с указанным материалом. Выберите размер и тип соединения, сравните цены и наличие.',
            'filters' => ['material' => $selection],
        ];
    }

    public function productFacts(Product $product, string $slug, array $dimensions): array
    {
        $body = [];
        $general = [];
        foreach (($product->specs ?? []) as $key => $spec) {
            $name = is_array($spec) ? ($spec['key'] ?? '') : $key;
            $value = is_array($spec) ? ($spec['value'] ?? '') : $spec;
            if (! is_scalar($value)) continue;
            $name = mb_strtolower(trim((string) $name));
            if (in_array($name, ['материал корпуса', 'материал изделия'], true)) $body[] = (string) $value;
            elseif ($name === 'материал') $general[] = (string) $value;
        }
        if ($body === [] && $general === [] && $product->relationLoaded('allAttributeValues')) {
            foreach ($product->allAttributeValues as $attributeValue) {
                if (in_array(mb_strtolower(trim((string) $attributeValue->attribute?->name)), ['материал', 'материал корпуса'], true)) {
                    $general[] = (string) ($attributeValue->option?->name ?? $attributeValue->value);
                }
            }
        }
        // Plating, manufacturer and colour are not evidence of the base metal.
        // Explicit body material wins over mentions of gaskets or other components.
        $text = implode(' ', $body ?: $general) ?: (string) $product->name;
        $matches = [];
        foreach (['latunnye' => '/латун/iu', 'chugunnye' => '/чугун/iu', 'stalnye' => '/сталь|стальн|стал[ьи]|нержав/iu'] as $key => $pattern) {
            if (preg_match($pattern, $text)) $matches[] = $key;
        }
        $material = count($matches) === 1 ? $matches[0] : 'unknown';
        $label = ['latunnye' => 'Латунь', 'chugunnye' => 'Чугун', 'stalnye' => 'Сталь'][$material] ?? null;

        return ['options' => ['material' => [$material]], 'card' => $label ? ['Материал' => $label] : []];
    }

    public function links(Collection $categoryIds): array
    {
        $counts = [];
        foreach ($this->facts(self::ROOT, $categoryIds) as $fact) {
            $material = $fact['options']['material'][0];
            $counts[$material] = ($counts[$material] ?? 0) + 1;
        }
        $links = [];
        foreach (self::MATERIALS as $key => $label) {
            if (($counts[$key] ?? 0) > 0) $links[] = [
                'path' => '/'.self::ROOT.'/'.$key, 'short' => $label,
                'count' => $counts[$key], 'title' => $label.' резьбовые фитинги',
            ];
        }

        return $links;
    }
}

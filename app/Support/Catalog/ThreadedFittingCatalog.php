<?php

namespace App\Support\Catalog;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ThreadedFittingCatalog extends SpecificationCatalog
{
    protected const FACTS_VERSION = 2;

    public const ROOT = 'rezbovye-fitingi';

    public const MATERIALS = [
        'latunnye' => 'Латунные',
        'chugunnye' => 'Чугунные',
        'stalnye' => 'Стальные',
    ];

    public const TYPES = [
        'ugolki' => 'Уголки и угольники',
        'nippeli' => 'Ниппели',
        'troiniki' => 'Тройники',
        'mufty' => 'Муфты',
        'perehodniki' => 'Переходники',
        'amerikanki' => 'Американки',
        'futorki' => 'Футорки',
        'zaglushki' => 'Заглушки и пробки',
        'udliniteli' => 'Удлинители',
        'sgony' => 'Сгоны',
        'kontrgaiki' => 'Контргайки',
        'bochonki' => 'Бочонки',
        'rezby' => 'Резьбы',
        'krestoviny' => 'Крестовины',
        'shtutsery' => 'Штуцеры',
        'oboymy' => 'Ремонтные обоймы',
        'soediniteli' => 'Соединители',
    ];

    public function dimensions(string $slug): array
    {
        return [
            'material' => ['Материал', self::MATERIALS + ['unknown' => 'Материал не указан']],
            'type' => ['Тип фитинга', self::TYPES + ['unknown' => 'Тип не указан']],
        ];
    }

    public function search(Builder $query, string $search): void
    {
        $aliases = [
            'уголок' => 'ugolki', 'уголки' => 'ugolki', 'угольник' => 'ugolki', 'угольники' => 'ugolki',
            'нипель' => 'nippeli', 'ниппель' => 'nippeli', 'ниппели' => 'nippeli',
            'тройник' => 'troiniki', 'тройники' => 'troiniki',
        ];
        $type = $aliases[mb_strtolower(trim($search))] ?? null;
        foreach (self::TYPES as $key => $label) {
            if (mb_strtolower($label) === mb_strtolower(trim($search))) $type = $key;
        }
        if ($type === null) {
            parent::search($query, $search);
            return;
        }

        $ids = Category::active()->where('slug', self::ROOT)->pluck('id');
        $matched = collect($this->facts(self::ROOT, $ids))
            ->filter(fn ($fact) => in_array($type, $fact['options']['type'], true))->keys();
        $query->whereIntegerInRaw('products.id', $matched);
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

        $type = $this->productType($product);

        return [
            'options' => ['material' => [$material], 'type' => [$type]],
            'card' => array_filter(['Материал' => $label, 'Тип' => self::TYPES[$type] ?? null]),
        ];
    }

    private function productType(Product $product): string
    {
        $values = [];
        foreach (($product->specs ?? []) as $key => $spec) {
            $name = is_array($spec) ? ($spec['key'] ?? '') : $key;
            $value = is_array($spec) ? ($spec['value'] ?? '') : $spec;
            if (is_scalar($value) && in_array(mb_strtolower(trim((string) $name)), ['вид фитинга', 'тип фитинга', 'тип товара'], true)) {
                $values[] = (string) $value;
            }
        }
        if ($values === [] && $product->relationLoaded('allAttributeValues')) {
            foreach ($product->allAttributeValues as $value) {
                if (in_array(mb_strtolower(trim((string) $value->attribute?->name)), ['вид фитинга', 'тип фитинга', 'тип товара'], true)) {
                    $values[] = (string) ($value->option?->name ?? $value->value);
                }
            }
        }

        $types = array_values(array_unique(array_filter(array_map($this->typeOf(...), $values))));
        // Conflicting types need review; generic "фитинг" can use the name.
        if (count($types) > 1) return 'unknown';
        if ($types !== []) return $types[0];

        $name = mb_strtolower(trim((string) $product->name));
        if (preg_match('/^соединител[ьья].*\(американка\)/u', $name)) return 'amerikanki';
        if (preg_match('/^(?:латунный )?запирающий колпачок/u', $name)) return 'zaglushki';
        if (preg_match('/^комплект заглушек/u', $name)) return 'zaglushki';

        return $this->typeOf($name) ?? 'unknown';
    }

    private function typeOf(string $value): ?string
    {
        $value = mb_strtolower(trim($value));
        foreach ([
            'ugolki' => '(?:уголок|уголки|угольник|угол(?: |$)|отвод|колено)',
            'nippeli' => 'ниппел', 'troiniki' => 'тройник', 'mufty' => 'муфт',
            'perehodniki' => '(?:переходник|переход(?: |$)|редукц)',
            'amerikanki' => '(?:американк|соединение разборное)',
            'futorki' => 'футорк', 'zaglushki' => '(?:заглушк|пробк)',
            'udliniteli' => 'удлинител', 'sgony' => 'сгон', 'kontrgaiki' => 'контргайк',
            'bochonki' => '(?:бочон|бочат)', 'rezby' => 'резьб', 'krestoviny' => 'крестовин',
            'shtutsery' => 'штуцер', 'oboymy' => '(?:обойм|ремонтная обойм)',
            'soediniteli' => '(?:соединител|соединение зажимное)',
        ] as $type => $pattern) {
            if (preg_match('/^(?:'.$pattern.')/u', $value)) return $type;
        }

        return null;
    }

    public function links(Collection $categoryIds, ?string $type = null): array
    {
        $counts = [];
        foreach ($this->facts(self::ROOT, $categoryIds) as $fact) {
            if ($type !== null && ! in_array($type, $fact['options']['type'], true)) continue;
            $material = $fact['options']['material'][0];
            $counts[$material] = ($counts[$material] ?? 0) + 1;
        }
        $links = [];
        foreach (self::MATERIALS as $key => $label) {
            if (($counts[$key] ?? 0) > 0) $links[] = [
                'path' => '/'.self::ROOT.'/'.$key.($type !== null ? '?'.http_build_query(['fitting' => ['type' => $type]]) : ''), 'short' => $label,
                'count' => $counts[$key], 'title' => $label.' резьбовые фитинги',
            ];
        }

        return $links;
    }
}

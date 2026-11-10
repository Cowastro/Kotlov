<?php

namespace App\Support\Catalog;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class BoilerCatalog
{
    public const NAMES = [
        'kotly' => 'Котлы отопления',
        'gazovye' => 'Газовые котлы',
        'elektricheskie' => 'Электрические котлы',
        'tverdotoplivnye' => 'Твердотопливные котлы',
        'kotly-na-pelletah' => 'Пеллетные котлы',
    ];

    public const LANDINGS = [
        'gazovye' => [
            'slug' => 'nastennye-dvuhkonturnye',
            'title' => 'Настенные двухконтурные газовые котлы',
            'short' => 'Настенные двухконтурные',
            'description' => 'Настенные газовые котлы для отопления и горячей воды. Сравните мощность отопления, тип камеры сгорания, цены и наличие.',
            'filters' => ['mount' => 'wall', 'circuits' => 'two'],
        ],
        'tverdotoplivnye' => [
            'slug' => 'na-drovah',
            'title' => 'Твердотопливные котлы на дровах',
            'short' => 'Котлы на дровах',
            'description' => 'Котлы, для которых в характеристиках указаны дрова. Сравните мощность, площадь обогрева, материал топки и другие допустимые виды топлива.',
            'filters' => ['fuel' => 'wood'],
        ],
    ];

    public function __construct(private BoilerSpecifications $specifications) {}

    public function search(Builder $query, string $search): void
    {
        foreach (preg_split('/\s+/u', $search, 6, PREG_SPLIT_NO_EMPTY) as $word) {
            $match = '%'.strtr($word, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $query->where(fn (Builder $term) => $term
                ->whereRaw("products.name LIKE ? ESCAPE '!'", [$match])
                ->orWhereRaw("products.sku LIKE ? ESCAPE '!'", [$match])
                ->orWhereRaw("products.slug LIKE ? ESCAPE '!'", [$match])
                ->orWhereHas('brand', fn (Builder $brand) => $brand->whereRaw("name LIKE ? ESCAPE '!'", [$match])));
        }
    }

    public function facets(Builder $query, string $slug, Collection $categoryIds, array $selection, array $fixed): array
    {
        $dimensions = $this->specifications->dimensions($slug);
        $selected = [];
        foreach ($dimensions as $key => [, $options]) {
            if (isset($selection[$key]) && is_scalar($selection[$key]) && isset($options[$selection[$key]])) {
                $selected[$key] = (string) $selection[$key];
            }
        }
        $selected = array_diff_key($selected, $fixed);
        $facts = $this->facts($slug, $categoryIds);

        $matched = [];
        $counts = [];
        foreach ((clone $query)->reorder()->pluck('products.id') as $id) {
            $options = $facts[$id]['options'] ?? [];
            if ($this->unmatched($fixed, $options) !== []) {
                continue;
            }
            $unmatched = $this->unmatched($selected, $options);
            if ($unmatched === []) {
                $matched[] = $id;
            }
            foreach ($options as $key => $values) {
                if ($unmatched === [] || $unmatched === [$key]) {
                    foreach ($values as $value) {
                        $counts[$key][$value] = ($counts[$key][$value] ?? 0) + 1;
                    }
                }
            }
        }

        if ($fixed !== [] || $selected !== []) {
            $query->whereIntegerInRaw('products.id', $matched);
        }

        $facets = [];
        foreach (array_diff_key($dimensions, $fixed) as $key => [$label, $options]) {
            $choices = [];
            foreach ($options as $option => $optionLabel) {
                $count = $counts[$key][$option] ?? 0;
                $active = ($selected[$key] ?? null) === (string) $option;
                if ($count > 0 || $active) {
                    $choices[$option] = ['label' => $optionLabel, 'count' => $count, 'active' => $active];
                }
            }
            if ($choices !== []) {
                $facets[$key] = ['label' => $label, 'options' => $choices];
            }
        }

        return [
            'facets' => $facets,
            'cards' => array_map(fn ($fact) => $fact['card'], $facts),
            'matched' => $matched,
        ];
    }

    public function facts(string $slug, Collection $categoryIds): array
    {
        $dimensions = $this->specifications->dimensions($slug);

        return Cache::remember(
            'boiler-specs-v1:'.$slug.':'.implode('-', $categoryIds->sort()->all()),
            now()->addMinutes(5),
            function () use ($slug, $categoryIds, $dimensions) {
                $facts = [];
                foreach (Product::query()->orderable()->whereIn('category_id', $categoryIds)
                    ->select('id', 'name', 'specs')
                    ->with(['allAttributeValues.attribute', 'allAttributeValues.option'])->lazyById(150) as $product) {
                    $facts[$product->id] = $this->specifications->catalogFacts($product, $slug, $dimensions);
                }

                return $facts;
            },
        );
    }

    private function unmatched(array $selected, array $options): array
    {
        return array_keys(array_filter($selected, fn ($value, $key) => ! in_array($value, $options[$key] ?? [], true), ARRAY_FILTER_USE_BOTH));
    }
}

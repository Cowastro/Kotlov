<?php

namespace App\Support\Catalog;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

abstract class SpecificationCatalog
{
    abstract protected function dimensions(string $slug): array;

    abstract public function productFacts(Product $product, string $slug, array $dimensions): array;

    public function search(Builder $query, string $search): void
    {
        CatalogSearch::apply($query, $search);
    }

    public function facets(Builder $query, string $slug, Collection $categoryIds, array $selection, array $fixed): array
    {
        $dimensions = $this->dimensions($slug);
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
        $dimensions = $this->dimensions($slug);

        // Refresh after the response while the previous facts are still within
        // the existing five-minute freshness limit. Prices/stock stay live.
        return Cache::flexible(
            static::class.':specs-v1:'.$slug.':'.implode('-', $categoryIds->sort()->all()),
            [240, 300],
            function () use ($slug, $categoryIds, $dimensions) {
                $facts = [];
                foreach (Product::query()->orderable()->whereIn('category_id', $categoryIds)
                    ->select('id', 'name', 'slug', 'specs', 'category_id')
                    ->with(['category:id,slug', 'allAttributeValues.attribute', 'allAttributeValues.option'])->lazyById(150) as $product) {
                    $facts[$product->id] = $this->productFacts($product, $slug, $dimensions);
                }

                return $facts;
            },
            ['seconds' => 30],
        );
    }

    private function unmatched(array $selected, array $options): array
    {
        return array_keys(array_filter($selected, fn ($value, $key) => ! in_array($value, $options[$key] ?? [], true), ARRAY_FILTER_USE_BOTH));
    }
}

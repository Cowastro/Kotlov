<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\ProductAttributeValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Filters of the B2B catalog by what the items are: diameter (mono and sandwich) and steel read from the names
 * (ChimneyDiameterQuickFilter, as on the public chimney pages), and the attributes marked «in filter».
 *
 * Attributes of the same name in different categories are one filter, options of the same name one option —
 * as in CatalogController. Within a filter the options add up (OR), filters narrow each other (AND).
 * A count shows how many items an option gives with the other filters as they are.
 */
class B2bCharacteristicFilters
{
    private const MAX_GROUPS = 10;

    private const MAX_OPTIONS = 30;

    /** URL parameters of the facets read from the names. */
    public const NAME_FACETS = ['dm' => 'Диаметр моно, мм', 'dp' => 'Диаметр сэндвич, мм', 'steel' => 'Сталь'];

    public function __construct(private ChimneyDiameterQuickFilter $names) {}

    /** @return array{names: array<string, string>, attr: array<int, list<int>>} */
    public function selected(Request $request): array
    {
        $names = [];
        foreach (array_keys(self::NAME_FACETS) as $key) {
            $value = mb_substr(trim((string) $request->query($key, '')), 0, 20);
            if ($value !== '' && preg_match('/^[0-9A-Za-z\/]+$/', $value) === 1) {
                $names[$key] = $value;
            }
        }
        $attr = [];
        foreach ((array) $request->query('attr', []) as $attributeId => $options) {
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) $options), fn (int $id): bool => $id > 0)));
            if ((int) $attributeId > 0 && $ids !== []) {
                $attr[(int) $attributeId] = array_slice($ids, 0, 30);
            }
        }

        return ['names' => $names, 'attr' => array_slice($attr, 0, 10, true)];
    }

    public function active(array $selected): bool
    {
        return $selected['names'] !== [] || $selected['attr'] !== [];
    }

    /** Narrows the query to the selected characteristics. */
    public function apply(Builder $query, array $selected, ?string $exceptName = null, ?int $exceptAttribute = null): Builder
    {
        foreach ($selected['attr'] as $attributeId => $optionIds) {
            if ($attributeId === $exceptAttribute) {
                continue;
            }
            [$attributeIds, $expanded] = $this->expand($attributeId, $optionIds);
            $query->whereHas('allAttributeValues', fn (Builder $value) => $value->whereIn('attribute_id', $attributeIds)->whereIn('option_id', $expanded));
        }
        foreach ($selected['names'] as $key => $value) {
            if ($key === $exceptName) {
                continue;
            }
            $ids = $this->nameGroups(clone $query, $key)->get($value, collect())->all();
            $query->whereIn('products.id', $ids === [] ? [0] : $ids);
        }

        return $query;
    }

    /**
     * The filters to show for the items of a category, with counts.
     *
     * @return list<array{key: string, param: string, label: string, multiple: bool, options: list<array{value: string, label: string, count: int, selected: bool}>}>
     */
    public function panel(Builder $base, array $selected): array
    {
        $facets = [];
        foreach (self::NAME_FACETS as $key => $label) {
            $groups = $this->nameGroups($this->apply(clone $base, $selected, exceptName: $key), $key);
            if ($groups->count() < 2 && ! isset($selected['names'][$key])) {
                continue;
            }
            $facets[] = ['key' => $key, 'param' => $key, 'label' => $label, 'multiple' => false,
                'options' => $groups->map(fn (Collection $ids, string $value): array => [
                    'value' => (string) $value, 'label' => $key === 'steel' ? 'AISI '.$value : (string) $value,
                    'count' => $ids->count(), 'selected' => ($selected['names'][$key] ?? null) === (string) $value,
                ])->values()->all()];
        }

        return array_merge($facets, $this->attributeFacets($base, $selected));
    }

    private function attributeFacets(Builder $base, array $selected): array
    {
        $counts = fn (Builder $query) => ProductAttributeValue::query()
            ->join('attributes', 'attributes.id', '=', 'product_attribute_values.attribute_id')
            ->where('attributes.in_filter', true)->where('attributes.type', 'select')
            ->whereNotNull('product_attribute_values.option_id')
            ->whereIn('product_attribute_values.product_id', $query->select('products.id'))
            ->groupBy('product_attribute_values.attribute_id', 'product_attribute_values.option_id')
            ->selectRaw('product_attribute_values.attribute_id, product_attribute_values.option_id, count(distinct product_attribute_values.product_id) as cnt')
            ->get();
        $shared = $counts($this->apply(clone $base, $selected));
        // A selected filter counts its options without itself, so the other options stay reachable.
        $own = collect($selected['attr'])->mapWithKeys(fn ($options, int $attributeId) => [$attributeId => $counts($this->apply(clone $base, $selected, exceptAttribute: $attributeId))]);

        $attributeIds = $shared->pluck('attribute_id')->merge($own->flatten(1)->pluck('attribute_id'))->merge(array_keys($selected['attr']))->unique();
        $attributes = Attribute::query()->whereIn('id', $attributeIds)->get(['id', 'name', 'suffix', 'sort_order']);
        $options = AttributeOption::query()->whereIn('attribute_id', $attributeIds)->get(['id', 'attribute_id', 'name', 'sort_order']);
        $selectedOptionNames = collect($selected['attr'])->flatMap(fn ($ids) => $ids)->map(fn (int $id) => $this->normal((string) $options->firstWhere('id', $id)?->name))->filter()->all();

        $facets = [];
        foreach ($attributes->groupBy(fn (Attribute $attribute) => $this->normal($attribute->name)) as $group) {
            $ids = $group->pluck('id')->all();
            $primary = $group->sortBy('id')->first();
            $selectedHere = collect($selected['attr'])->first(fn ($options, int $attributeId) => in_array($attributeId, $ids, true));
            $source = $own->first(fn ($rows, int $attributeId) => in_array($attributeId, $ids, true)) ?? $shared;
            $source = $source->whereIn('attribute_id', $ids);
            $merged = $options->whereIn('attribute_id', $ids)->groupBy(fn (AttributeOption $option) => $this->normal($option->name))
                ->map(function (Collection $same) use ($source, $selectedHere, $selectedOptionNames): ?array {
                    $count = (int) $source->whereIn('option_id', $same->pluck('id'))->sum('cnt');
                    $first = $same->sortBy('id')->first();
                    $isSelected = $selectedHere !== null && in_array($this->normal($first->name), $selectedOptionNames, true);

                    return $count > 0 || $isSelected ? ['value' => (string) $first->id, 'label' => trim($first->name), 'count' => $count, 'selected' => $isSelected, 'sort' => [(int) $first->sort_order, $this->naturalKey($first->name)]] : null;
                })->filter()->sortBy('sort')->values();
            if ($merged->count() < 2 && $selectedHere === null) {
                continue;
            }
            $label = trim($primary->name).(filled($primary->suffix) && ! str_contains($primary->name, (string) $primary->suffix) ? ', '.$primary->suffix : '');
            $facets[] = ['key' => 'attr'.$primary->id, 'param' => 'attr['.$primary->id.']', 'label' => $label, 'multiple' => true,
                'coverage' => $merged->sum('count'), 'sort' => (int) $primary->sort_order,
                'options' => $merged->take(self::MAX_OPTIONS)->map(fn (array $option) => array_diff_key($option, ['sort' => true]))->all()];
        }

        return collect($facets)->sortBy([['sort', 'asc'], ['coverage', 'desc']])->take(self::MAX_GROUPS)
            ->map(fn (array $facet) => array_diff_key($facet, ['coverage' => true, 'sort' => true]))->values()->all();
    }

    /** All attributes of the same name and their options of the same names as the chosen ones. @return array{0: list<int>, 1: list<int>} */
    private function expand(int $attributeId, array $optionIds): array
    {
        $attribute = Attribute::query()->find($attributeId, ['id', 'name']);
        if (! $attribute) {
            return [[0], [0]];
        }
        $attributeIds = Attribute::query()->where('in_filter', true)->where('type', 'select')->get(['id', 'name'])
            ->filter(fn (Attribute $other) => $this->normal($other->name) === $this->normal($attribute->name))->pluck('id')->push($attributeId)->unique()->values()->all();
        $names = AttributeOption::query()->whereIn('id', $optionIds)->pluck('name')->map(fn ($name) => $this->normal((string) $name))->all();
        $expanded = AttributeOption::query()->whereIn('attribute_id', $attributeIds)->get(['id', 'name'])
            ->filter(fn (AttributeOption $option) => in_array($this->normal($option->name), $names, true))->pluck('id')->all();

        return [$attributeIds, $expanded === [] ? [0] : $expanded];
    }

    /** @return Collection<string, Collection<int, int>> */
    private function nameGroups(Builder $query, string $key): Collection
    {
        return match ($key) {
            'dm' => $this->names->groups($query, mono: true),
            'dp' => $this->names->groups($query),
            default => $this->names->steelGroups($query),
        };
    }

    private function normal(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? ''));
    }

    private function naturalKey(string $value): string
    {
        return preg_replace_callback('/\d+(?:[.,]\d+)?/', fn ($m) => str_pad((string) round((float) str_replace(',', '.', $m[0]) * 100), 12, '0', STR_PAD_LEFT), mb_strtolower($value)) ?? $value;
    }
}

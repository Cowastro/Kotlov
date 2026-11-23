<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Support\Collection;

/** Map confirmed specifications to existing filter options; never invent or replace values. */
class CatalogSpecificationFilters
{
    private ?Collection $attributes = null;

    private ?Collection $categories = null;

    public function plan(Product $product, ?array $specs = null): array
    {
        $this->attributes ??= Attribute::where('in_filter', true)->where('type', 'select')->with('options')->get();
        $this->categories ??= Category::all(['id', 'parent_id', 'slug'])->keyBy('id');
        $ids = [];
        $id = $product->category_id;
        while ($id && ! in_array($id, $ids)) {
            $ids[] = $id;
            $id = $this->categories->get($id)?->parent_id;
        }
        $chimney = $this->categories->whereIn('id', $ids)->contains('slug', 'dymohody');
        $specs ??= $product->specs ?? [];
        $rows = $product->relationLoaded('allAttributeValues') ? $product->allAttributeValues
            : ProductAttributeValue::where('product_id', $product->id)->get();
        foreach ($rows->whereNull('option_id') as $row) {
            $attribute = $this->attributes->find($row->attribute_id);
            if ($attribute && filled($row->value)) {
                $specs[] = ['key' => $attribute->name, 'value' => $row->value];
            }
        }
        $existing = $rows->whereNotNull('option_id')->pluck('attribute_id')->all();
        $plan = [];
        foreach ($this->attributes->whereIn('category_id', $ids) as $attribute) {
            if (in_array($attribute->id, $existing)) {
                continue;
            }
            $matches = collect();
            $sourceValues = collect();
            $suffix = $attribute->suffix;
            if (! filled($suffix) && $attribute->options->isNotEmpty()) {
                $units = $attribute->options->map(fn ($option) => preg_match('/\d\s*(мм|см|м|квт|вт|л|бар)$/ui', $option->name, $match) ? mb_strtolower($match[1]) : null);
                if (! $units->contains(null) && $units->unique()->count() === 1) {
                    $suffix = $units->first();
                }
            }
            foreach ($specs as $key => $spec) {
                $name = is_array($spec) ? ($spec['key'] ?? '') : $key;
                $value = is_array($spec) ? ($spec['value'] ?? '') : $spec;
                if (! is_scalar($value) || ! is_string($name)) {
                    continue;
                }
                $target = $this->key($attribute->name);
                $source = $this->key($name);
                if ($chimney) {
                    $source = match ($source) {
                        'элемент дымохода' => 'тип элемента',
                        'толщина стали' => 'толщина металла',
                        'марка стали' => 'тип металла',
                        default => $source,
                    };
                    if ($target === 'тип элемента' && $this->key((string) $value) === 'шибер') {
                        $value = 'Шибер (заслонка)';
                    }
                    if ($target === 'тип металла' && preg_match('/^AISI\s+(304|430)$/i', trim((string) $value), $grade)) {
                        $value = 'Нержавеющая сталь '.$grade[1];
                    }
                }
                if ($source !== $target) {
                    continue;
                }
                $unit = is_array($spec) ? trim((string) ($spec['unit'] ?? '')) : '';
                $value = trim((string) $value);
                if ($unit !== '' && ! str_ends_with(mb_strtolower($value), mb_strtolower($unit))) {
                    $value .= ' '.$unit;
                }
                $sourceValues->push($this->value($value, $suffix));
                $options = $attribute->options->filter(fn ($option) => $this->value($option->name, $suffix) === $this->value($value, $suffix));
                // Several option IDs with the same visible label are ambiguous too.
                if ($options->count() === 1) {
                    $matches->push($options->first()->id);
                }
            }
            if ($sourceValues->unique()->count() === 1 && $matches->unique()->count() === 1) {
                $old = $rows->firstWhere('attribute_id', $attribute->id);
                if ($old && filled($old->value) && $this->value($old->value, $suffix) !== $sourceValues->first()) {
                    continue;
                }
                $plan[] = ['product_id' => $product->id, 'attribute_id' => $attribute->id, 'option_id' => $matches->first(), 'expected_value' => $old?->value];
            }
        }

        return $plan;
    }

    public function sync(Product $product, ?array $specs = null): int
    {
        $rows = $this->plan($product, $specs);
        foreach ($rows as $row) {
            $this->apply($row);
        }

        return count($rows);
    }

    public function apply(array $row): void
    {
        $value = ProductAttributeValue::firstOrCreate(['product_id' => $row['product_id'], 'attribute_id' => $row['attribute_id']], ['option_id' => $row['option_id']]);
        if ($value->option_id === null && $value->value === $row['expected_value']) {
            ProductAttributeValue::whereKey($value->id)->whereNull('option_id')->where('value', $row['expected_value'])->update(['option_id' => $row['option_id']]);
        }
    }

    private function key(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value)));
    }

    private function value(string $value, ?string $suffix): string
    {
        $value = $this->key($value);
        $suffix = $this->key($suffix ?? '');
        if ($suffix !== '' && str_ends_with($value, $suffix)) {
            $value = trim(mb_substr($value, 0, mb_strlen($value) - mb_strlen($suffix)));
        }
        $number = str_replace(',', '.', $value);

        return is_numeric($number) ? (string) (float) $number : $value;
    }
}

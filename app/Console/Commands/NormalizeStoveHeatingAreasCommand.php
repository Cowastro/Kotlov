<?php

namespace App\Console\Commands;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Services\StoveHeatingAreaNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeStoveHeatingAreasCommand extends Command
{
    protected $signature = 'catalog:normalize-stove-heating-areas
        {--apply : Persist canonical heated-area ranges; the default is a dry run}
        {--sample=15 : Number of unresolved or conflicting products to show}';

    protected $description = 'Map explicit stove heated-area facts to the catalog range filter';

    public function handle(StoveHeatingAreaNormalizer $normalizer): int
    {
        $apply = (bool) $this->option('apply');
        $category = Category::query()->where('slug', 'pechi-kaminy')->first();

        if (! $category) {
            $this->error('The pechi-kaminy category was not found.');

            return self::FAILURE;
        }

        $products = Product::query()
            ->where('category_id', $category->id)
            ->where('is_active', true)
            ->where('is_archived', false)
            ->with([
                'allAttributeValues.attribute:id,name,type',
                'allAttributeValues.option:id,name',
            ])
            ->orderBy('id')
            ->get();

        $plans = collect();
        $unresolved = collect();
        $conflicting = collect();
        $stats = [
            'products' => $products->count(),
            'under_50' => 0,
            'from_50_to_100' => 0,
            'over_100' => 0,
            'unchanged' => 0,
            'writes' => 0,
            'conflicting' => 0,
            'unresolved' => 0,
        ];

        foreach ($products as $product) {
            $attributeFacts = $product->allAttributeValues
                ->filter(fn (ProductAttributeValue $row) => $row->attribute && $normalizer->isAreaKey($row->attribute->name))
                ->map(fn (ProductAttributeValue $row) => [
                    'name' => $row->attribute->name,
                    'value' => $row->option?->name ?? $row->value,
                    'type' => $row->attribute->type,
                ])
                ->values()
                ->all();

            $usableAttributeFacts = collect($attributeFacts)
                ->filter(fn (array $fact) => is_scalar($fact['value']) && trim((string) $fact['value']) !== '');
            $rawAttributeFacts = $usableAttributeFacts->where('type', '!=', 'select')->values()->all();
            $selectAttributeFacts = $usableAttributeFacts->where('type', 'select')->values()->all();
            $hasExplicitSource = $rawAttributeFacts !== [] || $this->specsContainArea($product->specs ?: [], $normalizer);
            $range = $hasExplicitSource
                ? $normalizer->detect($product->specs ?: [], $rawAttributeFacts)
                : $normalizer->detect([], $selectAttributeFacts);

            if ($range === null) {
                if ($hasExplicitSource) {
                    $stats['conflicting']++;
                    $conflicting->push([$product->id, $product->name]);
                } else {
                    $stats['unresolved']++;
                    $unresolved->push([$product->id, $product->name]);
                }

                continue;
            }

            $stats[match ($range) {
                StoveHeatingAreaNormalizer::UNDER_50 => 'under_50',
                StoveHeatingAreaNormalizer::FROM_50_TO_100 => 'from_50_to_100',
                default => 'over_100',
            }]++;

            $existingCanonical = $product->allAttributeValues->first(function (ProductAttributeValue $row) use ($normalizer, $range) {
                return $row->attribute
                    && $normalizer->isAreaKey($row->attribute->name)
                    && $row->attribute->type === 'select'
                    && $normalizer->classify((string) ($row->option?->name ?? $row->value)) === $range;
            });

            $existingCanonical ? $stats['unchanged']++ : $stats['writes']++;
            $plans->push(['product' => $product, 'range' => $range]);
        }

        if ($apply) {
            DB::transaction(function () use ($category, $plans, $normalizer) {
                $attribute = $this->ensureAttribute($category);
                $options = $this->ensureOptions($attribute, $normalizer);

                foreach ($plans as $plan) {
                    ProductAttributeValue::query()->updateOrCreate(
                        [
                            'product_id' => $plan['product']->id,
                            'attribute_id' => $attribute->id,
                        ],
                        [
                            'option_id' => $options[$plan['range']]->id,
                            'is_checked' => false,
                            'value' => null,
                        ]
                    );
                }
            });
        }

        $this->table(['Metric', 'Count'], collect($stats)->map(fn ($value, $key) => [$key, $value])->values());
        $this->line('Mode: '.($apply ? 'APPLY' : 'DRY RUN'));
        $this->line('Only explicit heated-area facts were used; no power or room-volume estimates were made.');

        $sample = max(0, (int) $this->option('sample'));
        if ($conflicting->isNotEmpty() && $sample > 0) {
            $this->warn('Conflicting area facts (left unchanged):');
            $this->table(['ID', 'Product'], $conflicting->take($sample)->all());
        }
        if ($unresolved->isNotEmpty() && $sample > 0) {
            $this->warn('Products without an explicit heated area (left unchanged):');
            $this->table(['ID', 'Product'], $unresolved->take($sample)->all());
        }

        return self::SUCCESS;
    }

    /** @param array<int|string, mixed> $specs */
    private function specsContainArea(array $specs, StoveHeatingAreaNormalizer $normalizer): bool
    {
        foreach ($specs as $key => $spec) {
            if (is_array($spec) && isset($spec['key'], $spec['value'])) {
                if ($normalizer->isAreaKey((string) $spec['key']) && is_scalar($spec['value'])) {
                    return true;
                }

                continue;
            }

            if (is_string($key) && $normalizer->isAreaKey($key) && is_scalar($spec)) {
                return true;
            }
        }

        return false;
    }

    private function ensureAttribute(Category $category): Attribute
    {
        $attribute = Attribute::query()
            ->where('category_id', $category->id)
            ->where('type', 'select')
            ->get()
            ->first(fn (Attribute $item) => mb_strtolower(trim($item->name)) === 'площадь отапливаемого помещения');

        if (! $attribute) {
            $attribute = Attribute::query()->create([
                'category_id' => $category->id,
                'group_id' => 0,
                'sort_order' => 50,
                'type' => 'select',
                'name' => 'Площадь отапливаемого помещения',
                'suffix' => 'м²',
                'in_filter' => true,
                'in_sort' => false,
                'in_product' => true,
                'in_brief' => false,
                'is_comparable' => true,
            ]);
        } else {
            $attribute->update([
                'name' => 'Площадь отапливаемого помещения',
                'suffix' => 'м²',
                'in_filter' => true,
                'in_product' => true,
                'is_comparable' => true,
            ]);
        }

        return $attribute;
    }

    /** @return array<string, AttributeOption> */
    private function ensureOptions(Attribute $attribute, StoveHeatingAreaNormalizer $normalizer): array
    {
        $options = $attribute->options()->get();
        $canonical = [];
        $names = [
            StoveHeatingAreaNormalizer::UNDER_50,
            StoveHeatingAreaNormalizer::FROM_50_TO_100,
            StoveHeatingAreaNormalizer::OVER_100,
        ];

        foreach ($names as $index => $name) {
            $option = $options->first(fn (AttributeOption $item) => $normalizer->classify($item->name) === $name);
            $option ??= AttributeOption::query()->create([
                'attribute_id' => $attribute->id,
                'name' => $name,
                'sort_order' => $index + 1,
            ]);
            $option->update(['name' => $name, 'sort_order' => $index + 1]);
            $canonical[$name] = $option;
        }

        foreach ($options as $option) {
            $range = $normalizer->classify($option->name);
            if ($range === null || $option->is($canonical[$range])) {
                continue;
            }

            ProductAttributeValue::query()
                ->where('attribute_id', $attribute->id)
                ->where('option_id', $option->id)
                ->update(['option_id' => $canonical[$range]->id, 'value' => null]);

            if (! ProductAttributeValue::query()->where('option_id', $option->id)->exists()) {
                $option->delete();
            }
        }

        return $canonical;
    }
}

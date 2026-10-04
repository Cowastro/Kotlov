<?php

namespace App\Console\Commands;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Services\StoveHeatingAreaNormalizer;
use App\Services\StoveHeatingAreaFromVolumeConverter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeStoveHeatingAreasCommand extends Command
{
    private const CATEGORY_SLUGS = [
        'pechi-kaminy',
        'pechi',
        'peci-drovianye-otopitelnye',
        'burzhuiki-pechi',
        'dlya-dachi',
    ];

    protected $signature = 'catalog:normalize-stove-heating-areas
        {--apply : Persist canonical heated-area ranges; the default is a dry run}
        {--sample=15 : Number of unresolved or conflicting products to show}
        {--json-unresolved : Print current production evidence for every unresolved product as JSON lines}';

    protected $description = 'Map explicit stove heated-area facts to the catalog range filter';

    public function handle(
        StoveHeatingAreaNormalizer $normalizer,
        StoveHeatingAreaFromVolumeConverter $volumeConverter,
    ): int
    {
        $apply = (bool) $this->option('apply');
        $categories = Category::query()
            ->whereIn('slug', self::CATEGORY_SLUGS)
            ->get()
            ->unique('id')
            ->values();

        if ($categories->isEmpty()) {
            $this->error('No heating-stove categories were found.');

            return self::FAILURE;
        }

        $products = Product::query()
            ->whereIn('category_id', $categories->pluck('id'))
            ->where('is_active', true)
            ->where('is_archived', false)
            ->with([
                'allAttributeValues.attribute:id,name,type',
                'allAttributeValues.option:id,name',
                'brand:id,name',
                'category:id,slug,name',
                'supplierProducts:id,product_id,source_url',
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
            'from_description' => 0,
            'from_structured_volume' => 0,
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

            if ($range === null && ! $hasExplicitSource) {
                $volumeAttributeFacts = $product->allAttributeValues
                    ->filter(fn (ProductAttributeValue $row) => $row->attribute
                        && $row->attribute->type !== 'select'
                        && $volumeConverter->isVolumeKey($row->attribute->name))
                    ->map(fn (ProductAttributeValue $row) => [
                        'name' => $row->attribute->name,
                        'value' => $row->option?->name ?? $row->value,
                    ])
                    ->values()
                    ->all();
                $derivedArea = $volumeConverter->detect($product->specs ?: [], $volumeAttributeFacts);

                if ($derivedArea !== null) {
                    $range = $normalizer->classify((string) $derivedArea);
                    $stats['from_structured_volume']++;
                }
            }

            if ($range === null && ! $hasExplicitSource && $selectAttributeFacts === []) {
                $descriptionRange = $normalizer->detectText(
                    (string) $product->short_description,
                    (string) $product->content,
                );
                if ($descriptionRange !== null) {
                    $range = $descriptionRange;
                    $stats['from_description']++;
                }
            }

            if ($range === null) {
                if ($hasExplicitSource) {
                    $stats['conflicting']++;
                    $conflicting->push($product);
                } else {
                    $stats['unresolved']++;
                    $unresolved->push($product);
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
            DB::transaction(function () use ($categories, $plans, $normalizer) {
                $targetCategoryIds = $plans
                    ->map(fn (array $plan) => (int) $plan['product']->category_id)
                    ->unique();
                $targets = $categories
                    ->whereIn('id', $targetCategoryIds)
                    ->mapWithKeys(function (Category $category) use ($normalizer) {
                        $attribute = $this->ensureAttribute($category);
                        $options = $this->ensureOptions($attribute, $normalizer);

                        return [$category->id => compact('attribute', 'options')];
                    });

                foreach ($plans as $plan) {
                    $target = $targets->get($plan['product']->category_id);
                    if (! $target) {
                        continue;
                    }

                    ProductAttributeValue::query()->updateOrCreate(
                        [
                            'product_id' => $plan['product']->id,
                            'attribute_id' => $target['attribute']->id,
                        ],
                        [
                            'option_id' => $target['options'][$plan['range']]->id,
                            'is_checked' => false,
                            'value' => null,
                        ]
                    );
                }
            });
        }

        $this->table(['Metric', 'Count'], collect($stats)->map(fn ($value, $key) => [$key, $value])->values());
        $this->line('Mode: '.($apply ? 'APPLY' : 'DRY RUN'));
        $this->line('Explicit heated area has priority. Otherwise structured heated volume is divided by the standard 2.5 m ceiling height; power is never converted.');

        $sample = max(0, (int) $this->option('sample'));
        if ($conflicting->isNotEmpty() && $sample > 0) {
            $this->warn('Conflicting area facts (left unchanged):');
            $this->table(
                ['ID', 'Category', 'Product', 'Slug', 'Brand'],
                $conflicting->take($sample)->map(fn (Product $product) => [
                    $product->id,
                    $product->category?->name,
                    $product->name,
                    $product->slug,
                    $product->brand?->name,
                ])->all()
            );
        }
        if ($unresolved->isNotEmpty() && $sample > 0) {
            $this->warn('Products without an explicit heated area (left unchanged):');
            $this->table(
                ['ID', 'Category', 'Product', 'Slug', 'Brand'],
                $unresolved->take($sample)->map(fn (Product $product) => [
                    $product->id,
                    $product->category?->name,
                    $product->name,
                    $product->slug,
                    $product->brand?->name,
                ])->all()
            );
        }

        if ($this->option('json-unresolved')) {
            $this->line('UNRESOLVED_JSON_BEGIN');
            foreach ($unresolved as $product) {
                $this->line(json_encode([
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'brand' => $product->brand?->name,
                    'category' => $product->category?->slug,
                    'source_urls' => $product->supplierProducts->pluck('source_url')->filter()->values()->all(),
                    'specs' => $product->specs,
                    'short_description' => $product->short_description,
                    'content' => $product->content,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
            $this->line('UNRESOLVED_JSON_END');
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

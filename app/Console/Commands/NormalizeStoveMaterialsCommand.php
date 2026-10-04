<?php

namespace App\Console\Commands;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Services\StoveMaterialNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeStoveMaterialsCommand extends Command
{
    private const CATEGORY_SLUGS = [
        'pechi-kaminy',
        'pechi',
        'peci-drovianye-otopitelnye',
        'burzhuiki-pechi',
        'dlya-dachi',
        'topki',
    ];

    protected $signature = 'catalog:normalize-stove-materials
        {--apply : Persist canonical material attributes; the default is a dry run}
        {--sample=15 : Number of unresolved products to show}';

    protected $description = 'Normalize explicit stove and fireplace materials to the Сталь/Чугун filter directory';

    public function handle(StoveMaterialNormalizer $normalizer): int
    {
        $apply = (bool) $this->option('apply');
        $categories = Category::query()
            ->whereIn('slug', self::CATEGORY_SLUGS)
            ->get()
            ->unique('id')
            ->values();

        if ($categories->isEmpty()) {
            $this->error('No stove or fireplace categories were found.');

            return self::FAILURE;
        }

        $products = Product::query()
            ->whereIn('category_id', $categories->pluck('id'))
            ->where('is_active', true)
            ->where('is_archived', false)
            ->with([
                'category:id,slug,name',
                'allAttributeValues.attribute:id,name,type',
                'allAttributeValues.option:id,name',
            ])
            ->orderBy('id')
            ->get();

        $plans = collect();
        $unresolved = collect();
        $ambiguous = collect();
        $stats = [
            'products' => $products->count(),
            'cast_iron' => 0,
            'steel' => 0,
            'unchanged' => 0,
            'writes' => 0,
            'ambiguous' => 0,
            'unresolved' => 0,
        ];

        foreach ($products as $product) {
            $attributeFacts = $product->allAttributeValues
                ->filter(fn (ProductAttributeValue $row) => $row->attribute && $normalizer->isMaterialKey($row->attribute->name))
                ->map(fn (ProductAttributeValue $row) => [
                    'name' => $row->attribute->name,
                    'value' => $row->option?->name ?? $row->value,
                ])
                ->values()
                ->all();

            $material = $normalizer->detect(
                $product->name,
                $product->specs ?: [],
                $attributeFacts,
            );

            if ($material === null) {
                $rawFacts = collect($attributeFacts)
                    ->pluck('value')
                    ->filter()
                    ->map(fn ($value) => $normalizer->classify((string) $value))
                    ->filter()
                    ->unique();

                if ($rawFacts->count() > 1) {
                    $stats['ambiguous']++;
                    $ambiguous->push($this->productRow($product));
                } else {
                    $stats['unresolved']++;
                    $unresolved->push($this->productRow($product));
                }

                continue;
            }

            $material === StoveMaterialNormalizer::CAST_IRON
                ? $stats['cast_iron']++
                : $stats['steel']++;

            $existingCanonical = $product->allAttributeValues->first(function (ProductAttributeValue $row) use ($normalizer, $material) {
                return $row->attribute
                    && $normalizer->isMaterialKey($row->attribute->name)
                    && $row->attribute->type === 'select'
                    && $normalizer->classify((string) ($row->option?->name ?? $row->value)) === $material;
            });

            if ($existingCanonical) {
                $stats['unchanged']++;
            } else {
                $stats['writes']++;
            }

            $plans->push([
                'product' => $product,
                'material' => $material,
            ]);
        }

        if ($apply) {
            DB::transaction(function () use ($categories, $plans, $normalizer) {
                $targets = $categories->mapWithKeys(function (Category $category) use ($normalizer) {
                    $attribute = $this->ensureAttribute($category);
                    $options = $this->ensureOptions($attribute, $normalizer);

                    return [$category->id => compact('attribute', 'options')];
                });

                foreach ($plans as $plan) {
                    /** @var Product $product */
                    $product = $plan['product'];
                    $target = $targets->get($product->category_id);
                    $option = $target['options'][$plan['material']];

                    ProductAttributeValue::query()->updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'attribute_id' => $target['attribute']->id,
                        ],
                        [
                            'option_id' => $option->id,
                            'is_checked' => false,
                            'value' => null,
                        ]
                    );
                }
            });
        }

        $this->table(['Metric', 'Count'], collect($stats)->map(fn ($value, $key) => [$key, $value])->values());
        $this->line('Mode: '.($apply ? 'APPLY' : 'DRY RUN'));
        $this->line('Only explicit material facts were used; brand-only inference is disabled.');

        $sample = max(0, (int) $this->option('sample'));
        if ($ambiguous->isNotEmpty() && $sample > 0) {
            $this->warn('Conflicting material facts (left unchanged):');
            $this->table(['ID', 'Category', 'Product'], $ambiguous->take($sample)->all());
        }
        if ($unresolved->isNotEmpty() && $sample > 0) {
            $this->warn('Products without an explicit material (left unchanged):');
            $this->table(['ID', 'Category', 'Product'], $unresolved->take($sample)->all());
        }

        return self::SUCCESS;
    }

    private function ensureAttribute(Category $category): Attribute
    {
        $attribute = Attribute::query()
            ->where('category_id', $category->id)
            ->where('type', 'select')
            ->get()
            ->first(fn (Attribute $item) => mb_strtolower(trim($item->name)) === 'материал');

        if (! $attribute) {
            $attribute = Attribute::query()->create([
                'category_id' => $category->id,
                'group_id' => 0,
                'sort_order' => 40,
                'type' => 'select',
                'name' => 'Материал',
                'suffix' => null,
                'in_filter' => true,
                'in_sort' => false,
                'in_product' => true,
                'in_brief' => false,
                'is_comparable' => true,
            ]);
        } else {
            $attribute->update([
                'name' => 'Материал',
                'in_filter' => true,
                'in_product' => true,
                'is_comparable' => true,
            ]);
        }

        return $attribute;
    }

    /** @return array<string, AttributeOption> */
    private function ensureOptions(Attribute $attribute, StoveMaterialNormalizer $normalizer): array
    {
        $options = $attribute->options()->get();
        $canonical = [];

        foreach ([StoveMaterialNormalizer::CAST_IRON, StoveMaterialNormalizer::STEEL] as $index => $name) {
            $option = $options->first(fn (AttributeOption $item) => mb_strtolower(trim($item->name)) === mb_strtolower($name));
            $option ??= AttributeOption::query()->create([
                'attribute_id' => $attribute->id,
                'name' => $name,
                'sort_order' => $index + 1,
            ]);
            $option->update(['name' => $name, 'sort_order' => $index + 1]);
            $canonical[$name] = $option;
        }

        foreach ($options as $option) {
            $material = $normalizer->classify($option->name);
            if ($material === null || $option->is($canonical[$material])) {
                continue;
            }

            ProductAttributeValue::query()
                ->where('attribute_id', $attribute->id)
                ->where('option_id', $option->id)
                ->update(['option_id' => $canonical[$material]->id, 'value' => null]);

            if (! ProductAttributeValue::query()->where('option_id', $option->id)->exists()) {
                $option->delete();
            }
        }

        return $canonical;
    }

    private function productRow(Product $product): array
    {
        return [$product->id, $product->category?->name ?? '—', $product->name];
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Services\StoveHeatingVolumeNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeStoveHeatingVolumesCommand extends Command
{
    private const CATEGORY_SLUGS = [
        'pechi-kaminy',
        'pechi',
        'peci-drovianye-otopitelnye',
        'burzhuiki-pechi',
        'dlya-dachi',
    ];

    protected $signature = 'catalog:normalize-stove-heating-volumes
        {--apply : Persist canonical room-volume ranges; the default is a dry run}
        {--sample=15 : Number of unresolved products to show}';

    protected $description = 'Map explicit stove room-volume facts to a separate m³ catalog filter';

    public function handle(StoveHeatingVolumeNormalizer $normalizer): int
    {
        $apply = (bool) $this->option('apply');
        $categories = Category::query()->whereIn('slug', self::CATEGORY_SLUGS)->get()->unique('id')->values();

        if ($categories->isEmpty()) {
            $this->error('No heating-stove categories were found.');
            return self::FAILURE;
        }

        $products = Product::query()
            ->whereIn('category_id', $categories->pluck('id'))
            ->where('is_active', true)
            ->where('is_archived', false)
            ->with(['allAttributeValues.attribute:id,name,type', 'allAttributeValues.option:id,name', 'category:id,slug,name'])
            ->orderBy('id')
            ->get();

        $plans = collect();
        $unresolved = collect();
        $stats = [
            'products' => $products->count(),
            'up_to_100' => 0,
            'from_101_to_200' => 0,
            'over_200' => 0,
            'unchanged' => 0,
            'writes' => 0,
            'unresolved' => 0,
        ];

        foreach ($products as $product) {
            $range = $normalizer->detect($product->specs ?: []);
            if ($range === null) {
                $stats['unresolved']++;
                $unresolved->push($product);
                continue;
            }

            $stats[match ($range) {
                StoveHeatingVolumeNormalizer::UP_TO_100 => 'up_to_100',
                StoveHeatingVolumeNormalizer::FROM_101_TO_200 => 'from_101_to_200',
                default => 'over_200',
            }]++;

            $existing = $product->allAttributeValues->first(function (ProductAttributeValue $row) use ($normalizer, $range) {
                return $row->attribute
                    && $normalizer->isVolumeKey($row->attribute->name)
                    && $row->attribute->type === 'select'
                    && $normalizer->classify((string) ($row->option?->name ?? $row->value)) === $range;
            });

            $existing ? $stats['unchanged']++ : $stats['writes']++;
            $plans->push(compact('product', 'range'));
        }

        if ($apply) {
            DB::transaction(function () use ($categories, $plans, $normalizer) {
                $targets = $categories
                    ->whereIn('id', $plans->pluck('product.category_id')->unique())
                    ->mapWithKeys(function (Category $category) use ($normalizer) {
                        $attribute = $this->ensureAttribute($category);
                        return [$category->id => [
                            'attribute' => $attribute,
                            'options' => $this->ensureOptions($attribute, $normalizer),
                        ]];
                    });

                foreach ($plans as $plan) {
                    $target = $targets->get($plan['product']->category_id);
                    if (! $target) {
                        continue;
                    }

                    ProductAttributeValue::query()->updateOrCreate(
                        ['product_id' => $plan['product']->id, 'attribute_id' => $target['attribute']->id],
                        ['option_id' => $target['options'][$plan['range']]->id, 'is_checked' => false, 'value' => null]
                    );
                }
            });
        }

        $this->table(['Metric', 'Count'], collect($stats)->map(fn ($value, $key) => [$key, $value])->values());
        $this->line('Mode: '.($apply ? 'APPLY' : 'DRY RUN'));
        $this->line('Only explicit room-volume facts were used; m³ was not converted to m².');

        $sample = max(0, (int) $this->option('sample'));
        if ($sample > 0 && $unresolved->isNotEmpty()) {
            $this->warn('Products without an explicit room volume (left unchanged):');
            $this->table(
                ['ID', 'Category', 'Product', 'Slug'],
                $unresolved->take($sample)->map(fn (Product $product) => [
                    $product->id,
                    $product->category?->name,
                    $product->name,
                    $product->slug,
                ])->all()
            );
        }

        return self::SUCCESS;
    }

    private function ensureAttribute(Category $category): Attribute
    {
        $attribute = Attribute::query()
            ->where('category_id', $category->id)
            ->where('type', 'select')
            ->get()
            ->first(fn (Attribute $item) => app(StoveHeatingVolumeNormalizer::class)->isVolumeKey($item->name));

        $values = [
            'name' => 'Объём отапливаемого помещения',
            'suffix' => 'м³',
            'in_filter' => true,
            'in_product' => true,
            'is_comparable' => true,
        ];

        if ($attribute) {
            $attribute->update($values);
            return $attribute;
        }

        return Attribute::query()->create($values + [
            'category_id' => $category->id,
            'group_id' => 0,
            'sort_order' => 51,
            'type' => 'select',
            'in_sort' => false,
            'in_brief' => false,
        ]);
    }

    /** @return array<string, AttributeOption> */
    private function ensureOptions(Attribute $attribute, StoveHeatingVolumeNormalizer $normalizer): array
    {
        $names = [
            StoveHeatingVolumeNormalizer::UP_TO_100,
            StoveHeatingVolumeNormalizer::FROM_101_TO_200,
            StoveHeatingVolumeNormalizer::OVER_200,
        ];
        $options = $attribute->options()->get();
        $canonical = [];

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

        return $canonical;
    }
}

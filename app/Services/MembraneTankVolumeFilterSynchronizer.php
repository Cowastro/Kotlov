<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Support\Facades\DB;

class MembraneTankVolumeFilterSynchronizer
{
    public function __construct(private readonly MembraneTankVolumeNormalizer $normalizer) {}

    public function sync(bool $apply): array
    {
        $category = Category::query()->where('slug', 'membrannye-baki')->first();

        if (! $category) {
            return ['category_found' => 0, 'products' => 0, 'resolved' => 0, 'linked' => 0, 'options' => 0];
        }

        $attribute = Attribute::query()
            ->where('category_id', $category->id)
            ->where('type', 'select')
            ->whereIn('name', ['Объём', 'Объем', 'Литраж'])
            ->orderByDesc('in_filter')
            ->orderBy('id')
            ->first();

        $products = Product::query()
            ->where('category_id', $category->id)
            ->with(['allAttributeValues.attribute:id,name,type', 'allAttributeValues.option:id,name'])
            ->orderBy('id')
            ->get();

        $resolved = $products->mapWithKeys(function (Product $product): array {
            $attributes = $product->allAttributeValues
                ->filter(fn (ProductAttributeValue $row) => $row->attribute !== null)
                ->mapWithKeys(fn (ProductAttributeValue $row) => [
                    $row->attribute->name => $row->option?->name ?? $row->value,
                ])
                ->all();

            $volume = $this->normalizer->extract($product->name, $product->specs ?: [], $attributes);

            return $volume === null ? [] : [$product->id => $volume];
        });

        $volumes = $resolved->values()->unique()->sort()->values();
        $linked = 0;

        if ($apply && $volumes->isNotEmpty()) {
            DB::transaction(function () use ($category, &$attribute, $volumes, $resolved, &$linked): void {
                if (! $attribute) {
                    $attribute = Attribute::query()->create([
                        'category_id' => $category->id,
                        'group_id' => 0,
                        'sort_order' => 5,
                        'type' => 'select',
                        'name' => 'Объём',
                        'suffix' => 'л',
                        'in_filter' => true,
                        'in_sort' => false,
                        'in_product' => false,
                        'in_brief' => false,
                        'is_comparable' => false,
                    ]);
                } else {
                    $attribute->update([
                        'name' => 'Объём',
                        'suffix' => 'л',
                        'in_filter' => true,
                        'sort_order' => min((int) $attribute->sort_order, 5),
                    ]);
                }

                $options = [];
                foreach ($volumes as $index => $volume) {
                    $option = AttributeOption::query()->updateOrCreate(
                        ['attribute_id' => $attribute->id, 'name' => $volume.' л'],
                        ['sort_order' => $index]
                    );
                    $options[(int) $volume] = $option->id;
                }

                foreach ($resolved as $productId => $volume) {
                    ProductAttributeValue::query()->updateOrCreate(
                        ['product_id' => $productId, 'attribute_id' => $attribute->id],
                        ['option_id' => $options[(int) $volume], 'is_checked' => null, 'value' => null]
                    );
                    $linked++;
                }
            });
        }

        return [
            'category_found' => 1,
            'products' => $products->count(),
            'resolved' => $resolved->count(),
            'linked' => $linked,
            'options' => $volumes->count(),
        ];
    }
}

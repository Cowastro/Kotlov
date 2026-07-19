<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CATEGORY_SLUG = 'teplovyie-nasosyi';
    private const ATTRIBUTE_NAME = 'Мощность';

    private const RANGES = [
        'до 5 кВт' => [0, 5],
        '6–10 кВт' => [5.0001, 10],
        '11–15 кВт' => [10.0001, 15],
        '16–20 кВт' => [15.0001, 20],
        '21–25 кВт' => [20.0001, 25],
        '26–30 кВт' => [25.0001, 30],
    ];

    private const MANUAL_POWER_BY_SLUG = [
        'teplovoy-nasos-hotta-flm80-r32-30-kvt' => 30.0,
        'teplovoy-nasos-hotta-flm60-r32-23-kvt' => 23.0,
    ];

    public function up(): void
    {
        $now = now();
        $categoryId = (int) DB::table('categories')->where('slug', self::CATEGORY_SLUG)->value('id');

        if (! $categoryId) {
            return;
        }

        DB::table('attributes')->updateOrInsert(
            [
                'category_id' => $categoryId,
                'name' => self::ATTRIBUTE_NAME,
                'type' => 'select',
            ],
            [
                'group_id' => 0,
                'sort_order' => -10,
                'suffix' => null,
                'in_filter' => true,
                'in_sort' => false,
                'in_product' => true,
                'in_brief' => true,
                'is_comparable' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        $attributeId = (int) DB::table('attributes')
            ->where('category_id', $categoryId)
            ->where('name', self::ATTRIBUTE_NAME)
            ->where('type', 'select')
            ->value('id');

        $optionIds = [];
        $sort = 10;

        foreach (array_keys(self::RANGES) as $label) {
            DB::table('attribute_options')->updateOrInsert(
                [
                    'attribute_id' => $attributeId,
                    'name' => $label,
                ],
                [
                    'sort_order' => $sort,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            $optionIds[$label] = (int) DB::table('attribute_options')
                ->where('attribute_id', $attributeId)
                ->where('name', $label)
                ->value('id');

            $sort += 10;
        }

        $products = DB::table('products')
            ->where('category_id', $categoryId)
            ->where('is_active', true)
            ->where('is_archived', false)
            ->where('price', '>', 0)
            ->get(['id', 'name', 'slug']);

        foreach ($products as $product) {
            $power = $this->powerForProduct((int) $product->id, (string) $product->slug, (string) $product->name);
            $rangeLabel = $this->rangeLabel($power);

            if (! $rangeLabel || ! isset($optionIds[$rangeLabel])) {
                continue;
            }

            DB::table('product_attribute_values')->updateOrInsert(
                [
                    'product_id' => (int) $product->id,
                    'attribute_id' => $attributeId,
                ],
                [
                    'option_id' => $optionIds[$rangeLabel],
                    'value' => null,
                    'is_checked' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        $categoryId = (int) DB::table('categories')->where('slug', self::CATEGORY_SLUG)->value('id');
        $attributeId = (int) DB::table('attributes')
            ->where('category_id', $categoryId)
            ->where('name', self::ATTRIBUTE_NAME)
            ->where('type', 'select')
            ->value('id');

        if (! $attributeId) {
            return;
        }

        DB::table('product_attribute_values')->where('attribute_id', $attributeId)->delete();
        DB::table('attribute_options')->where('attribute_id', $attributeId)->delete();
        DB::table('attributes')->where('id', $attributeId)->delete();
    }

    private function powerForProduct(int $productId, string $slug, string $name): ?float
    {
        if (isset(self::MANUAL_POWER_BY_SLUG[$slug])) {
            return self::MANUAL_POWER_BY_SLUG[$slug];
        }

        $value = DB::table('product_attribute_values as pav')
            ->join('attributes as a', 'a.id', '=', 'pav.attribute_id')
            ->where('pav.product_id', $productId)
            ->where('a.name', 'Мощность теплового насоса')
            ->value('pav.value');

        $power = $this->parsePower((string) $value);

        if ($power !== null) {
            return $power;
        }

        return $this->parsePower($name);
    }

    private function parsePower(string $value): ?float
    {
        $normalized = str_replace(',', '.', $value);

        if (! preg_match_all('/\d+(?:\.\d+)?/u', $normalized, $matches) || empty($matches[0])) {
            return null;
        }

        $numbers = array_map('floatval', $matches[0]);

        return max($numbers);
    }

    private function rangeLabel(?float $power): ?string
    {
        if ($power === null) {
            return null;
        }

        foreach (self::RANGES as $label => [$min, $max]) {
            if ($power >= $min && $power <= $max) {
                return $label;
            }
        }

        return null;
    }
};

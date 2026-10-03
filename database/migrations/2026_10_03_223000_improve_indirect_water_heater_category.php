<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $category = DB::table('categories')->where('slug', 'kosvennye')->first();

        if (! $category) {
            return;
        }

        DB::table('categories')
            ->where('id', $category->id)
            ->update([
                'h1' => 'Бойлеры косвенного нагрева',
                'meta_title' => 'Бойлеры косвенного нагрева — купить в %city% | KOTLOV',
                'meta_description' => 'Бойлеры косвенного нагрева для дома: модели разного объёма, настенные и напольные. Цены, доставка по Беларуси, гарантия и помощь с подбором.',
                'updated_at' => now(),
            ]);

        $valueAttribute = DB::table('attributes')
            ->where('category_id', $category->id)
            ->where('type', 'value')
            ->whereIn('name', ['Объем', 'Объём'])
            ->orderByDesc('id')
            ->first();

        if (! $valueAttribute) {
            return;
        }

        $selectAttribute = DB::table('attributes')
            ->where('category_id', $category->id)
            ->where('type', 'select')
            ->whereIn('name', ['Объем', 'Объём'])
            ->orderByDesc('id')
            ->first();

        if (! $selectAttribute) {
            $selectAttributeId = DB::table('attributes')->insertGetId([
                'category_id' => $category->id,
                'group_id' => $valueAttribute->group_id ?? 0,
                'sort_order' => 10,
                'type' => 'select',
                'name' => 'Объём',
                'suffix' => null,
                'in_filter' => true,
                'in_sort' => false,
                'in_product' => false,
                'in_brief' => false,
                'is_comparable' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $selectAttributeId = $selectAttribute->id;

            DB::table('attributes')
                ->where('id', $selectAttributeId)
                ->update([
                    'name' => 'Объём',
                    'in_filter' => true,
                    'updated_at' => now(),
                ]);
        }

        $ranges = [
            ['label' => 'до 100 л', 'max' => 100],
            ['label' => '101–150 л', 'max' => 150],
            ['label' => '151–200 л', 'max' => 200],
            ['label' => '201–250 л', 'max' => 250],
            ['label' => 'свыше 250 л', 'max' => null],
        ];

        $existingOptions = DB::table('attribute_options')
            ->where('attribute_id', $selectAttributeId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values();

        $optionIds = [];
        foreach ($ranges as $index => $range) {
            $existing = $existingOptions->get($index);

            if ($existing) {
                DB::table('attribute_options')->where('id', $existing->id)->update([
                    'name' => $range['label'],
                    'sort_order' => $index,
                    'updated_at' => now(),
                ]);
                $optionIds[$index] = $existing->id;
            } else {
                $optionIds[$index] = DB::table('attribute_options')->insertGetId([
                    'attribute_id' => $selectAttributeId,
                    'name' => $range['label'],
                    'sort_order' => $index,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('product_attribute_values')
            ->where('attribute_id', $valueAttribute->id)
            ->whereNotNull('value')
            ->orderBy('id')
            ->select(['product_id', 'value'])
            ->each(function (object $attributeValue) use ($selectAttributeId, $optionIds) {
                $normalized = str_replace(',', '.', (string) $attributeValue->value);

                if (! preg_match('/\d+(?:\.\d+)?/', $normalized, $matches)) {
                    return;
                }

                $volume = (float) $matches[0];
                $rangeIndex = match (true) {
                    $volume <= 100 => 0,
                    $volume <= 150 => 1,
                    $volume <= 200 => 2,
                    $volume <= 250 => 3,
                    default => 4,
                };

                DB::table('product_attribute_values')->updateOrInsert(
                    [
                        'product_id' => $attributeValue->product_id,
                        'attribute_id' => $selectAttributeId,
                    ],
                    [
                        'option_id' => $optionIds[$rangeIndex],
                        'is_checked' => null,
                        'value' => null,
                        'updated_at' => now(),
                    ]
                );
            });
    }

    public function down(): void
    {
        DB::table('categories')
            ->where('slug', 'kosvennye')
            ->update([
                'h1' => 'Косвенные водонагреватели (Бойлеры)',
                'meta_title' => 'Косвенные бойлеры. Водонагреватели косвенного нагрева - каталог с ценами в %city% на Kotlov.by.',
                'meta_description' => 'Каталог косвенных водонагревателей. Купить косвенный бойлер стало как никогда просто. Профессиональные консультации по выбору бойлера косвенного нагрева, справедливые цены, помощь в установке а также доставка косвенных проточных водонагревателей  в %city% и в другие города Беларуси.',
                'updated_at' => now(),
            ]);
    }
};

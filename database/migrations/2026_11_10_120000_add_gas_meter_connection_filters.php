<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const FILTERS = ['Направление потока', 'Расстояние между патрубками'];

    public function up(): void
    {
        $categoryId = DB::table('categories')->where('slug', 'schetchiki-gaza')->value('id');
        if (! $categoryId) {
            return;
        }

        $now = now();
        foreach (self::FILTERS as $name) {
            $key = ['category_id' => $categoryId, 'name' => $name, 'type' => 'select'];
            $attributes = DB::table('attributes');
            $id = (clone $attributes)->where($key)->value('id');
            if ($id) {
                $attributes->where('id', $id)->update(['in_filter' => true, 'updated_at' => $now]);
                $attributeIds[$name] = $id;
            } else {
                $attributeIds[$name] = $attributes->insertGetId($key + [
                    'in_filter' => true, 'in_product' => false,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        $optionIds = [];
        foreach (DB::table('products')->where('category_id', $categoryId)->select('id', 'slug', 'name')->cursor() as $product) {
            $values = [];
            if (preg_match('/\b(левый|правый)\b/ui', $product->name, $direction)) {
                $values[self::FILTERS[0]] = mb_convert_case($direction[1], MB_CASE_TITLE, 'UTF-8');
            }
            if (preg_match('/\bL\s*=\s*(\d{2,3})\b/ui', $product->name, $length)) {
                $values[self::FILTERS[1]] = $length[1].' мм';
            }
            if ($product->slug === 'schetchik-gaza-bytovoj-rusbelgaz-rbg-u-g16-a') {
                $values[self::FILTERS[1]] = 'До 177 мм';
            }

            foreach ($values as $name => $value) {
                $attributeId = $attributeIds[$name];
                $optionIds[$name][$value] ??= DB::table('attribute_options')
                    ->where('attribute_id', $attributeId)->where('name', $value)->value('id')
                    ?? DB::table('attribute_options')->insertGetId([
                        'attribute_id' => $attributeId, 'name' => $value,
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                DB::table('product_attribute_values')->insertOrIgnore([
                    'product_id' => $product->id, 'attribute_id' => $attributeId,
                    'option_id' => $optionIds[$name][$value],
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Keep verified catalogue data and any later edits by store managers.
    }
};

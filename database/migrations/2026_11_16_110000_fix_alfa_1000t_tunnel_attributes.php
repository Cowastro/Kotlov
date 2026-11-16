<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The card's characteristics table shows attribute values, not the specs JSON. For both АЛЬФА 1000 T cards:
 * «Вид топки» = двусторонняя (tunnel), «Встроенная шиберная заслонка» = да, and the junk «Главная» row goes.
 * Select options are matched by name (created when missing), so filters keep working.
 */
return new class extends Migration
{
    private const CARDS = [
        'kaminnaia-topka-ekokamin-alfa-1000-t-tunnelnaia-podovoe-gorenie',
        'kaminnaia-topka-ekokamin-alfa-1000-tb-tunnelnaia-podovoe-gorenie-cernyi-samot',
    ];

    public function up(): void
    {
        $now = now();
        foreach (self::CARDS as $slug) {
            $product = DB::table('products')->where('slug', $slug)->first(['id', 'category_id']);
            if (! $product) {
                continue;
            }
            $attribute = fn (string $name) => DB::table('attributes')->where('category_id', $product->category_id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first(['id', 'type']);

            if ($main = $attribute('Главная')) {
                DB::table('product_attribute_values')->where('product_id', $product->id)->where('attribute_id', $main->id)->delete();
            }
            foreach (['Вид топки' => 'двусторонняя', 'Встроенная шиберная заслонка' => true] as $name => $value) {
                $attr = $attribute($name);
                if (! $attr) {
                    continue;
                }
                $row = ['option_id' => null, 'is_checked' => null, 'value' => null, 'updated_at' => $now];
                if ($attr->type === 'check') {
                    $row['is_checked'] = (bool) $value;
                } elseif ($attr->type === 'select') {
                    $optionId = DB::table('attribute_options')->where('attribute_id', $attr->id)
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $value)])->value('id');
                    if (! $optionId) {
                        $optionId = DB::table('attribute_options')->insertGetId(['attribute_id' => $attr->id, 'name' => (string) $value,
                            'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]);
                    }
                    $row['option_id'] = $optionId;
                } else {
                    $row['value'] = $value === true ? 'Да' : (string) $value;
                }
                $exists = DB::table('product_attribute_values')->where('product_id', $product->id)->where('attribute_id', $attr->id)->exists();
                $exists
                    ? DB::table('product_attribute_values')->where('product_id', $product->id)->where('attribute_id', $attr->id)->update($row)
                    : DB::table('product_attribute_values')->insert($row + ['product_id' => $product->id, 'attribute_id' => $attr->id, 'created_at' => $now]);
            }
        }
        Cache::flush();
    }

    public function down(): void {}
};

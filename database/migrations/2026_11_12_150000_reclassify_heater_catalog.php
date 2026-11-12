<?php

use App\Support\Catalog\HeaterCategoryClassifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    public function up(): void
    {
        $heater = DB::table('categories')->where('slug', 'obogrevateli')->value('id');
        if (! $heater) return;
        DB::transaction(function () use ($heater): void {
            $electric = DB::table('categories')->where('slug', 'elektropitanie-i-zashchita')->value('id');
            foreach ([
                'komplektuyushchie-dlya-obogrevateley' => ['Комплектующие для обогревателей', $heater],
                'zaryadnye-stantsii-dlya-elektromobiley' => ['Зарядные станции для электромобилей', $electric],
            ] as $slug => [$name, $parent]) {
                if ($parent && ! DB::table('categories')->where('slug', $slug)->exists()) {
                    DB::table('categories')->insert(['slug' => $slug, 'name' => $name, 'h1' => $name,
                        'parent_id' => $parent, 'type' => 'child', 'is_active' => true, 'sort_order' => 90,
                        'created_at' => now(), 'updated_at' => now()]);
                }
            }
            $destinations = DB::table('categories')->where('is_active', true)->pluck('id', 'slug');
            // Restrict repairs to the heater branch; unrelated products are untouched.
            $ids = [$heater];
            for ($i = 0; $i < count($ids); $i++) {
                foreach (DB::table('categories')->where('parent_id', $ids[$i])->pluck('id') as $id) {
                    if (! in_array($id, $ids)) $ids[] = $id;
                }
            }
            foreach (DB::table('products')->whereIn('category_id', $ids)->get(['id', 'name', 'category_id']) as $product) {
                $slug = HeaterCategoryClassifier::slug($product->name);
                if ($slug && isset($destinations[$slug]) && $destinations[$slug] != $product->category_id) {
                    DB::table('products')->where('id', $product->id)->update(['category_id' => $destinations[$slug], 'updated_at' => now()]);
                }
            }
        });
        Cache::flush();
    }

    public function down(): void
    {
        // Preserve reviewed classifications and product URLs; no destructive rollback.
    }
};

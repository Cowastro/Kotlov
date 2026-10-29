<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Boiler spare parts (pellet hoppers and augers, blowers, glow plugs, draught regulators, fuses)
 * lay in the root of «Комплектующие»; they get a category under «Котлы и горелки».
 */
return new class extends Migration
{
    public function up(): void
    {
        $kotly = DB::table('categories')->where('slug', 'kotly')->value('id');
        if (! $kotly || DB::table('categories')->where('slug', 'zapchasti-dlya-kotlov')->exists()) {
            return;
        }
        $name = 'Запчасти и комплектующие для котлов';
        DB::table('categories')->insert([
            'parent_id' => $kotly, 'name' => $name, 'slug' => 'zapchasti-dlya-kotlov', 'h1' => $name, 'type' => 'child', 'sort_order' => 50, 'is_active' => true,
            'meta_title' => $name.' купить в %city% — цены | KOTLOV',
            'meta_description' => $name.' в наличии и под заказ: цены в BYN, характеристики, доставка по Беларуси.',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('categories')->where('slug', 'zapchasti-dlya-kotlov')->whereNotExists(
            fn ($q) => $q->from('products')->whereColumn('products.category_id', 'categories.id')
        )->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $parentId = DB::table('categories')
            ->where('slug', 'komplektuyushhie-dlya-otopleniya')
            ->value('id');

        if (! $parentId) {
            return;
        }

        DB::table('categories')->updateOrInsert(
            ['slug' => 'gazovye-shlangi'],
            [
                'parent_id' => $parentId,
                'name' => 'Газовые шланги',
                'h1' => 'Газовые шланги для подключения оборудования',
                'type' => 'main-child',
                'sort_order' => 155,
                'is_active' => true,
                'meta_title' => 'Газовые шланги — купить в Беларуси | KOTLOV',
                'meta_description' => 'Газовые шланги для подключения плит, котлов и другого оборудования. Цены, характеристики и доставка по Беларуси.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $categoryId = DB::table('categories')->where('slug', 'gazovye-shlangi')->value('id');

        DB::table('products')
            ->where('sku', 'KOTLOV-022607')
            ->update(['category_id' => $categoryId, 'updated_at' => now()]);
    }

    public function down(): void
    {
        $categoryId = DB::table('categories')->where('slug', 'gazovye-shlangi')->value('id');
        $fallbackId = DB::table('categories')->where('slug', 'schetchiki-gaza')->value('id');

        if ($categoryId && $fallbackId) {
            DB::table('products')
                ->where('sku', 'KOTLOV-022607')
                ->where('category_id', $categoryId)
                ->update(['category_id' => $fallbackId, 'updated_at' => now()]);
        }

        if ($categoryId && ! DB::table('products')->where('category_id', $categoryId)->exists()) {
            DB::table('categories')->where('id', $categoryId)->delete();
        }
    }
};

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
            ['slug' => 'gibkaya-podvodka-dlya-vody'],
            [
                'parent_id' => $parentId,
                'name' => 'Гибкая подводка для воды',
                'h1' => 'Гибкая подводка для воды',
                'type' => 'main-child',
                'sort_order' => 156,
                'is_active' => true,
                'meta_title' => 'Гибкая подводка для воды — купить в Беларуси | KOTLOV',
                'meta_description' => 'Гибкие водопроводные шланги для подключения смесителей и сантехнического оборудования. Цены, характеристики и доставка по Беларуси.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        $categoryId = DB::table('categories')
            ->where('slug', 'gibkaya-podvodka-dlya-vody')
            ->value('id');

        if ($categoryId && ! DB::table('products')->where('category_id', $categoryId)->exists()) {
            DB::table('categories')->where('id', $categoryId)->delete();
        }
    }
};

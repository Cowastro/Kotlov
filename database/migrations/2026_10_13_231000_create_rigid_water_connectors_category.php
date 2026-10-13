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
            ['slug' => 'zhestkaya-podvodka-dlya-vody'],
            [
                'parent_id' => $parentId,
                'name' => 'Жёсткая подводка для воды',
                'h1' => 'Жёсткая подводка для воды',
                'type' => 'main-child',
                'sort_order' => 157,
                'is_active' => true,
                'meta_title' => 'Жёсткая подводка для воды — купить в Беларуси | KOTLOV',
                'meta_description' => 'Хромированные медные трубки и комплектующие для жёсткого подключения смесителей и сантехнического оборудования. Цены и доставка по Беларуси.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        $categoryId = DB::table('categories')
            ->where('slug', 'zhestkaya-podvodka-dlya-vody')
            ->value('id');

        if ($categoryId && ! DB::table('products')->where('category_id', $categoryId)->exists()) {
            DB::table('categories')->where('id', $categoryId)->delete();
        }
    }
};

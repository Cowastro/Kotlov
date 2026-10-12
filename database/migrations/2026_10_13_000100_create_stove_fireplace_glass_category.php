<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $parentId = DB::table('categories')->where('slug', 'aksessuary-dlya-bani')->value('id');

        if (! $parentId) {
            return;
        }

        DB::table('categories')->updateOrInsert(
            ['slug' => 'stekla-dlya-pechey-i-kaminov'],
            [
                'parent_id' => $parentId,
                'name' => 'Стекло для печей и каминов',
                'h1' => 'Огнеупорное стекло для печей и каминов',
                'type' => 'main-child',
                'sort_order' => 45,
                'is_active' => true,
                'meta_title' => 'Огнеупорное стекло для печей и каминов | KOTLOV',
                'meta_description' => 'Жаростойкое стекло для печных и каминных дверок. Размеры, совместимость, цены и доставка по Беларуси.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        $categoryId = DB::table('categories')->where('slug', 'stekla-dlya-pechey-i-kaminov')->value('id');

        if ($categoryId && ! DB::table('products')->where('category_id', $categoryId)->exists()) {
            DB::table('categories')->where('id', $categoryId)->delete();
        }
    }
};

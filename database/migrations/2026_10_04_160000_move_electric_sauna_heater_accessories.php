<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $electricHeatersId = DB::table('categories')->where('slug', 'elektrokamenki')->value('id');
        $accessoriesParentId = DB::table('categories')->where('slug', 'aksessuary-dlya-bani')->value('id');
        if (! $electricHeatersId || ! $accessoriesParentId) {
            return;
        }

        $now = now();
        DB::table('categories')->updateOrInsert(
            ['slug' => 'komplektuyushchie-dlya-elektrokamenok'],
            [
                'parent_id' => $accessoriesParentId,
                'name' => 'Комплектующие для электрокаменок',
                'h1' => 'Пульты и комплектующие для электрокаменок',
                'type' => 'child',
                'sort_order' => 15,
                'is_active' => true,
                'content' => '<p>Пульты управления, силовые блоки, датчики и другие комплектующие для электрических печей бань и саун.</p>',
                'meta_title' => 'Пульты и комплектующие для электрокаменок — купить в %city% | KOTLOV',
                'meta_keywords' => 'пульт для электрокаменки, блок мощности, комплектующие для электропечи сауны',
                'meta_description' => 'Пульты управления, блоки мощности, датчики и комплектующие для электрокаменок. Подбор совместимого оборудования и доставка по Беларуси.',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        $accessoriesId = DB::table('categories')
            ->where('slug', 'komplektuyushchie-dlya-elektrokamenok')
            ->value('id');

        if (! $accessoriesId) {
            return;
        }

        DB::table('products')
            ->where('category_id', $electricHeatersId)
            ->where(function ($query) {
                $query->where('name', 'like', 'пульт управления%')
                    ->orWhere('name', 'like', 'сенсорный пульт%')
                    ->orWhere('name', 'like', 'кнопочный пульт%')
                    ->orWhere('name', 'like', 'блок мощности%')
                    ->orWhere('name', 'like', 'термодатчик%')
                    ->orWhere('name', 'like', 'пароиспаритель%');
            })
            ->update([
                'category_id' => $accessoriesId,
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        // Catalog repair: intentionally not reversed.
    }
};

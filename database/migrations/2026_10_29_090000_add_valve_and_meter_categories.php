<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Чистый берег brings shut-off valves and gauges the catalogue had no place for.
 */
return new class extends Migration
{
    private const GROUPS = [
        'krany-i-zapornaya-armatura' => [
            'zadvizhki' => ['Задвижки', 10],
            'zatvory-diskovye' => ['Затворы дисковые', 20],
            'ventili-zapornye' => ['Вентили запорные', 30],
            'obratnye-klapany' => ['Обратные клапаны', 40],
        ],
        'komplektuyushhie-dlya-otopleniya' => [
            'manometry-i-termometry' => ['Манометры и термометры', 200],
            'schetchiki-vody-i-tepla' => ['Счётчики воды и тепла', 210],
        ],
    ];

    public function up(): void
    {
        $now = now();
        foreach (self::GROUPS as $parentSlug => $children) {
            $parentId = (int) DB::table('categories')->where('slug', $parentSlug)->value('id');
            if (! $parentId) {
                continue;
            }
            DB::table('categories')->where('id', $parentId)->update(['is_active' => true, 'updated_at' => $now]);
            foreach ($children as $slug => [$name, $sort]) {
                if (DB::table('categories')->where('slug', $slug)->exists()) {
                    continue;
                }
                DB::table('categories')->insert([
                    'parent_id' => $parentId,
                    'name' => $name,
                    'slug' => $slug,
                    'h1' => $name,
                    'type' => 'child',
                    'sort_order' => $sort,
                    'is_active' => true,
                    'meta_title' => $name.' купить в %city% — цены | KOTLOV',
                    'meta_description' => $name.' в наличии и под заказ: цены в BYN, характеристики, доставка по Беларуси.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Categories stay with their cards.
    }
};

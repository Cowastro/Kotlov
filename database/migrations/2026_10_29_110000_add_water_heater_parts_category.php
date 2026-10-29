<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Spare parts of water heaters and buffer tanks (heating elements, thermostats, flanges, insulation) from Чистый берег.
 */
return new class extends Migration
{
    public function up(): void
    {
        $parentId = (int) DB::table('categories')->where('slug', 'vodonagrevateli')->value('id');
        if (! $parentId || DB::table('categories')->where('slug', 'komplektuyushchie-dlya-vodonagrevateley')->exists()) {
            return;
        }
        $name = 'Комплектующие для водонагревателей';
        DB::table('categories')->insert([
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => 'komplektuyushchie-dlya-vodonagrevateley',
            'h1' => $name,
            'type' => 'child',
            'sort_order' => 200,
            'is_active' => true,
            'meta_title' => $name.' купить в %city% — цены | KOTLOV',
            'meta_description' => 'ТЭНы, термостаты, фланцы, аноды и изоляция для водонагревателей и буферных ёмкостей: цены в BYN, доставка по Беларуси.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // The category stays with its cards.
    }
};

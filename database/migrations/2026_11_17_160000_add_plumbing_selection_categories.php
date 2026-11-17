<?php

use App\Models\Category;
use App\Services\Suppliers\TdKomplektPlumbingCategories;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach (TdKomplektPlumbingCategories::CATEGORIES as $slug => [$name, $parent, $description]) {
            $parentId = Category::where('slug', $parent)->value('id');
            if (! $parentId) {
                continue;
            }
            Category::firstOrCreate(['slug' => $slug], ['parent_id' => $parentId, 'name' => $name,
                'h1' => $name, 'is_active' => true, 'type' => 'child', 'sort_order' => 65,
                'meta_title' => $name.' — купить в %city% | KOTLOV',
                'meta_description' => $description.' Цены в BYN, наличие и доставка по Беларуси.']);
        }
    }

    public function down(): void
    {
        // Preserve populated categories and historical product URLs.
    }
};

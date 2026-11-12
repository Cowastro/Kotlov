<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;

class CatalogPublishedCategories
{
    public function ids(): array
    {
        $categories = Category::active()->get(['id', 'parent_id', 'slug'])->keyBy('id');
        $ids = [];
        foreach (Product::orderable()->distinct()->pluck('category_id') as $id) {
            $seen = [];
            while (($category = $categories->get($id)) && ! isset($seen[$id])) {
                $seen[$id] = $ids[(int) $id] = true;
                $id = $category->parent_id;
            }
        }
        $gas = $categories->firstWhere('slug', 'gas');
        $canonicalGas = $categories->firstWhere('slug', 'vodogreynaya-kolonka');
        if ($gas && $canonicalGas && isset($ids[$gas->id])) $ids[$canonicalGas->id] = true;
        return array_keys($ids);
    }
}

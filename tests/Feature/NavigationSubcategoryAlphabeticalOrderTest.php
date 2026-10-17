<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;
use Tests\TestCase;

class NavigationSubcategoryAlphabeticalOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_children_are_alphabetical_despite_manual_sort_order(): void
    {
        $parent = Category::query()->create([
            'parent_id' => 0,
            'name' => 'Навигация для проверки алфавита',
            'slug' => 'alphabetical-navigation-test',
            'is_active' => true,
        ]);

        $subcategories = [
            ['name' => 'Фильтры навигации', 'slug' => 'navigation-filters-test', 'sort_order' => 1],
            ['name' => 'Гибкие воздуховоды навигации', 'slug' => 'navigation-ducts-test', 'sort_order' => 2],
            ['name' => 'Буферные емкости навигации', 'slug' => 'navigation-tanks-test', 'sort_order' => 3],
        ];

        foreach ($subcategories as $index => $data) {
            $subcategory = Category::query()->create([
                'parent_id' => $parent->id,
                'name' => $data['name'],
                'slug' => $data['slug'],
                'sort_order' => $data['sort_order'],
                'is_active' => true,
            ]);

            Product::query()->create([
                'category_id' => $subcategory->id,
                'name' => 'Товар '.$data['name'],
                'slug' => 'navigation-alphabetical-product-'.$index,
                'sku' => 'NAV-ALPHABETICAL-'.$index,
                'price' => 100 + $index,
                'is_active' => true,
                'is_archived' => false,
                'in_stock' => true,
                'availability_status' => Product::AVAILABILITY_IN_STOCK,
            ]);
        }

        Cache::flush();

        $method = new ReflectionMethod(AppServiceProvider::class, 'getNavCategories');
        $navigation = $method->invoke(new AppServiceProvider($this->app));
        $navigationParent = $navigation->firstWhere('slug', $parent->slug);

        $this->assertNotNull($navigationParent);
        $this->assertSame([
            'Буферные емкости навигации',
            'Гибкие воздуховоды навигации',
            'Фильтры навигации',
        ], $navigationParent->children->pluck('name')->all());
    }
}

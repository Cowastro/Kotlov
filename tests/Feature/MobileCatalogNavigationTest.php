<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileCatalogNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_renders_mobile_navigation_with_alphabetical_children(): void
    {
        $root = Category::query()->create([
            'parent_id' => 0,
            'name' => 'Отопление',
            'slug' => 'mobile-heating',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $beta = Category::query()->create([
            'parent_id' => $root->id,
            'name' => 'Бета раздел',
            'slug' => 'mobile-beta',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $alpha = Category::query()->create([
            'parent_id' => $root->id,
            'name' => 'Альфа раздел',
            'slug' => 'mobile-alpha',
            'sort_order' => 99,
            'is_active' => true,
        ]);

        $this->createProduct($beta, 'MOBILE-BETA');
        $this->createProduct($alpha, 'MOBILE-ALPHA');

        $this->get('/catalog')
            ->assertOk()
            ->assertSee('class="catalog-index-page"', false)
            ->assertSee('class="mobile-catalog-index d-md-none"', false)
            ->assertSee('Поиск в каталоге KOTLOV')
            ->assertSeeInOrder(['Альфа раздел', 'Бета раздел']);
    }

    private function createProduct(Category $category, string $sku): void
    {
        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Тестовый товар '.$sku,
            'slug' => mb_strtolower($sku),
            'sku' => $sku,
            'price' => 100,
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => true,
            'availability_status' => Product::AVAILABILITY_IN_STOCK,
        ]);
    }
}

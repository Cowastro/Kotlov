<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantSelectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_page_groups_same_tank_series_by_volume_and_shows_availability(): void
    {
        $category = Category::query()->create([
            'name' => 'Мембранные баки',
            'slug' => 'membrannye-baki',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $brand = Brand::query()->create([
            'name' => 'Flamco',
            'slug' => 'flamco-selector-test',
            'is_active' => true,
        ]);

        $current = $this->tank($category, $brand, 25, Product::AVAILABILITY_CHECK);
        $available = $this->tank($category, $brand, 35, Product::AVAILABILITY_IN_STOCK, true);
        $this->tank($category, $brand, 50, Product::AVAILABILITY_OUT_OF_STOCK);

        Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Расширительный бак Flamco Airfix R 35 л',
            'slug' => 'flamco-airfix-r-35-test',
            'price' => 200,
            'is_active' => true,
            'is_archived' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);

        $response = $this->get('/membrannye-baki/'.$current->slug);

        $response->assertOk()
            ->assertSeeText('Выберите объём:')
            ->assertSeeText('25 л')
            ->assertSeeText('35 л')
            ->assertSeeText('50 л')
            ->assertSee('/membrannye-baki/'.$available->slug, false)
            ->assertSee('is-current', false)
            ->assertSee('is-in_stock', false);

        $this->assertSame(3, substr_count($response->getContent(), 'class="product-model-selector__option '));
    }

    private function tank(Category $category, Brand $brand, int $volume, string $availability, bool $inStock = false): Product
    {
        return Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => "Расширительный бак Flamco Flexcon R {$volume} л",
            'slug' => "flamco-flexcon-r-{$volume}-test",
            'price' => 150 + $volume,
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => $inStock,
            'manual_stock_management' => true,
            'availability_status' => $availability,
        ]);
    }
}

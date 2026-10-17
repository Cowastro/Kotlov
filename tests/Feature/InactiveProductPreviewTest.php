<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InactiveProductPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_product_stays_hidden_from_public_visitors(): void
    {
        [$category, $product] = $this->inactiveProduct();

        $this->get('https://kotlov.by/'.$category->slug.'/'.$product->slug.'?admin-preview=1')
            ->assertNotFound();
    }

    public function test_active_manager_can_preview_an_inactive_product(): void
    {
        [$category, $product] = $this->inactiveProduct();
        $manager = User::factory()->create([
            'role' => 'manager',
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->followingRedirects()
            ->get('https://kotlov.by/'.$category->slug.'/'.$product->slug.'?admin-preview=1')
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('133.00 BYN')
            ->assertSee('Нет в наличии')
            ->assertSee('class="product-detail-page"', false)
            ->assertSee('class="mobile-product-topbar d-md-none"', false)
            ->assertSee('class="mobile-product-media-actions d-md-none"', false)
            ->assertDontSee('class="tf-btn type-xl animate-btn w-100 btn-add-to-cart"', false);
    }

    /** @return array{Category, Product} */
    private function inactiveProduct(): array
    {
        $category = Category::query()->create([
            'parent_id' => 0,
            'name' => 'Печи для предпросмотра',
            'slug' => 'preview-stoves',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Отключённая печь для предпросмотра',
            'slug' => 'inactive-preview-stove',
            'sku' => 'PREVIEW-STOVE-1',
            'price' => 133,
            'is_active' => false,
            'is_archived' => false,
            'in_stock' => true,
            'availability_status' => Product::AVAILABILITY_IN_STOCK,
        ]);
        $supplier = Supplier::query()->create([
            'code' => 'inactive-preview-supplier',
            'name' => 'Поставщик отключённой карточки',
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_article' => 'INACTIVE-PREVIEW-1',
            'in_stock' => true,
            'stock_quantity' => 5,
        ]);

        return [$category, $product];
    }
}

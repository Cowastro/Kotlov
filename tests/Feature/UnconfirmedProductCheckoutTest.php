<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnconfirmedProductCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_without_confirmed_supplier_stock_cannot_enter_the_cart(): void
    {
        $category = Category::query()->create([
            'name' => 'Тестовая категория',
            'slug' => 'unconfirmed-checkout-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Товар с неподтверждённым наличием',
            'slug' => 'unconfirmed-product',
            'sku' => 'UNCONFIRMED-1',
            'price' => 33,
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);

        $this->postJson('/cart/add', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Товар сейчас недоступен для оформления заказа.');

        $this->assertEmpty(session('cart', []));
    }
}

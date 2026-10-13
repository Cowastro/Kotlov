<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnconfirmedProductCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_without_confirmed_supplier_stock_cannot_enter_the_cart(): void
    {
        $product = $this->unconfirmedProduct();

        $this->postJson('/cart/add', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Товар сейчас недоступен для оформления заказа.');

        $this->assertEmpty(session('cart', []));
    }

    public function test_stale_cart_cannot_create_an_order_after_stock_becomes_unconfirmed(): void
    {
        $product = $this->unconfirmedProduct();
        $cart = [
            $product->id => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'sku' => $product->sku,
                'price' => 33.0,
                'quantity' => 1,
                'pricing_type' => 'retail',
            ],
        ];

        $this->withSession(['cart' => $cart])
            ->withHeader('User-Agent', 'Mozilla/5.0')
            ->post(route('checkout.store'), [
                'order_token' => 'unconfirmed-retail-stock',
                'customer_name' => 'Тестовый покупатель',
                'customer_phone' => '+375291112233',
                'delivery_type' => 'pickup',
                'payment_type' => 'cash',
            ])
            ->assertRedirect(route('cart'))
            ->assertSessionHas(
                'error',
                'Заказ не создан: товары без подтверждённого наличия удалены из корзины. Закажите консультацию.'
            );

        $this->assertEmpty(session('cart', []));
        $this->assertDatabaseCount((new Order)->getTable(), 0);
    }

    public function test_cart_api_removes_an_item_that_requires_availability_confirmation(): void
    {
        $product = $this->unconfirmedProduct();

        $this->withSession([
            'cart' => [
                $product->id => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => 33.0,
                    'quantity' => 1,
                    'pricing_type' => 'retail',
                ],
            ],
        ])->getJson('/cart/data')
            ->assertOk()
            ->assertJsonPath('count', 0)
            ->assertJsonPath('items', []);

        $this->assertEmpty(session('cart', []));
    }

    public function test_catalog_cards_offer_consultation_instead_of_cart_for_unconfirmed_product(): void
    {
        $product = $this->unconfirmedProduct()->load('category');

        foreach (['partials.product-card', 'partials.product-card-list'] as $view) {
            $this->view($view, ['product' => $product])
                ->assertSee('Заказать консультацию')
                ->assertSee('data-can-buy="0"', false)
                ->assertDontSee('btn-add-to-cart', false);
        }
    }

    private function unconfirmedProduct(): Product
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'unconfirmed-checkout-test'],
            [
                'name' => 'Тестовая категория',
                'parent_id' => 0,
                'is_active' => true,
            ],
        );

        return Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Товар с неподтверждённым наличием',
            'slug' => 'unconfirmed-product-'.uniqid(),
            'sku' => 'UNCONFIRMED-'.uniqid(),
            'price' => 33,
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);
    }
}

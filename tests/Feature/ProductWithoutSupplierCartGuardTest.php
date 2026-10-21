<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductWithoutSupplierCartGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_without_supplier_cannot_be_added_to_cart_and_offers_availability_request(): void
    {
        $product = $this->product();

        $this->assertFalse($product->canBeAddedToCart());
        $this->assertSame(Product::AVAILABILITY_CHECK, $product->publicAvailabilityStatus());

        $this->postJson(route('cart.add'), ['product_id' => $product->id])
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Товар нельзя добавить в корзину: поставщик пока не назначен. Оставьте запрос — менеджер уточнит наличие и возможность поставки.',
            );

        $this->assertEmpty(session('cart', []));

        $this->view('partials.product-card', ['product' => $product->load('category')])
            ->assertSee('Уточняйте наличие')
            ->assertSee('Цена по запросу')
            ->assertDontSee('100.00 BYN')
            ->assertSee('Уточнить наличие')
            ->assertDontSee('btn-add-to-cart', false);
    }

    public function test_product_without_supplier_is_removed_from_an_old_cart_before_checkout(): void
    {
        $product = $this->product();
        $cart = [
            $product->id => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'sku' => $product->sku,
                'price' => 100.0,
                'quantity' => 1,
                'pricing_type' => 'retail',
            ],
        ];

        $this->withSession(['cart' => $cart])
            ->post(route('checkout.store'), [
                'order_token' => 'supplier-guard-checkout',
                'customer_name' => 'Проверка поставщика',
                'customer_phone' => '+375291112233',
                'delivery_type' => 'pickup',
                'payment_type' => 'cash',
            ])
            ->assertRedirect(route('cart'))
            ->assertSessionHas('error');

        $this->assertEmpty(session('cart', []));
        $this->assertDatabaseCount((new Order)->getTable(), 0);
    }

    public function test_active_supplier_link_restores_normal_cart_ordering(): void
    {
        $product = $this->product();
        $supplier = Supplier::query()->create([
            'code' => 'cart-guard-supplier',
            'name' => 'Поставщик корзины',
            'is_active' => true,
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_article' => 'CART-GUARD-1',
            'price_byn' => 80,
            'in_stock' => false,
            'stock_status' => 'unknown',
        ]);

        $this->postJson(route('cart.add'), ['product_id' => $product->id])
            ->assertOk();

        $this->assertArrayHasKey($product->id, session('cart', []));
    }

    public function test_unconfirmed_price_blocks_cart_even_with_an_active_supplier(): void
    {
        $product = $this->product();
        $product->update(['requires_price_confirmation' => true]);
        $supplier = Supplier::query()->create([
            'code' => 'price-hold-supplier',
            'name' => 'Поставщик неподтверждённой цены',
            'is_active' => true,
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_article' => 'PRICE-HOLD-1',
            'price_byn' => 80,
            'in_stock' => false,
            'stock_status' => 'unknown',
        ]);

        $this->assertFalse($product->fresh()->canBeAddedToCart());

        $this->postJson(route('cart.add'), ['product_id' => $product->id])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Товар сейчас недоступен для оформления заказа.');

        $this->assertEmpty(session('cart', []));
    }

    private function product(): Product
    {
        $category = Category::query()->create([
            'name' => 'Проверка поставщика',
            'slug' => 'supplier-cart-guard-'.uniqid(),
            'parent_id' => 0,
            'is_active' => true,
        ]);

        return Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Товар без поставщика',
            'slug' => 'product-without-supplier-'.uniqid(),
            'sku' => 'NO-SUPPLIER-'.uniqid(),
            'price' => 100,
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => true,
            'availability_status' => Product::AVAILABILITY_IN_STOCK,
        ]);
    }
}

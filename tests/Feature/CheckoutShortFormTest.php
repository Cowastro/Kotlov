<?php

namespace Tests\Feature;

use App\Events\NewOrderCreated;
use App\Http\Controllers\CheckoutController;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CheckoutShortFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_region_follows_from_the_city(): void
    {
        $this->assertSame('Минск', CheckoutController::regionOf('г. Минск'));
        $this->assertSame('Гродненская область', CheckoutController::regionOf('Лида'));
        $this->assertSame('Могилёвская область', CheckoutController::regionOf('могилев'));
        $this->assertNull(CheckoutController::regionOf('Неизвестновка'));
    }

    public function test_an_order_needs_no_region_and_the_form_asks_only_name_phone_city_address(): void
    {
        Event::fake([NewOrderCreated::class]);
        $category = Category::query()->create(['name' => 'Короткая форма', 'slug' => 'short-form-test', 'parent_id' => 0]);
        $product = Product::query()->create(['category_id' => $category->id, 'name' => 'Котёл', 'slug' => 'short-form-boiler', 'sku' => 'SHORT-1', 'price' => 200]);
        $supplier = Supplier::query()->create(['code' => 'short-form-stock', 'name' => 'Склад']);
        SupplierProduct::query()->create(['supplier_id' => $supplier->id, 'product_id' => $product->id, 'supplier_article' => 'SHORT-1', 'in_stock' => true, 'stock_quantity' => 5]);
        $cart = ['cart' => [$product->id => ['id' => $product->id, 'name' => $product->name, 'slug' => $product->slug, 'sku' => $product->sku, 'price' => 200, 'quantity' => 1]]];

        $this->withSession($cart)->get(route('checkout'))
            ->assertOk()
            ->assertDontSee('id="delivery-region"', false)
            ->assertSee('id="delivery-city-list"', false)
            ->assertSee('Дополнительно: e-mail и комментарий');

        $this->withHeader('User-Agent', 'Mozilla/5.0')->withSession($cart)->post(route('checkout.store'), [
            'order_token' => 'short-form-checkout',
            'customer_name' => 'Иван',
            'customer_phone' => '+375291112233',
            'delivery_type' => 'transport',
            'delivery_city' => 'Лида',
            'delivery_address' => 'ул. Советская, 1',
            'payment_type' => 'cash',
        ])->assertRedirect();

        $order = Order::query()->latest('id')->firstOrFail();
        $this->assertSame('Гродненская область', $order->delivery_region);
        $this->assertSame('Лида', $order->delivery_city);
    }
}

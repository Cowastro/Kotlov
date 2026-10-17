<?php

namespace Tests\Feature;

use App\Events\NewOrderCreated;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OnlineOrderCouponTest extends TestCase
{
    use RefreshDatabase;

    public function test_online3_coupon_is_seeded_as_an_active_three_percent_discount(): void
    {
        $coupon = Coupon::query()->where('code', 'ONLINE3')->firstOrFail();

        $this->assertSame('percent', $coupon->type);
        $this->assertSame('3.00', $coupon->value);
        $this->assertTrue($coupon->is_active);
        $this->assertNull($coupon->expires_at);
        $this->assertNull($coupon->uses_limit);
    }

    public function test_coupon_endpoint_validates_and_calculates_discount(): void
    {
        $response = $this
            ->withSession(['cart' => [1 => ['price' => 250, 'quantity' => 2]]])
            ->postJson(route('cart.coupon'), ['coupon' => 'online3']);

        $response
            ->assertOk()
            ->assertJson([
                'coupon' => 'ONLINE3',
                'discount' => 15,
                'total' => 485,
            ]);
        $response->assertSessionHas('cart_coupon', 'ONLINE3');
    }

    public function test_unknown_coupon_is_rejected_and_removed_from_session(): void
    {
        $response = $this
            ->withSession([
                'cart' => [1 => ['price' => 100, 'quantity' => 1]],
                'cart_coupon' => 'OLD-CODE',
            ])
            ->postJson(route('cart.coupon'), ['coupon' => 'NOT-A-COUPON']);

        $response->assertUnprocessable()->assertJsonStructure(['message']);
        $response->assertSessionMissing('cart_coupon');
    }

    public function test_checkout_applies_discount_to_goods_not_delivery_and_records_usage(): void
    {
        Event::fake([NewOrderCreated::class]);
        $product = $this->orderableProduct();

        $response = $this
            ->withHeader('User-Agent', 'Mozilla/5.0')
            ->withSession([
                'cart' => [
                    $product->id => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'price' => 200,
                        'quantity' => 1,
                    ],
                ],
                'cart_coupon' => 'ONLINE3',
            ])
            ->post(route('checkout.store'), [
                'order_token' => 'online3-checkout-test',
                'customer_name' => 'Покупатель',
                'customer_phone' => '+375291112233',
                'delivery_type' => 'pickup',
                'payment_type' => 'cash',
            ]);

        $order = Order::query()->latest('id')->firstOrFail();

        $response->assertRedirect(route('checkout.success', $order->number));
        $this->assertSame('ONLINE3', $order->coupon_code);
        $this->assertSame('200.00', $order->subtotal);
        $this->assertSame('6.00', $order->discount);
        $this->assertSame('194.00', $order->total);
        $this->assertSame(1, Coupon::query()->where('code', 'ONLINE3')->value('uses_count'));
        Event::assertDispatched(NewOrderCreated::class);
    }

    public function test_site_presents_online_order_offer_without_discouraging_customer_support(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('−3% за самостоятельный заказ онлайн')
            ->assertSee('ONLINE3')
            ->assertSee('без ожидания ответа менеджера')
            ->assertDontSee('болтовн', false);
    }

    private function orderableProduct(): Product
    {
        $category = Category::query()->create([
            'name' => 'Промокоды',
            'slug' => 'online-coupon-test',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Товар для онлайн-заказа',
            'slug' => 'online-order-product',
            'sku' => 'ONLINE-3',
            'price' => 200,
        ]);
        $supplier = Supplier::query()->create([
            'code' => 'online-order-stock',
            'name' => 'Поставщик онлайн-заказа',
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_article' => 'ONLINE-3',
            'in_stock' => true,
            'stock_quantity' => 5,
        ]);

        return $product;
    }
}

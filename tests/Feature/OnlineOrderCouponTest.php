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

    public function test_online_discount_is_applied_automatically_when_retail_product_is_added(): void
    {
        $product = $this->orderableProduct();

        $response = $this
            ->withHeader('User-Agent', 'Mozilla/5.0')
            ->postJson(route('cart.add'), [
                'product_id' => $product->id,
                'quantity' => 1,
            ]);

        $response
            ->assertOk()
            ->assertJson([
                'discount' => 6,
                'total' => 194,
            ]);
        $response->assertSessionHas('cart_coupon', 'ONLINE3');
    }

    public function test_heat_pump_is_excluded_from_the_online_discount(): void
    {
        $product = $this->orderableHeatPump();

        $response = $this
            ->withHeader('User-Agent', 'Mozilla/5.0')
            ->postJson(route('cart.add'), [
                'product_id' => $product->id,
                'quantity' => 1,
            ]);

        $response
            ->assertOk()
            ->assertJson([
                'discount' => 0,
                'total' => 21300,
            ]);
        $response->assertSessionMissing('cart_coupon');
        $this->assertFalse($product->qualifiesForOnlineOrderDiscount());

        $this->get('/'.$product->category->slug.'/'.$product->slug)
            ->assertOk()
            ->assertDontSeeText('со скидкой −3%')
            ->assertSeeText('В корзину');
    }

    public function test_mixed_cart_discounts_regular_goods_but_not_the_heat_pump(): void
    {
        $regular = $this->orderableProduct();
        $heatPump = $this->orderableHeatPump();

        $this->withHeader('User-Agent', 'Mozilla/5.0')->postJson(route('cart.add'), [
            'product_id' => $regular->id,
            'quantity' => 1,
        ])->assertOk();

        $response = $this->withHeader('User-Agent', 'Mozilla/5.0')->postJson(route('cart.add'), [
            'product_id' => $heatPump->id,
            'quantity' => 1,
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'subtotal' => 21500,
                'discount' => 6,
                'total' => 21494,
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

    public function test_installment_checkout_does_not_apply_the_online_order_discount(): void
    {
        Event::fake([NewOrderCreated::class]);
        $product = $this->orderableProduct();
        $cart = [
            $product->id => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'sku' => $product->sku,
                'price' => 200,
                'quantity' => 1,
                'category_slug' => $product->category->slug,
                'pricing_type' => 'retail',
            ],
        ];

        $this->withSession(['cart' => $cart, 'cart_coupon' => 'ONLINE3'])
            ->get(route('checkout', ['payment_type' => 'installment_6']))
            ->assertOk()
            ->assertSeeText('Онлайн-скидка −3% при рассрочке не применяется.')
            ->assertSee('id="checkout-discount-row"', false)
            ->assertSee('style="display:none"', false);

        $response = $this->post(route('checkout.store'), [
            'order_token' => 'installment-without-online3',
            'customer_name' => 'Покупатель',
            'customer_phone' => '+375291112233',
            'delivery_type' => 'pickup',
            'payment_type' => 'installment_6',
        ]);

        $order = Order::query()->latest('id')->firstOrFail();

        $response->assertRedirect(route('checkout.success', $order->number));
        $this->assertNull($order->coupon_code);
        $this->assertSame('0.00', $order->discount);
        $this->assertSame('200.00', $order->total);
        $this->assertSame(0, Coupon::query()->where('code', 'ONLINE3')->value('uses_count'));
        Event::assertDispatched(NewOrderCreated::class);
    }

    public function test_checkout_does_not_apply_online3_to_a_heat_pump(): void
    {
        Event::fake([NewOrderCreated::class]);
        $product = $this->orderableHeatPump();

        $response = $this
            ->withHeader('User-Agent', 'Mozilla/5.0')
            ->withSession([
                'cart' => [
                    $product->id => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'slug' => $product->slug,
                        'sku' => $product->sku,
                        'price' => 21300,
                        'quantity' => 1,
                        'category_slug' => 'teplovyie-nasosyi',
                        'pricing_type' => 'retail',
                    ],
                ],
                'cart_coupon' => 'ONLINE3',
                'order_token' => 'heat-pump-no-online3-checkout',
            ])
            ->post(route('checkout.store'), [
                'order_token' => 'heat-pump-no-online3-checkout',
                'customer_name' => 'Покупатель',
                'customer_phone' => '+375291112233',
                'delivery_type' => 'pickup',
                'payment_type' => 'cash',
            ]);

        $order = Order::query()->latest('id')->firstOrFail();

        $response->assertRedirect(route('checkout.success', $order->number));
        $this->assertNull($order->coupon_code);
        $this->assertSame('0.00', $order->discount);
        $this->assertSame('21300.00', $order->total);
        Event::assertDispatched(NewOrderCreated::class);
    }

    public function test_site_presents_online_order_offer_without_discouraging_customer_support(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Онлайн-цена ниже на 3%')
            ->assertSee('скидка применится автоматически')
            ->assertSee('Заказы принимаются 24/7')
            ->assertDontSee('Скопировать промокод')
            ->assertDontSee('болтовн', false);
    }

    public function test_cart_explains_automatic_discount_without_requiring_the_code(): void
    {
        $product = $this->orderableProduct();

        $this->withSession([
            'cart' => [
                $product->id => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'sku' => $product->sku,
                    'price' => 200,
                    'quantity' => 1,
                    'category_slug' => $product->category->slug,
                    'pricing_type' => 'retail',
                ],
            ],
        ])->get(route('cart'))
            ->assertOk()
            ->assertSee('Онлайн-скидка применена автоматически')
            ->assertSee('Ничего вводить не нужно')
            ->assertDontSee('Применить ONLINE3');
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

    private function orderableHeatPump(): Product
    {
        $category = Category::query()->firstOrCreate([
            'slug' => 'teplovyie-nasosyi',
        ], [
            'name' => 'Тепловые насосы',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Тепловой насос KOTLOV GE FLM80-250',
            'slug' => 'online-order-heat-pump',
            'sku' => 'HEAT-PUMP-NO-ONLINE3',
            'price' => 21300,
            'is_active' => true,
            'is_archived' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
            'requires_price_confirmation' => false,
        ]);
        $supplier = Supplier::query()->create([
            'code' => 'heat-pump-online-discount-test',
            'name' => 'Поставщик тепловых насосов',
            'is_active' => true,
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_article' => 'HEAT-PUMP-NO-ONLINE3',
            'in_stock' => false,
            'stock_status' => 'preorder',
        ]);

        return $product->load('category');
    }
}

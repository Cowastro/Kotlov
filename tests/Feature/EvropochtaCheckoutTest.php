<?php

namespace Tests\Feature;

use App\Events\NewOrderCreated;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EvropochtaCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_regional_branch_quote_is_rounded_and_saved_with_the_carrier_cost(): void
    {
        Event::fake([NewOrderCreated::class]);
        $product = $this->product();
        $this->mockCarrier();
        $this->withSession($this->cart($product))
            ->get(route('checkout'))
            ->assertOk()
            ->assertSee('Европочта до отделения')
            ->assertSee('id="evropochta-results" hidden', false)
            ->assertSee('data-branches=', false)
            ->assertSee('г. Лида, Красноармейская, 63, №23')
            ->assertDontSee('Монтажников, 2, №1');

        $quote = $this->postJson(route('checkout.evropochta.quote'), ['branch_id' => 40930010, 'weight' => 0.01])
            ->assertOk()->assertJsonPath('price', 6.5);
        $sessionCookie = $quote->getCookie(config('session.cookie'));
        $this->withCookie($sessionCookie->getName(), $sessionCookie->getValue());

        $orderRequest = [
            'order_token' => 'evropochta-test',
            'customer_name' => 'Покупатель',
            'customer_phone' => '+375291112233',
            'delivery_type' => 'evropochta',
            'payment_type' => 'bank_transfer',
            'delivery_price' => 0,
            'evropochta_branch' => 40930010,
            'evropochta_token' => $quote->json('token'),
        ];

        $this->withHeader('User-Agent', 'Mozilla/5.0')
            ->post(route('checkout.store'), ['evropochta_branch' => 70130010] + $orderRequest)
            ->assertSessionHasErrors('evropochta_branch');
        $this->post(route('checkout.store'), ['payment_type' => 'cash'] + $orderRequest)
            ->assertSessionHasErrors('payment_type');
        $this->assertDatabaseCount('orders', 0);

        $this->post(route('checkout.store'), $orderRequest)->assertRedirect()->assertSessionHasNoErrors();
        $order = Order::query()->sole();
        $this->assertSame('6.50', $order->delivery_price);
        $this->assertSame('206.50', $order->total);
        $this->assertSame('Лида', $order->delivery_city);
        $this->assertSame('Гродненская область', $order->delivery_region);
        $this->assertSame(6.34, $order->evropochta_quote['carrier_price']);
        $this->assertSame(40930010, $order->evropochta_quote['store_id_finish']);
        $this->assertNull($order->evropochta_tracking_number);

        Http::assertSent(function (Request $request): bool {
            $form = collect($request->data())->pluck('contents', 'name');

            return str_ends_with($request->url(), '/payment/calculate')
                && (float) $form->get('weight') === 1.0
                && (int) $form->get('store_id_finish') === 40930010;
        });
        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/create'));
    }

    public function test_multiple_units_and_missing_package_measurements_are_ineligible(): void
    {
        $product = $this->product();
        $this->mockCarrier();
        $this->withSession($this->cart($product, 2))
            ->get(route('checkout'))->assertOk()->assertDontSee('Европочта до отделения');
        $this->postJson(route('checkout.evropochta.quote'), ['branch_id' => 40930010])
            ->assertUnprocessable();

        foreach ([
            ['weight' => 1, 'length' => 30],
            ['weight' => 5.01, 'length' => 30, 'width' => 20, 'height' => 10],
            ['weight' => 1, 'length' => 110, 'width' => 20, 'height' => 10],
        ] as $parcel) {
            $product->update(['shipping_parcel' => $parcel]);
            $this->withSession($this->cart($product))
                ->postJson(route('checkout.evropochta.quote'), ['branch_id' => 40930010])
                ->assertUnprocessable();
        }

        Http::assertNothingSent();
    }

    private function mockCarrier(): void
    {
        config([
            'services.evropochta.token' => 'test-token',
            'services.evropochta.checkout_enabled' => true,
            'services.evropochta.checkout_origin' => 70130010,
            'shop.checkout_payment_methods' => ['cash', 'installment_6'],
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://api-kassa.evropochta.by/api/external/stores' => Http::response([
                ['id' => 70130010, 'type' => 1, 'ops_number' => 1, 'ops_name' => 'Отделение №1',
                    'city' => 'г. Минск', 'street' => 'Монтажников', 'house' => '2'],
                ['id' => 40930010, 'type' => 1, 'ops_number' => 23, 'ops_name' => 'Отделение №23',
                    'city' => 'г. Лида', 'street' => 'Красноармейская', 'house' => '63', 'region' => 'Гродненская область'],
            ]),
            'https://api-kassa.evropochta.by/api/external/postal/payment/calculate' => Http::response([
                'sender_pays' => 6.34, 'receiver_pays' => 0,
            ]),
        ]);
    }

    private function product(): Product
    {
        $category = Category::query()->create(['name' => 'Комплектующие', 'slug' => 'parcel-test', 'parent_id' => 0]);
        $product = Product::query()->create([
            'category_id' => $category->id, 'name' => 'Деталь', 'slug' => 'parcel-part-test', 'sku' => 'PARCEL-1',
            'price' => 200, 'shipping_parcel' => ['weight' => 1, 'length' => 30, 'width' => 20, 'height' => 10],
        ]);
        $supplier = Supplier::query()->create(['code' => 'parcel-test-stock', 'name' => 'Склад']);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id, 'product_id' => $product->id, 'supplier_article' => $product->sku,
            'in_stock' => true, 'stock_quantity' => 5,
        ]);

        return $product;
    }

    private function cart(Product $product, int $quantity = 1): array
    {
        return ['cart' => [$product->id => [
            'id' => $product->id, 'name' => $product->name, 'slug' => $product->slug, 'sku' => $product->sku,
            'price' => (float) $product->price, 'quantity' => $quantity,
        ]]];
    }
}

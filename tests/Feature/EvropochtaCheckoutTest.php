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
use Illuminate\Support\Facades\RateLimiter;
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
            ->assertSee('Наложенный платёж')
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
        RateLimiter::clear('form:checkout:127.0.0.1');

        $this->post(route('checkout.store'), ['delivery_type' => 'pickup', 'payment_type' => 'evropochta_cod'] + $orderRequest)
            ->assertSessionHasErrors('payment_type');

        $this->post(route('checkout.store'), ['payment_type' => 'evropochta_cod'] + $orderRequest)
            ->assertRedirect()->assertSessionHasNoErrors();
        $order = Order::query()->sole();
        $this->assertSame('6.50', $order->delivery_price);
        $this->assertSame('206.50', $order->total);
        $this->assertSame('evropochta_cod', $order->payment_type);
        $this->assertSame('Оплата при получении', $order->paymentStatusLabel());
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
            ->get(route('checkout'))->assertOk()
            ->assertDontSee('Европочта до отделения')
            ->assertSee('Европочта при оформлении доступна для одной единицы товара.');
        $this->postJson(route('checkout.evropochta.quote'), ['branch_id' => 40930010])
            ->assertUnprocessable();

        foreach ([
            ['weight' => 1, 'length' => 30],
            ['weight' => 30.01, 'length' => 30, 'width' => 20, 'height' => 10],
            ['weight' => 1, 'length' => 110, 'width' => 20, 'height' => 10],
        ] as $parcel) {
            $product->update(['shipping_parcel' => $parcel]);
            $this->withSession($this->cart($product))
                ->postJson(route('checkout.evropochta.quote'), ['branch_id' => 40930010])
                ->assertUnprocessable();
        }

        Http::assertNothingSent();
    }

    public function test_unconfirmed_packaging_and_overweight_products_do_not_call_the_carrier(): void
    {
        $product = $this->product();
        $product->update([
            'shipping_parcel' => null,
            'specs' => ['Вес' => '11,5 кг', 'Габариты без упаковки, мм' => '350×143×210'],
        ]);
        $this->mockCarrier();

        $this->withSession($this->cart($product))->get(route('checkout'))->assertOk()
            ->assertDontSee('Европочта до отделения')
            ->assertSee('Для Европочты при оформлении нужны подтверждённые вес и размеры товара в упаковке.')
            ->assertSee('Рассчитать доставку Европочтой отдельно');

        $product->update(['specs' => ['Вес' => '31 кг', 'Габариты без упаковки, мм' => '350×143×210']]);
        $this->withSession($this->cart($product))->get(route('checkout'))->assertOk()
            ->assertSee('Европочта при оформлении доступна для посылок до 30 кг. Этот товар превышает лимит по весу.');

        Http::assertNothingSent();
    }

    public function test_confirmed_garant_parcel_and_the_30_kg_boundary_can_be_quoted_in_checkout(): void
    {
        $product = $this->product();
        $product->update([
            'name' => 'ИБП Гарант-1500 24В', 'sku' => 'OMENERGO-E0201-0007',
            'slug' => 'ibp-garant-1500-24v-1500va-e0201-0007', 'shipping_parcel' => null,
        ]);
        $migration = require database_path('migrations/2026_11_19_160000_confirm_evropochta_garant_1500_parcel.php');
        $migration->up();
        $parcel = $product->refresh()->shipping_parcel;
        $this->assertEquals(['weight' => 12.5, 'length' => 40, 'width' => 18, 'height' => 25],
            collect($parcel)->only(['weight', 'length', 'width', 'height'])->all());
        $this->mockCarrier(17.26);

        foreach ([12.5, 30] as $weight) {
            $product->update(['shipping_parcel' => ['weight' => $weight] + $parcel]);
            $this->withSession($this->cart($product))->get(route('checkout'))
                ->assertOk()->assertSee('Европочта до отделения')->assertSee('г. Лида, Красноармейская, 63, №23');
            $this->postJson(route('checkout.evropochta.quote'), ['branch_id' => 40930010])
                ->assertOk()->assertJsonPath('price', 17.5);

            Http::assertSent(function (Request $request) use ($weight): bool {
                $form = collect($request->data())->pluck('contents', 'name');

                return str_ends_with($request->url(), '/payment/calculate')
                    && (float) $form->get('weight') === (float) $weight
                    && (int) $form->get('store_id_finish') === 40930010;
            });
        }

        $migration->down();
        $this->assertEquals(30, $product->refresh()->shipping_parcel['weight']);
        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/create'));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_the_public_calculator_quotes_the_packed_parcel_without_a_cart(): void
    {
        $this->mockCarrier();
        $this->get(route('evropochta.calculator'))
            ->assertOk()->assertSee('Калькулятор доставки Европочтой')
            ->assertSee('20 × 15 × 10 см')
            ->assertSee('evropochta-branch-picker.js')
            ->assertSee('г. Лида, Красноармейская, 63, №23')
            ->assertDontSee('Монтажников, 2, №1');

        $this->post(route('evropochta.calculator.estimate'), [
            'evropochta_branch' => 40930010, 'weight' => 1,
            'length' => 20, 'width' => 15, 'height' => 10,
        ])->assertOk()->assertSeeText('6,50 BYN')->assertSeeText('Стандартная посылка');
        $this->assertDatabaseCount('orders', 0);

        $this->from(route('evropochta.calculator'))->post(route('evropochta.calculator.estimate'), [
            'evropochta_branch' => 70130010, 'weight' => 1,
            'length' => 20, 'width' => 15, 'height' => 10,
        ])->assertSessionHasErrors('estimate');

        Http::assertSentCount(2);
    }

    public function test_public_calculator_returns_a_quote_for_local_decimal_input_and_validates_the_branch(): void
    {
        $this->mockCarrier(17.26);
        $parcel = [
            'evropochta_branch' => 40930010, 'weight' => '12,5',
            'length' => '40', 'width' => '18', 'height' => '25',
        ];

        $this->postJson(route('evropochta.calculator.estimate'), $parcel)
            ->assertOk()
            ->assertExactJson([
                'price' => 17.5,
                'address' => 'г. Лида, Красноармейская, 63, №23',
                'type' => 'Стандартная посылка',
            ]);

        $this->postJson(route('evropochta.calculator.estimate'), ['weight' => '50,01'] + $parcel)
            ->assertUnprocessable()->assertJsonValidationErrors('weight');
        $this->postJson(route('evropochta.calculator.estimate'), ['evropochta_branch' => 70130010] + $parcel)
            ->assertUnprocessable()->assertJsonValidationErrors('estimate');

        Http::assertSent(function (Request $request): bool {
            $form = collect($request->data())->pluck('contents', 'name');

            return str_ends_with($request->url(), '/payment/calculate')
                && (float) $form->get('weight') === 12.5;
        });
        $this->assertDatabaseCount('orders', 0);
    }

    private function mockCarrier(float $carrierPrice = 6.34): void
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
                'sender_pays' => $carrierPrice, 'receiver_pays' => 0,
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

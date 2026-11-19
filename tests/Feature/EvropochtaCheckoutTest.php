<?php

namespace Tests\Feature;

use App\Events\NewOrderCreated;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Shipping\EvropochtaCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EvropochtaCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_regional_branch_quote_is_rounded_and_saved_with_the_carrier_cost(): void
    {
        Event::fake([NewOrderCreated::class]);
        $product = $this->product();
        $this->mockCarrier();
        $checkout = $this->withSession($this->cart($product))
            ->get(route('checkout'))
            ->assertOk()
            ->assertSee('Европочта до отделения')
            ->assertSee('До отделения с оплатой при получении')
            ->assertSee('Оплата при получении в Европочте')
            ->assertSee('id="evropochta-results" hidden', false)
            ->assertSee('data-branches=', false)
            ->assertSee('г. Лида, Красноармейская, 63, №23')
            ->assertDontSee('Монтажников, 2, №1');
        $checkout->assertDontSee('name="delivery_type" value="transport"', false);
        $this->assertMatchesRegularExpression('/name="delivery_type" value="evropochta"\s+checked\b/', $checkout->getContent());
        $this->assertMatchesRegularExpression('/value="evropochta_cod"\s+checked\b/', $checkout->getContent());

        $quote = $this->postJson(route('checkout.evropochta.quote'), ['branch_id' => 40930010, 'weight' => 0.01])
            ->assertOk()->assertJsonPath('price', 8);
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
        $this->assertSame('8.00', $order->delivery_price);
        $this->assertSame('208.00', $order->total);
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

    public function test_checkout_defaults_to_payment_on_receipt_for_evropochta_and_keeps_chosen_prepayment(): void
    {
        $this->mockCarrier();
        $session = $this->cart($this->product());

        $default = $this->withSession($session + ['_old_input' => ['delivery_type' => 'evropochta']])
            ->get(route('checkout'))
            ->assertOk();
        $default->assertSee('id="delivery-options"', false);
        $this->assertMatchesRegularExpression('/value="evropochta_cod"\s+checked\b/', $default->getContent());
        $this->assertMatchesRegularExpression('/value="cash"\s+disabled\b/', $default->getContent());

        $prepaid = $this->withSession($session + ['_old_input' => [
            'delivery_type' => 'evropochta',
            'payment_type' => 'bank_transfer',
        ]])->get(route('checkout'))
            ->assertOk();
        $this->assertMatchesRegularExpression('/value="bank_transfer"\s+checked\b/', $prepaid->getContent());
        $this->assertDoesNotMatchRegularExpression('/value="evropochta_cod"\s+checked\b/', $prepaid->getContent());
    }

    public function test_gas_meters_and_alarms_use_payment_on_receipt_for_evropochta(): void
    {
        $product = $this->product();
        $this->mockCarrier();
        $this->withHeader('User-Agent', 'Mozilla/5.0');

        foreach (['schetchiki-gaza', 'signalizatory-zagazovannosti'] as $category) {
            $targetCategory = Category::query()->firstOrCreate(['slug' => $category], ['name' => $category, 'parent_id' => 0]);
            $product->update(['category_id' => $targetCategory->id]);
            $this->withSession($this->cart($product))
                ->get(route('checkout'))
                ->assertOk()
                ->assertSee('Оплата при получении в Европочте')
                ->assertDontSee('id="pay-bank_transfer"', false)
                ->assertDontSee('name="delivery_type" value="transport"', false);

            $input = [
                'order_token' => 'shipping-probe',
                'customer_name' => 'Покупатель',
                'customer_phone' => '+375291112233',
                'payment_type' => 'bank_transfer',
                'delivery_type' => 'evropochta',
                'evropochta_branch' => 40930010,
                'evropochta_token' => 'test-quote',
            ];
            $this->post(route('checkout.store'), $input)->assertSessionHasErrors('payment_type');
            $this->post(route('checkout.store'), [
                'delivery_type' => 'transport',
                'payment_type' => 'cash',
                'delivery_city' => 'Солигорск',
                'delivery_address' => 'ул. Ленина, 1',
            ] + $input)->assertSessionHasErrors('delivery_type');
            RateLimiter::clear('form:checkout:127.0.0.1');
        }

        $this->assertDatabaseCount('orders', 0);
        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/create'));
    }

    public function test_two_elekhant_meters_get_one_box_and_one_quote_to_soligorsk(): void
    {
        $this->mockCarrier(8.83);
        $category = Category::query()->firstOrCreate(['slug' => 'schetchiki-gaza'], ['name' => 'Счётчики газа', 'parent_id' => 0]);
        $meter = $this->product();
        $meter->update([
            'category_id' => $category->id,
            'sku' => 'PS-007.059',
            'slug' => 'schetchik-gaza-bytovoj-yelexant-sgb-18',
            'shipping_parcel' => null,
        ]);
        $pilot = require database_path('migrations/2026_11_19_152000_enable_evropochta_elekhant_packaging_pilot.php');
        $pilot->up();

        $session = $this->cart($meter, 2);
        $this->withSession($session)->get(route('checkout'))->assertOk()
            ->assertSee('Европочта до отделения')
            ->assertSee('Солигорск')
            ->assertDontSee('name="delivery_type" value="transport"', false)
            ->assertDontSee('id="pay-bank_transfer"', false);

        $this->postJson(route('checkout.evropochta.quote'), ['branch_id' => 40930011])
            ->assertOk()
            ->assertJsonPath('price', 11)
            ->assertJsonPath('box.dimensions', [50, 30, 30])
            ->assertJsonPath('box.weight', 2.75);

        $checkout = app(EvropochtaCheckout::class);
        $parcel = $checkout->parcel($session['cart']);
        $this->assertSame([['product_id' => $meter->id, 'quantity' => 2]], $parcel['items']);
        $quote = $checkout->quote($parcel, 40930011, 'session-for-two-meters');
        $changedParcel = $checkout->parcel($this->cart($meter, 1)['cart']);
        $this->assertNull($checkout->confirmedQuote($changedParcel, 40930011, $quote['token'], 'session-for-two-meters'));
        $snapshot = $checkout->confirmedQuote($parcel, 40930011, $quote['token'], 'session-for-two-meters');
        $this->assertSame($parcel, $snapshot['parcel']);
        $this->assertSame('Солигорск', $snapshot['city']);
        Http::assertSent(function (Request $request): bool {
            $fields = collect($request->data())->pluck('contents', 'name');

            return str_ends_with($request->url(), '/payment/calculate')
                && (float) $fields->get('weight') === 2.75
                && (int) $fields->get('is_oversize') === 0;
        });
        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/create'));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_different_products_share_the_smallest_box_only_when_all_measurements_are_confirmed(): void
    {
        $this->mockCarrier();
        $first = $this->product();
        $first->update(['shipping_parcel' => [
            'weight' => 1, 'length' => 20, 'width' => 15, 'height' => 10, 'weight_mode' => 'band',
            'item_dimensions' => [8, 5, 3], 'item_weight' => 0.31,
        ]]);
        $second = $first->replicate(['slug', 'sku']);
        $second->fill(['slug' => 'other-small-part', 'sku' => 'PARCEL-2']);
        $second->save();
        $cart = $this->cart($first)['cart'] + $this->cart($second)['cart'];
        $checkout = app(EvropochtaCheckout::class);

        $parcel = $checkout->parcel($cart);
        $this->assertSame('box-20-15-10', $parcel['box']);
        $this->assertSame(0.87, $parcel['weight']);
        $this->assertSame($parcel, $checkout->parcel(array_reverse($cart, true)));

        $second->update(['shipping_parcel' => null]);
        $this->assertNull($checkout->parcel($cart));
        Http::assertNothingSent();
    }

    public function test_multi_item_baskets_without_a_suitable_box_keep_other_delivery_methods(): void
    {
        $product = $this->product();
        $this->mockCarrier();
        $product->update(['shipping_parcel' => ['weight' => 2, 'length' => 70, 'width' => 30, 'height' => 25]]);
        $this->withSession($this->cart($product, 2))
            ->get(route('checkout'))->assertOk()
            ->assertDontSee('Европочта до отделения')
            ->assertDontSee('name="delivery_type" value="evropochta"', false)
            ->assertSee('Товары не помещаются в одну доступную коробку Европочты с допустимым весом.')
            ->assertSee('Транспортная компания по Беларуси')
            ->assertSee('60.00 BYN');
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
            ->assertDontSee('name="delivery_type" value="evropochta"', false)
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
                ->assertOk()->assertJsonPath('price', 21);

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
        ])->assertOk()->assertSeeText('8 BYN')->assertSeeText('Стандартная посылка');
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
                'price' => 21,
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

    #[DataProvider('retailPrices')]
    public function test_public_tariff_adds_twenty_percent_before_rounding(float $carrierPrice, int $customerPrice): void
    {
        $this->mockCarrier($carrierPrice);

        $this->postJson(route('evropochta.calculator.estimate'), [
            'evropochta_branch' => 40930010, 'weight' => 1,
            'length' => 20, 'width' => 15, 'height' => 10,
        ])->assertOk()->assertJsonPath('price', $customerPrice);
    }

    public static function retailPrices(): array
    {
        return [
            '9.60 rounds up' => [8.00, 10],
            '12.30 rounds down' => [10.25, 12],
            '10.50 rounds up' => [8.75, 11],
        ];
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
                ['id' => 40930011, 'type' => 1, 'ops_number' => 567, 'ops_name' => 'Отделение №567',
                    'city' => 'г. Солигорск', 'street' => 'Ленинского Комсомола', 'house' => '43', 'region' => 'Минская область'],
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

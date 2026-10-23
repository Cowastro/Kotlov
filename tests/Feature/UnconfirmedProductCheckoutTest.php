<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UnconfirmedProductCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_without_confirmed_supplier_stock_can_enter_the_cart_for_manager_confirmation(): void
    {
        $product = $this->unconfirmedProduct();

        $this->postJson('/cart/add', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])
            ->assertOk();

        $this->assertTrue(session('cart')[$product->id]['requires_availability_confirmation']);
    }

    public function test_unconfirmed_stock_creates_a_real_order_for_manager_processing(): void
    {
        Notification::fake();
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
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
                'requires_availability_confirmation' => true,
            ],
        ];
        $orderCount = Order::query()->count();

        $this->withSession(['cart' => $cart])
            ->withHeader('User-Agent', 'Mozilla/5.0')
            ->post(route('checkout.store'), [
                'order_token' => 'unconfirmed-retail-stock',
                'customer_name' => 'Тестовый покупатель',
                'customer_phone' => '+375291112233',
                'delivery_type' => 'pickup',
                'payment_type' => 'cash',
            ])
            ->assertRedirect();

        $this->assertEmpty(session('cart', []));
        $this->assertDatabaseCount((new Order)->getTable(), $orderCount + 1);
        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'supply_is_available' => false,
        ]);
        Notification::assertSentTo(
            $manager,
            DatabaseNotification::class,
            fn (DatabaseNotification $notification): bool => str_contains(
                (string) $notification->data['title'],
                'подтвердить наличие',
            ),
        );
    }

    public function test_cart_api_keeps_an_item_that_requires_availability_confirmation(): void
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
            ->assertJsonPath('count', 1)
            ->assertJsonPath('items.0.requires_availability_confirmation', true);

        $this->assertArrayHasKey($product->id, session('cart', []));
    }

    public function test_catalog_cards_offer_ordering_with_a_clear_manager_confirmation_marker(): void
    {
        $product = $this->unconfirmedProduct()->load('category');

        $this->view('partials.product-card', ['product' => $product])
            ->assertDontSee('card-product--consultation', false)
            ->assertSee('btn-add-to-cart', false)
            ->assertSeeText('Доступно к заказу');

        foreach (['partials.product-card', 'partials.product-card-list'] as $view) {
            $this->view($view, ['product' => $product])
                ->assertSee('33.00 BYN')
                ->assertSee('Заказать')
                ->assertSee('data-can-buy="1"', false)
                ->assertSee('btn-add-to-cart', false);
        }
    }

    public function test_grid_card_shows_a_quiet_stock_marker_only_for_confirmed_stock(): void
    {
        $product = $this->confirmedProduct(120)->load('category');

        $this->view('partials.product-card', ['product' => $product])
            ->assertDontSee('card-product--consultation', false)
            ->assertSee('product-card-availability', false)
            ->assertSeeText('В наличии')
            ->assertSee('aria-label="В наличии', false);
    }

    public function test_confirmed_product_can_be_bought_from_mobile_and_desktop_with_the_same_retail_price(): void
    {
        $product = $this->confirmedProduct(120)->load('category');

        foreach (['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X)', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'] as $userAgent) {
            $this->withHeader('User-Agent', $userAgent);

            foreach (['partials.product-card', 'partials.product-card-list'] as $view) {
                $this->view($view, ['product' => $product])
                    ->assertSee('btn-add-to-cart', false)
                    ->assertSee('data-can-buy="1"', false)
                    ->assertDontSee('Заказать консультацию');
            }

            $this->postJson('/cart/add', ['product_id' => $product->id])
                ->assertOk();

            $this->assertEquals(120, session('cart')[$product->id]['price']);
            $this->assertSame('retail', session('cart')[$product->id]['pricing_type']);
        }
    }

    public function test_product_page_keeps_price_and_order_button_while_stock_requires_confirmation(): void
    {
        $product = $this->unconfirmedProduct();

        $this->get('/'.$product->category->slug.'/'.$product->slug)
            ->assertOk()
            ->assertSee('33.00 BYN')
            ->assertSee('Доступно к заказу')
            ->assertSee('Менеджер подтвердит наличие и срок поставки после оформления.')
            ->assertSee('btn-add-to-cart', false)
            ->assertSeeText('Купить в рассрочку до 6 месяцев')
            ->assertSeeText('Менеджер подтвердит доступность и продолжит оформление')
            ->assertSeeText('Смотреть видеоинструкцию')
            ->assertSee('https://www.youtube.com/watch?v=nw2v3z5BZms', false)
            ->assertSeeText('Как оформить рассрочку')
            ->assertSee(route('installment.info'), false)
            ->assertDontSee('sbg.by', false)
            ->assertSee('btn-installment-purchase', false)
            ->assertSee('payment_type=installment_6', false);
    }

    public function test_checkout_stops_and_refreshes_a_stale_retail_price(): void
    {
        $product = $this->confirmedProduct(120);
        $cart = [
            $product->id => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => 90.0,
                'quantity' => 1,
                'pricing_type' => 'retail',
            ],
        ];
        $orderCount = Order::query()->count();

        $this->withSession(['cart' => $cart])
            ->withHeader('User-Agent', 'Mozilla/5.0')
            ->post(route('checkout.store'), [
                'order_token' => 'stale-retail-price',
                'customer_name' => 'Тестовый покупатель',
                'customer_phone' => '+375291112233',
                'delivery_type' => 'pickup',
                'payment_type' => 'cash',
            ])
            ->assertRedirect(route('cart'))
            ->assertSessionHas(
                'error',
                'Заказ не создан: цены изменились и обновлены в корзине. Проверьте сумму и подтвердите заказ повторно.'
            );

        $this->assertEquals(120.0, session('cart')[$product->id]['price']);
        $this->assertDatabaseCount((new Order)->getTable(), $orderCount);
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

        $product = Product::query()->create([
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

        $supplier = Supplier::query()->create([
            'code' => 'unconfirmed-supplier-'.uniqid(),
            'name' => 'Поставщик с уточняемым наличием',
            'is_active' => true,
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_article' => 'UNCONFIRMED-'.uniqid(),
            'price_byn' => 25,
            'in_stock' => false,
            'stock_status' => 'unknown',
        ]);

        return $product->fresh();
    }

    private function confirmedProduct(float $price): Product
    {
        $product = $this->unconfirmedProduct();
        $product->update([
            'price' => $price,
            'in_stock' => true,
            'availability_status' => Product::AVAILABILITY_IN_STOCK,
        ]);
        $supplier = Supplier::query()->create([
            'code' => 'confirmed-price-'.uniqid(),
            'name' => 'Поставщик подтверждённой цены',
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_article' => 'PRICE-'.uniqid(),
            'price_byn' => max(1, $price - 20),
            'in_stock' => true,
            'stock_quantity' => 3,
        ]);

        return $product->fresh();
    }
}

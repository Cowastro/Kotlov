<?php

namespace Tests\Feature;

use App\Events\NewOrderCreated;
use App\Models\{B2bClient, B2bPricingRule, Category, CustomerCart, Product, Supplier, SupplierProduct, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Auth, Event, Http, Mail, Notification, RateLimiter};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckoutPersistenceFlowTest extends TestCase
{
    use RefreshDatabase;

    public static function customerTypes(): array
    {
        return ['retail' => [false], 'b2b' => [true]];
    }

    private function customer(bool $business): User
    {
        $user = User::factory()->create(['is_active' => true]);
        if ($business) {
            B2bClient::create(['user_id' => $user->id, 'status' => 'approved', 'partner_type' => 'wholesale',
                'company_name' => 'Тестовая компания', 'unp' => '123456789', 'allow_all_partner_suppliers' => true]);
        }
        return $user;
    }

    private function product(string $sku): Product
    {
        $category = Category::firstOrCreate(['slug' => 'checkout-flow'], ['name' => 'Фитинги', 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'sku' => $sku, 'slug' => strtolower($sku),
            'name' => 'Тестовый фитинг '.$sku, 'price' => 200, 'is_active' => true, 'is_archived' => false]);
        $supplier = Supplier::firstOrCreate(['code' => 'checkout-flow'], ['name' => 'Тестовый склад',
            'is_active' => true, 'b2b_partner_enabled' => true, 'b2b_require_positive_stock' => true]);
        SupplierProduct::create(['supplier_id' => $supplier->id, 'product_id' => $product->id,
            'supplier_article' => $sku, 'price_byn' => 100, 'in_stock' => true, 'stock_quantity' => 500,
            'stock_status' => 'in_stock', 'match_status' => 'matched', 'last_stock_synced_at' => now(), 'last_synced_at' => now()]);
        if (! $supplier->b2bPricingRules()->exists()) {
            $supplier->b2bPricingRules()->create(['name' => 'Тестовые условия', 'scope_type' => B2bPricingRule::SCOPE_SUPPLIER,
                'calculation_type' => B2bPricingRule::TYPE_TARGET_MARGIN, 'value' => 20, 'minimum_profit_byn' => 0,
                'minimum_margin_percent' => 10, 'rounding_step_byn' => 1, 'minimum_retail_discount_percent' => 0,
                'priority' => 100, 'status' => B2bPricingRule::STATUS_ACTIVE]);
        }
        return $product;
    }

    private function signIn(User $user): void
    {
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    private function signOut(): void
    {
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        Auth::forgetGuards();
        session()->flush();
    }

    #[DataProvider('customerTypes')]
    public function test_real_login_cycle_preserves_quantities_postponed_lines_notes_and_price_source(bool $business): void
    {
        $user = $this->customer($business);
        $first = $this->product('FLOW-A'); $second = $this->product('FLOW-B');
        $this->signIn($user);
        $this->postJson('/cart/add', ['product_id' => $first->id, 'quantity' => 2])->assertOk();
        $this->postJson('/cart/add', ['product_id' => $second->id, 'quantity' => 7])->assertOk();
        $this->postJson('/cart/update', ['product_id' => $first->id, 'quantity' => 13])->assertOk();
        $this->postJson('/cart/selection', ['ids' => [$second->id], 'selected' => false])->assertOk();
        $this->postJson('/cart/line-note', ['product_id' => $first->id, 'note' => 'Для первого этажа'])->assertOk();
        $saved = CustomerCart::where('user_id', $user->id)->sole();
        $this->assertSame($business ? 'b2b' : 'retail', $saved->items[$first->id]['pricing_type']);
        $before = $saved->items;
        $this->signOut();
        $this->getJson('/cart/data')->assertOk()->assertJsonCount(0, 'items');
        $this->signIn($user);
        $this->getJson('/cart/data')->assertOk()->assertJsonCount(2, 'items');
        $this->assertSame($before, CustomerCart::where('user_id', $user->id)->sole()->items);
        $this->assertEquals([$second->id], session('cart_excluded_ids'));
        $this->get('/checkout')->assertOk()->assertViewHas('cart', fn ($cart) => count($cart) === 1 && $cart[$first->id]['quantity'] === 13);
        $other = $this->customer(! $business);
        $this->signOut();
        $this->signIn($other);
        $this->getJson('/cart/data')->assertOk()->assertJsonCount(0, 'items');
        $this->assertSame($before, CustomerCart::where('user_id', $user->id)->sole()->items);
    }

    #[DataProvider('customerTypes')]
    public function test_invalid_checkout_can_retry_the_same_token_and_keeps_postponed_cart_after_success(bool $business): void
    {
        Event::fake([NewOrderCreated::class]); Mail::fake(); Notification::fake(); Http::fake(['*' => Http::response([], 404)]);
        $user = $this->customer($business);
        $first = $this->product('RETRY-A'); $second = $this->product('RETRY-B');
        $this->signIn($user);
        $this->postJson('/cart/add', ['product_id' => $first->id, 'quantity' => 2])->assertOk();
        $this->postJson('/cart/add', ['product_id' => $second->id, 'quantity' => 7])->assertOk();
        $this->postJson('/cart/selection', ['ids' => [$second->id], 'selected' => false])->assertOk();
        $payload = ['order_token' => 'retry-checkout', 'customer_name' => 'Тест', 'customer_phone' => 'bad',
            'form_started_at' => time(),
            'delivery_type' => 'pickup', 'payment_type' => $business ? 'invoice' : 'cash',
            'company_name' => 'Тестовая компания', 'company_unp' => '123456789',
            'company_address' => 'Тестовый адрес', 'company_email' => 'invoice@example.test'];
        $this->withHeader('User-Agent', 'Mozilla/5.0')->from('/checkout')->post('/checkout', $payload)
            ->assertSessionHasErrors('customer_phone');
        $this->assertFalse(session()->has('order_token_used_retry-checkout'));
        $this->assertCount(2, CustomerCart::where('user_id', $user->id)->sole()->items);
        $payload['customer_phone'] = '+375291112233';
        $this->post('/checkout', $payload)->assertRedirect();
        $this->assertDatabaseCount($business ? 'b2b_orders' : 'orders', 1);
        $this->assertDatabaseCount($business ? 'orders' : 'b2b_orders', 0);
        $this->post('/checkout', $payload)->assertRedirect();
        $this->assertDatabaseCount($business ? 'b2b_orders' : 'orders', 1);
        $this->signOut(); $this->signIn($user);
        $remaining = CustomerCart::where('user_id', $user->id)->sole()->items;
        $this->assertSame([$second->id], array_keys($remaining));
        $this->assertSame(7, $remaining[$second->id]['quantity']);
        $this->getJson('/cart/data')->assertOk()->assertJsonCount(1, 'items');
    }

    #[DataProvider('customerTypes')]
    public function test_checkout_block_does_not_pretend_that_an_order_was_sent_or_erase_cart(bool $business): void
    {
        $user = $this->customer($business); $product = $this->product('BLOCK-A');
        $this->signIn($user);
        $this->postJson('/cart/add', ['product_id' => $product->id, 'quantity' => 5])->assertOk();
        RateLimiter::increment('form:checkout:127.0.0.1', 600, 3);
        $this->withHeader('User-Agent', 'Mozilla/5.0')->from('/checkout')->post('/checkout', [
            'order_token' => 'blocked-checkout', 'customer_name' => 'Тест',
        ])->assertRedirect('/checkout')->assertSessionHas('error')->assertSessionMissing('success');
        $this->get('/checkout')->assertOk()->assertSee('Не удалось отправить заказ. Корзина сохранена.');
        $this->assertDatabaseCount('orders', 0); $this->assertDatabaseCount('b2b_orders', 0);
        $this->assertSame(5, CustomerCart::where('user_id', $user->id)->sole()->items[$product->id]['quantity']);
    }

    public function test_notification_failure_after_order_commit_does_not_leave_ordered_goods_in_saved_cart(): void
    {
        Mail::fake(); Notification::fake();
        Event::fakeExcept([NewOrderCreated::class]);
        Event::listen(NewOrderCreated::class, function () { throw new \RuntimeException('Notification transport unavailable'); });
        $user = $this->customer(false); $product = $this->product('NOTIFY-A');
        $this->signIn($user);
        $this->postJson('/cart/add', ['product_id' => $product->id, 'quantity' => 3])->assertOk();
        $this->withHeader('User-Agent', 'Mozilla/5.0')->post('/checkout', [
            'order_token' => 'notification-failure', 'customer_name' => 'Тест', 'customer_phone' => '+375291112233',
            'delivery_type' => 'pickup', 'payment_type' => 'cash',
        ])->assertRedirectContains('/checkout/success/');
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame([], CustomerCart::where('user_id', $user->id)->sole()->items);
        $this->signOut(); $this->signIn($user);
        $this->getJson('/cart/data')->assertOk()->assertJsonCount(0, 'items');
    }
}

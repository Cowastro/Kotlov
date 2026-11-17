<?php

namespace Tests\Feature;

use App\Events\NewOrderCreated;
use App\Models\{Category, CustomerCart, CustomerSelection, Order, Product, Supplier, SupplierProduct, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MobileCartWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_approved_business_accounts_get_the_business_cart_workspace(): void
    {
        $p = $this->product();
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/cart/add', ['product_id' => $p->id])->assertOk();
        $this->get('/cart')->assertOk()->assertSee('cart-page--retail', false)
            ->assertSee('cart-retail-selection', false)->assertDontSee('data-edit-line-note', false);

        $client = \App\Models\B2bClient::create(['user_id' => $user->id, 'partner_type' => 'wholesale', 'status' => 'pending', 'company_name' => 'Тестовый партнёр']);
        $this->get('/cart')->assertOk()->assertSee('cart-page--retail', false);
        $client->update(['status' => 'approved']);
        $this->actingAs($user->fresh())->get('/cart')->assertOk()->assertSee('cart-page--b2b', false)
            ->assertSee('data-edit-line-note', false)->assertSee('cart-find', false)
            ->assertSee('cart-save-tools--business', false)->assertDontSee('cart-retail-selection', false)
            ->assertDontSee('id="cart-coupon"', false);
    }

    private function product(float $price = 25): Product
    {
        $c = Category::firstOrCreate(['slug' => 'mobile-cart-test'], ['name' => 'Фитинги', 'parent_id' => 0, 'is_active' => true]);
        $n = Product::count();
        $p = Product::create(['category_id' => $c->id, 'name' => 'Тройник '.$n, 'slug' => 'tee-'.$n, 'sku' => 'TEE-'.$n, 'price' => $price, 'is_active' => true, 'is_archived' => false]);
        $s = Supplier::firstOrCreate(['code' => 'mobile-cart-test'], ['name' => 'Склад', 'is_active' => true]);
        SupplierProduct::create(['supplier_id' => $s->id, 'product_id' => $p->id, 'supplier_article' => $p->sku, 'in_stock' => true, 'stock_quantity' => 500, 'price_byn' => $price]);
        return $p;
    }

    public function test_selection_has_its_own_server_total_and_survives_login_session(): void
    {
        $u = User::factory()->create(); $a = $this->product(25); $b = $this->product(40);
        $this->actingAs($u)->postJson('/cart/add', ['product_id' => $a->id, 'quantity' => 2])->assertOk();
        $this->postJson('/cart/add', ['product_id' => $b->id])->assertOk();
        $this->postJson('/cart/selection', ['ids' => [$b->id, 999999], 'selected' => false])->assertOk()
            ->assertJsonPath('selection.positions', 1)->assertJsonPath('selection.subtotal', 50);
        $this->assertCount(2, CustomerCart::where('user_id', $u->id)->first()->items);
        session()->flush();
        $this->get('/cart')->assertOk()->assertSee('cart-mobile-total', false)->assertSee('Найти товар или артикул')
            ->assertViewHas('cartSelection', fn ($s) => $s['positions'] === 1 && $s['subtotal'] === 50.0);
        $this->postJson('/cart/update', ['product_id' => $a->id, 'quantity' => 8])->assertOk()
            ->assertJsonPath('item_quantity', 8)->assertJsonPath('selection.subtotal', 200);
        $this->postJson('/cart/update', ['product_id' => $a->id, 'quantity' => 1000])->assertUnprocessable();
    }

    public function test_only_selected_lines_are_ordered_and_postponed_cart_is_preserved(): void
    {
        Event::fake([NewOrderCreated::class]);
        $u = User::factory()->create(); $a = $this->product(25); $b = $this->product(40);
        $this->actingAs($u)->postJson('/cart/add', ['product_id' => $a->id, 'quantity' => 2]);
        $this->postJson('/cart/add', ['product_id' => $b->id, 'quantity' => 7]);
        $this->postJson('/cart/selection', ['ids' => [$b->id], 'selected' => false]);
        $this->get('/checkout')->assertOk()->assertViewHas('cart', fn ($cart) => array_keys($cart) === [$a->id]);
        $this->withHeader('User-Agent', 'Mozilla/5.0')->post('/checkout', ['order_token' => 'partial-mobile', 'customer_name' => 'Иван', 'customer_phone' => '+375291112233', 'delivery_type' => 'pickup', 'payment_type' => 'cash'])->assertRedirect();
        $order = Order::latest('id')->firstOrFail();
        $this->assertEquals(50, $order->subtotal);
        $this->assertSame([$a->id], $order->items->pluck('product_id')->all());
        $cart = CustomerCart::where('user_id', $u->id)->first()->items;
        $this->assertSame([$b->id], array_keys($cart));
        $this->assertSame(7, $cart[$b->id]['quantity']);
        $this->get('/checkout')->assertRedirect('/cart');
    }

    public function test_repricing_selected_line_does_not_drop_postponed_products(): void
    {
        $a = $this->product(25); $b = $this->product(40);
        $this->postJson('/cart/add', ['product_id' => $a->id]); $this->postJson('/cart/add', ['product_id' => $b->id]);
        $this->postJson('/cart/selection', ['ids' => [$b->id], 'selected' => false]);
        $a->update(['price' => 30]);
        $this->get('/checkout')->assertRedirect('/cart');
        $this->assertCount(2, session('cart'));
        $this->assertEquals(30, session('cart')[$a->id]['price']);
        $this->assertEquals(40, session('cart')[$b->id]['price']);
    }

    public function test_empty_selection_never_places_an_order(): void
    {
        $p = $this->product(); $this->postJson('/cart/add', ['product_id' => $p->id]);
        $this->postJson('/cart/selection', ['ids' => [$p->id], 'selected' => false])->assertJsonPath('selection.total', 0);
        $this->post('/checkout', [])->assertRedirect('/cart');
        $this->assertDatabaseCount('orders', 0); $this->assertCount(1, session('cart'));
        $this->postJson('/cart/selection', ['ids' => [$p->id], 'selected' => true])->assertJsonPath('selection.total', 25);
    }

    public function test_line_notes_are_saved_escaped_and_retained_in_copy_and_print(): void
    {
        $u = User::factory()->create(); $p = $this->product();
        $this->actingAs($u)->postJson('/cart/add', ['product_id' => $p->id]);
        $note = '<script>alert(1)</script>';
        $this->postJson('/cart/line-note', ['product_id' => $p->id, 'note' => $note])->assertOk();
        $this->post('/account/selections', ['name' => 'Объект']);
        $selection = CustomerSelection::where('user_id', $u->id)->firstOrFail();
        $this->get('/account/selections/'.$selection->id.'?print=1')->assertOk()->assertDontSee($note, false)->assertSee($note);
        $this->post('/account/selections/'.$selection->id.'/duplicate')->assertRedirect();
        $this->assertSame($note, CustomerSelection::latest('id')->first()->items[$p->id]['selection_note']);
        $other = User::factory()->create();
        $this->actingAs($other)->post('/account/selections/'.$selection->id.'/duplicate')->assertNotFound();
    }

    public function test_move_is_private_atomic_and_never_overwrites_a_target_line(): void
    {
        $u = User::factory()->create(); $other = User::factory()->create(); $p = $this->product();
        $target = CustomerSelection::create(['user_id' => $u->id, 'name' => 'Объект 2', 'items' => []]);
        $foreign = CustomerSelection::create(['user_id' => $other->id, 'name' => 'Чужой', 'items' => []]);
        $this->actingAs($u)->postJson('/cart/add', ['product_id' => $p->id, 'quantity' => 12]);
        $this->post('/cart/move-to-selection', ['product_id' => $p->id, 'target_selection_id' => $foreign->id])->assertNotFound();
        $this->assertCount(1, session('cart'));
        $this->post('/cart/move-to-selection', ['product_id' => $p->id, 'target_selection_id' => $target->id])->assertRedirect('/cart');
        $this->assertSame(12, $target->fresh()->items[$p->id]['quantity']); $this->assertSame(2, $target->fresh()->revision);
        $this->assertSame([], session('cart'));
        $this->postJson('/cart/add', ['product_id' => $p->id, 'quantity' => 3]);
        $this->post('/cart/move-to-selection', ['product_id' => $p->id, 'target_selection_id' => $target->id])->assertSessionHas('error');
        $this->assertSame(12, $target->fresh()->items[$p->id]['quantity']);
        $this->assertSame(3, session('cart')[$p->id]['quantity']);
    }
}

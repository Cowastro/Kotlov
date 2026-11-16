<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CustomerCart;
use App\Models\CustomerSelection;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCartPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        $category = Category::firstOrCreate(['slug' => 'test-fittings'], ['name' => 'Фитинги', 'parent_id' => 0, 'is_active' => true]);
        $p = Product::create(['name' => 'Муфта '.Product::count(), 'slug' => 'mufta-'.Product::count(), 'sku' => 'M-'.Product::count(), 'category_id' => $category->id, 'price' => 25, 'is_active' => true, 'is_archived' => false]);
        $supplier = Supplier::firstOrCreate(['code' => 'test-cart'], ['name' => 'Поставщик', 'is_active' => true]);
        SupplierProduct::create(['supplier_id' => $supplier->id, 'product_id' => $p->id, 'supplier_article' => $p->sku, 'price_byn' => 20, 'in_stock' => false, 'stock_status' => 'unknown']);
        return $p;
    }

    private function items(int $count): array
    {
        $items = [];
        for ($i = 1; $i <= $count; $i++) $items[$i] = ['id' => $i, 'name' => 'Позиция '.$i, 'sku' => 'FIT-'.$i, 'slug' => 'fitting-'.$i, 'category_slug' => 'rezbovye-fitingi', 'quantity' => 1, 'price' => 10, 'pricing_type' => 'retail'];
        return $items;
    }

    public function test_cart_survives_new_session_and_stale_tab_adds_do_not_lose_positions(): void
    {
        $user = User::factory()->create();
        $first = $this->product(); $second = $this->product();
        $this->actingAs($user)->postJson('/cart/add', ['product_id' => $first->id, 'quantity' => 2])->assertOk();
        $this->assertSame(2, CustomerCart::where('user_id', $user->id)->first()->items[$first->id]['quantity']);
        $this->withSession(['cart' => [], 'cart_owner_id' => $user->id])->postJson('/cart/add', ['product_id' => $second->id])->assertOk();
        $this->assertCount(2, CustomerCart::where('user_id', $user->id)->first()->items);
        session()->flush();
        $this->getJson('/cart/data')->assertOk()->assertJsonCount(2, 'items');
        $this->postJson('/cart/clear')->assertOk();
        $this->assertSame([], CustomerCart::where('user_id', $user->id)->first()->items);
        session()->flush();
        $this->getJson('/cart/data')->assertOk()->assertJsonCount(0, 'items');
    }

    public function test_logout_does_not_delete_the_account_cart(): void
    {
        $user = User::factory()->create(); $product = $this->product();
        $this->actingAs($user)->postJson('/cart/add', ['product_id' => $product->id])->assertOk();
        $this->post('/logout')->assertRedirect();
        $this->assertCount(1, CustomerCart::where('user_id', $user->id)->first()->items);
        $this->actingAs($user)->getJson('/cart/data')->assertOk()->assertJsonCount(1, 'items');
    }

    public function test_guest_cart_merges_on_sign_in_and_another_account_cannot_inherit_it(): void
    {
        $user = User::factory()->create(); $other = User::factory()->create();
        CustomerCart::create(['user_id' => $user->id, 'items' => $this->items(2)]);
        $this->actingAs($user)->withSession(['cart' => [3 => $this->items(3)[3]]])->get('/account/selections')->assertOk();
        $this->assertCount(3, session('cart'));
        $this->actingAs($other)->get('/account/selections')->assertOk();
        $this->assertSame([], session('cart'));
    }

    public function test_150_positions_are_saved_opened_edited_and_printed_without_session_dependency(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['cart' => $this->items(150), 'cart_note' => 'Объект №1'])
            ->post('/account/selections', ['name' => 'Подбор для клиента'])->assertRedirect();
        $selection = CustomerSelection::where('user_id', $user->id)->firstOrFail();
        $this->assertCount(150, $selection->items);
        session()->flush();
        $this->get('/account/selections/'.$selection->id)->assertOk()->assertSee('FIT-150')->assertSee('Мои подборы');
        $this->get('/account/selections/'.$selection->id.'?print=1')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertSee('FIT-150')->assertSee('1 500,00');
        $this->put('/account/selections/'.$selection->id, ['name' => 'Новый подбор', 'revision' => 1, 'quantities' => [1 => 0, 2 => 3], 'note' => 'Изменено'])->assertRedirect();
        $selection->refresh();
        $this->assertCount(149, $selection->items);
        $this->assertSame(3, $selection->items[2]['quantity']);
        $this->assertSame(1, $selection->items[150]['quantity']);
        $this->assertSame(1510.0, $selection->total());
        $this->put('/account/selections/'.$selection->id, ['name' => 'Старая вкладка', 'revision' => 1, 'quantities' => [2 => 1]])->assertSessionHas('error');
        $this->assertSame('Новый подбор', $selection->fresh()->name);
    }

    public function test_opening_selection_preserves_previous_cart_and_does_not_change_the_saved_copy(): void
    {
        $user = User::factory()->create();
        $selection = CustomerSelection::create(['user_id' => $user->id, 'name' => 'Проект', 'items' => $this->items(150)]);
        CustomerCart::create(['user_id' => $user->id, 'items' => [200 => $this->items(1)[1]]]);
        $this->actingAs($user)->post('/account/selections/'.$selection->id.'/restore')->assertRedirect('/cart');
        $this->assertCount(150, CustomerCart::where('user_id', $user->id)->first()->items);
        $this->assertCount(150, $selection->fresh()->items);
        $this->assertSame(2, CustomerSelection::where('user_id', $user->id)->count());
    }

    public function test_selections_are_private_and_print_escapes_content(): void
    {
        $user = User::factory()->create(); $other = User::factory()->create();
        $selection = CustomerSelection::create(['user_id' => $user->id, 'name' => '<script>alert(1)</script>', 'items' => $this->items(1)]);
        $this->actingAs($other)->get('/account/selections/'.$selection->id)->assertNotFound();
        $this->put('/account/selections/'.$selection->id, [])->assertNotFound();
        $this->post('/account/selections/'.$selection->id.'/restore')->assertNotFound();
        $this->actingAs($user)->get('/account/selections/'.$selection->id.'?print=1')->assertOk()->assertDontSee('<script>', false);
    }

    public function test_sign_in_merges_guest_cart_into_the_durable_account_cart(): void
    {
        $user = User::factory()->create();
        CustomerCart::create(['user_id' => $user->id, 'items' => $this->items(2)]);
        $this->withSession(['cart' => [3 => $this->items(3)[3]]])->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->assertCount(3, CustomerCart::where('user_id', $user->id)->first()->items);
    }

    public function test_auto_removed_products_leave_a_recoverable_selection(): void
    {
        $user = User::factory()->create();
        $missing = [];
        foreach ($this->items(150) as $id => $item) $missing[$id + 100000] = array_replace($item, ['id' => $id + 100000]);
        CustomerCart::create(['user_id' => $user->id, 'items' => $missing]);
        $this->actingAs($user)->getJson('/cart/data')->assertOk();
        $backup = CustomerSelection::where('user_id', $user->id)->firstOrFail();
        $this->assertCount(150, $backup->items);
        $this->assertSame([], CustomerCart::where('user_id', $user->id)->first()->items);
    }

    public function test_current_cart_can_update_an_existing_selection_with_revision_protection(): void
    {
        $user = User::factory()->create();
        $selection = CustomerSelection::create(['user_id' => $user->id, 'name' => 'Проект', 'items' => $this->items(2)]);
        $this->actingAs($user)->withSession(['cart' => $this->items(150)])
            ->post('/account/selections', ['name' => 'Проект', 'selection_id' => $selection->id, 'revision' => 1])->assertRedirect();
        $this->assertCount(150, $selection->fresh()->items);
        $this->assertSame(1, CustomerSelection::where('user_id', $user->id)->count());
        $this->post('/account/selections', ['name' => 'Старая версия', 'selection_id' => $selection->id, 'revision' => 1])->assertSessionHas('error');
        $this->assertSame('Проект', $selection->fresh()->name);
    }
}

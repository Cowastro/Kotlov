<?php

namespace Tests\Feature;

use App\Models\B2bClient;
use App\Models\B2bOrder;
use App\Models\Category;
use App\Models\CrmCounterparty;
use App\Models\CustomerSelection;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientPurchaseHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function siteOrder(array $values, array $items, string $date): Order
    {
        $order = Order::create($values + ['status' => 'new', 'customer_name' => 'Клиент', 'customer_phone' => '+375291234567', 'delivery_type' => 'pickup',
            'payment_type' => 'cash', 'payment_status' => 'pending', 'subtotal' => 100, 'total' => 100]);
        foreach ($items as $item) {
            $order->items()->create($item + ['total' => $item['price'] * $item['quantity']]);
        }
        $order->forceFill(['created_at' => $date])->save();

        return $order;
    }

    public function test_a_selection_shows_what_its_client_bought_before(): void
    {
        $category = Category::create(['name' => 'История', 'slug' => 'history-cat', 'is_active' => true]);
        $pump = Product::create(['category_id' => $category->id, 'name' => 'Насос Историйный 25-60', 'slug' => 'history-pump', 'sku' => 'HIST-1', 'price' => 300, 'is_active' => true, 'is_archived' => false]);
        $valve = Product::create(['category_id' => $category->id, 'name' => 'Кран Историйный', 'slug' => 'history-valve', 'price' => 20, 'is_active' => true, 'is_archived' => false]);
        $counterparty = CrmCounterparty::create(['name' => 'ООО Историйный клиент', 'unp' => '190000001']);
        $partner = User::factory()->create(['role' => 'client', 'is_active' => true]);
        $client = B2bClient::create(['user_id' => $partner->id, 'counterparty_id' => $counterparty->id, 'status' => 'approved', 'partner_type' => 'wholesale', 'company_name' => 'ООО Историйный клиент']);

        // By the counterparty, by the УНП and by the account of the B2B client; a cancelled order and another client do not count.
        $this->siteOrder(['number' => 'H-1', 'crm_counterparty_id' => $counterparty->id], [['product_id' => $pump->id, 'product_name' => $pump->name, 'quantity' => 2, 'price' => 280]], '2026-09-01 10:00:00');
        $this->siteOrder(['number' => 'H-2', 'company_unp' => '190000001'], [['product_id' => $pump->id, 'product_name' => $pump->name, 'quantity' => 1, 'price' => 292], ['product_name' => 'Монтаж', 'quantity' => 1, 'price' => 50]], '2026-10-12 10:00:00');
        $this->siteOrder(['number' => 'H-3', 'crm_counterparty_id' => $counterparty->id, 'status' => 'cancelled'], [['product_id' => $pump->id, 'product_name' => $pump->name, 'quantity' => 9, 'price' => 1]], '2026-11-01 10:00:00');
        $this->siteOrder(['number' => 'H-4'], [['product_id' => $valve->id, 'product_name' => $valve->name, 'quantity' => 5, 'price' => 1]], '2026-11-02 10:00:00');
        $b2b = B2bOrder::create(['b2b_client_id' => $client->id, 'created_by' => $partner->id, 'number' => 'B2B-H-1', 'company_name' => 'ООО', 'status' => 'checking', 'contact_name' => 'Тест', 'contact_phone' => '+375290000000', 'total' => 36]);
        $b2b->items()->create(['product_id' => $valve->id, 'product_name' => $valve->name, 'quantity' => 3, 'price' => 12, 'total' => 36]);
        $b2b->forceFill(['created_at' => '2026-08-01 10:00:00'])->save();

        $manager = User::factory()->create(['role' => 'manager', 'is_crm_user' => true, 'is_active' => true]);
        $this->actingAs($manager);
        $this->getJson(route('b2b.workspace.clients', ['q' => '1900000']))->assertOk()->assertJsonPath('items.0.key', 'crm:'.$counterparty->id);
        $selection = CustomerSelection::create(['user_id' => $manager->id, 'name' => 'Подбор', 'items' => [], 'meta' => ['staff_workspace' => true], 'revision' => 3]);

        $this->putJson(route('b2b.workspace.client', $selection), ['client' => 'crm:'.$counterparty->id])->assertOk()
            ->assertJsonPath('client.label', 'ООО Историйный клиент')->assertJsonPath('revision', 3);
        $history = $this->getJson(route('b2b.workspace.history', $selection))->assertOk()->json();

        $this->assertSame(['H-2', 'H-1', 'B2B-H-1'], array_column($history['orders'], 'number'));
        $this->assertSame(['price' => 292, 'quantity' => 1, 'date' => '12.10.2026', 'number' => 'H-2', 'times' => 2], $history['products'][$pump->id]);
        $this->assertSame(12, $history['products'][$valve->id]['price']);
        $this->assertNull($history['orders'][0]['items'][1]['product_id']);

        $this->putJson(route('b2b.workspace.client', $selection), ['client' => null])->assertOk()->assertJsonPath('client', null);
        $this->putJson(route('b2b.workspace.client', $selection), ['client' => 'crm:999999'])->assertUnprocessable();
        $this->putJson(route('b2b.workspace.client', $selection), ['client' => 'x'])->assertUnprocessable();
    }

    public function test_only_the_owner_of_the_selection_and_staff_see_the_history(): void
    {
        $owner = User::factory()->create(['role' => 'manager', 'is_crm_user' => true, 'is_active' => true]);
        $selection = CustomerSelection::create(['user_id' => $owner->id, 'name' => 'Подбор', 'items' => [], 'meta' => ['staff_workspace' => true], 'revision' => 1]);
        $this->actingAs(User::factory()->create(['role' => 'manager', 'is_crm_user' => true, 'is_active' => true]))
            ->getJson(route('b2b.workspace.history', $selection))->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => 'client', 'is_active' => true]))->getJson(route('b2b.workspace.clients', ['q' => 'ООО']))->assertForbidden();
        $this->actingAs($owner)->get(route('b2b.catalog', ['workspace' => 1, 'mode' => 'reference']))->assertOk()
            ->assertSee('data-client-pick', false)->assertSee('b2b-client-history.js', false);
    }
}

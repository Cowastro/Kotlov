<?php

namespace Tests\Feature;

use App\Models\B2bClient;
use App\Models\B2bPricingRule;
use App\Models\Category;
use App\Models\CustomerCart;
use App\Models\CustomerSelection;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\CartSelection;
use App\Services\SelectionStockMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestArrivalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_arrivals_are_durable_deduplicated_and_do_not_change_request_revision(): void
    {
        [$user,$selection,$offer,$item] = $this->fixture();
        $this->actingAs($user)->post(route('requests.check', $selection), ['revision' => 1])->assertRedirect();
        $this->assertDatabaseCount('selection_stock_alerts', 0);
        $watch = $selection->stockWatch()->firstOrFail();
        $offer->update(['stock_quantity' => 8]);
        app(SelectionStockMonitor::class)->scan($watch);
        app(SelectionStockMonitor::class)->scan($watch);
        $this->assertDatabaseCount('selection_stock_alerts', 1);
        $alert = $watch->alerts()->firstOrFail();
        $this->assertSame(2, $alert->items[$item['id']]['newly_available']);
        $this->assertSame(2, $selection->fresh()->revision);
        $this->get(route('selections.index'))->assertOk()->assertSee('Поступления ожидаемых товаров')->assertSee('Доступно 8 шт. (+2)');
        $this->post(route('requests.arrivals.read', $alert))->assertRedirect();
        $this->assertNotNull($alert->fresh()->read_at);
        $this->assertSame(10, $selection->fresh()->items[$item['id']]['quantity']);
    }

    public function test_quick_add_preserves_cart_and_request_repeated_clicks_and_new_stock(): void
    {
        [$user,$selection,$offer,$item] = $this->fixture();
        $other = ['id' => 999, 'name' => 'Другой товар', 'price' => 3, 'quantity' => 2];
        $this->actingAs($user)->withSession(['cart' => [999 => $other], 'cart_note' => 'Не терять', 'cart_coupon' => 'TEST', CartSelection::KEY => [999]])
            ->post(route('requests.add-ready', $selection), ['revision' => 1])->assertRedirect();
        $this->assertSame(6, session('cart')[$item['id']]['quantity']);
        $this->assertSame($other, session('cart')[999]);
        $this->assertSame('Не терять', session('cart_note'));
        $this->assertSame([999], session(CartSelection::KEY));
        $this->post(route('requests.add-ready', $selection), ['revision' => 2])->assertRedirect();
        $this->assertSame(6, session('cart')[$item['id']]['quantity']);
        $offer->update(['stock_quantity' => 10]);
        $this->post(route('requests.add-ready', $selection), ['revision' => 3])->assertRedirect();
        $this->assertSame(10, session('cart')[$item['id']]['quantity']);
        $this->assertSame(10, $selection->fresh()->items[$item['id']]['quantity']);
        $stored = CustomerCart::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(10, $stored->items[$item['id']]['quantity']);
        $this->assertSame(10, $stored->items[$item['id']]['request_origins'][$selection->id]);
        $this->assertDatabaseCount('b2b_order_reservations', 0);
        $this->postJson(route('requests.add-ready', $selection), ['revision' => 1])->assertConflict();
        $this->assertSame(10, CustomerCart::where('user_id', $user->id)->first()->items[$item['id']]['quantity']);
    }

    public function test_other_sources_and_unauthorized_users_are_not_overwritten_or_exposed(): void
    {
        [$user,$selection,$offer,$item] = $this->fixture();
        $existing = array_replace($item, ['supply_option' => 'supplier:999', 'quantity' => 3]);
        $this->actingAs($user)->withSession(['cart' => [$item['id'] => $existing]])->post(route('requests.add-ready', $selection), ['revision' => 1])->assertRedirect();
        $this->assertSame($existing, session('cart')[$item['id']]);
        $watch = $selection->stockWatch()->firstOrFail();
        $offer->update(['stock_quantity' => 10]);
        app(SelectionStockMonitor::class)->scan($watch);
        $alert = $watch->alerts()->firstOrFail();
        $foreign = User::factory()->create(['is_active' => true, 'role' => 'admin']);
        $this->actingAs($foreign)->post(route('requests.watch', $selection), ['enabled' => 1])->assertNotFound();
        $this->post(route('requests.arrivals.read', $alert))->assertNotFound();
        $this->post(route('requests.add-ready', $selection), ['revision' => 2])->assertNotFound();
        $this->get(route('selections.index'))->assertDontSee('Доступно 10 шт. (+4)');
    }

    public function test_disabled_watch_and_quantity_edits_do_not_create_false_arrivals(): void
    {
        [$user,$selection,$offer,$item] = $this->fixture();
        $this->actingAs($user)->post(route('requests.watch', $selection), ['enabled' => 1])->assertRedirect();
        $watch = $selection->stockWatch()->firstOrFail();
        $items = $selection->items;
        $items[$item['id']]['quantity'] = 20;
        $selection->update(['items' => $items]);
        $offer->update(['stock_quantity' => 10]);
        app(SelectionStockMonitor::class)->scan($watch);
        $this->assertDatabaseCount('selection_stock_alerts', 0);
        $this->post(route('requests.watch', $selection), ['enabled' => 0])->assertRedirect();
        $offer->update(['stock_quantity' => 15]);
        $this->post(route('requests.check', $selection), ['revision' => 1])->assertRedirect();
        app(SelectionStockMonitor::class)->scan($watch);
        $this->assertFalse($watch->fresh()->enabled);
        $this->assertDatabaseCount('selection_stock_alerts', 0);
    }

    public function test_two_requests_share_stock_capacity_and_manual_reductions_are_respected(): void
    {
        [$user,$selection,$offer,$item] = $this->fixture(10);
        $item['quantity'] = 6;
        $selection->update(['items' => [$item['id'] => $item]]);
        $second = CustomerSelection::create(['user_id' => $user->id, 'name' => 'Другой объект', 'revision' => 1, 'items' => [$item['id'] => array_replace($item, ['quantity' => 8])]]);
        $this->actingAs($user)->post(route('requests.add-ready', $selection), ['revision' => 1])->assertRedirect();
        $this->post(route('requests.add-ready', $second), ['revision' => 1])->assertRedirect();
        $this->assertSame(10, session('cart')[$item['id']]['quantity']);
        $this->assertSame(4, session('cart')[$item['id']]['request_origins'][$second->id]);
        $this->post(route('requests.add-ready', $second), ['revision' => 2])->assertRedirect();
        $this->assertSame(10, session('cart')[$item['id']]['quantity']);
        $cart = CustomerCart::where('user_id', $user->id)->firstOrFail();
        $items = $cart->items;
        $items[$item['id']]['quantity'] = 2;
        $cart->update(['items' => $items]);
        $this->post(route('requests.add-ready', $second), ['revision' => 3])->assertRedirect();
        $this->assertSame(10, session('cart')[$item['id']]['quantity']);
        $this->assertSame(8, session('cart')[$item['id']]['request_origins'][$second->id]);
    }

    public function test_alert_transfer_is_scoped_and_rechecks_stock_instead_of_trusting_notification(): void
    {
        [$user,$selection,$offer,$item] = $this->fixture();
        $this->actingAs($user)->post(route('requests.watch', $selection), ['enabled' => 1])->assertRedirect();
        $offer->update(['stock_quantity' => 10]);
        $this->artisan('requests:check-arrivals')->assertSuccessful();
        $alert = $selection->stockWatch->alerts()->firstOrFail();
        $offer->update(['stock_quantity' => 3]);
        $this->post(route('requests.add-ready', $selection), ['revision' => 1, 'alert_id' => $alert->id])->assertRedirect();
        $this->assertSame(3, session('cart')[$item['id']]['quantity']);
        $this->assertNull($alert->fresh()->read_at);
        $offer->update(['last_stock_synced_at' => now()->subDays(3)]);
        $this->post(route('requests.add-ready', $selection), ['revision' => 2, 'alert_id' => $alert->id])->assertRedirect();
        $this->assertSame(3, session('cart')[$item['id']]['quantity']);
        $this->assertSame(10, $selection->fresh()->items[$item['id']]['quantity']);
    }

    public function test_restored_request_is_not_added_again_and_watch_does_not_enable_itself_after_opt_out(): void
    {
        [$user,$selection,$offer,$item] = $this->fixture(100);
        $this->actingAs($user)->withSession(['cart' => [$item['id'] => $item], 'cart_selection_id' => $selection->id])
            ->post(route('requests.add-ready', $selection), ['revision' => 1])->assertRedirect();
        $this->assertSame(10, session('cart')[$item['id']]['quantity']);
        $this->assertSame(10, $selection->fresh()->items[$item['id']]['quantity']);
    }

    private function fixture(int $stock = 6): array
    {
        $user = User::factory()->create(['is_active' => true]);
        B2bClient::create(['user_id' => $user->id, 'status' => 'approved', 'partner_type' => 'wholesale',
            'company_name' => 'Тест заявок', 'allow_all_partner_suppliers' => true]);
        $category = Category::create(['name' => 'Насосы', 'slug' => 'request-pumps', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Склад', 'code' => 'request-test', 'is_active' => true,
            'b2b_partner_enabled' => true, 'b2b_require_positive_stock' => true, 'b2b_stock_max_age_hours' => 24]);
        $supplier->b2bPricingRules()->create(['name' => 'Цена', 'scope_type' => B2bPricingRule::SCOPE_SUPPLIER,
            'calculation_type' => B2bPricingRule::TYPE_TARGET_MARGIN, 'value' => 20, 'minimum_profit_byn' => 0,
            'minimum_margin_percent' => 10, 'rounding_step_byn' => 1, 'minimum_retail_discount_percent' => 0,
            'priority' => 100, 'status' => B2bPricingRule::STATUS_ACTIVE]);
        $product = Product::withoutEvents(fn () => Product::create(['category_id' => $category->id, 'sku' => 'PUMP-1', 'slug' => 'request-pump',
            'name' => 'Насос для объекта', 'price' => 200, 'is_active' => true, 'is_archived' => false]));
        $offer = SupplierProduct::create(['supplier_id' => $supplier->id, 'product_id' => $product->id,
            'supplier_article' => 'SUP-1', 'price_byn' => 100, 'stock_quantity' => $stock, 'in_stock' => $stock > 0,
            'stock_status' => $stock > 0 ? 'in_stock' : 'out_of_stock', 'match_status' => 'matched',
            'last_stock_synced_at' => now(), 'last_synced_at' => now()]);
        $item = ['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'slug' => $product->slug,
            'category_slug' => $category->slug, 'price' => 120, 'quantity' => 10, 'pricing_type' => 'b2b',
            'supplier_product_id' => $offer->id, 'supply_option' => 'supplier:'.$offer->id, 'selection_note' => 'Этаж 2'];
        $selection = CustomerSelection::create(['user_id' => $user->id, 'name' => 'Объект №1', 'revision' => 1, 'items' => [$product->id => $item]]);

        return [$user, $selection, $offer, $item];
    }
}

<?php

namespace Tests\Feature;

use App\Models\B2bClient;
use App\Models\B2bClientMember;
use App\Models\B2bOrder;
use App\Models\B2bOrderReservation;
use App\Models\Category;
use App\Models\CommercialDocument;
use App\Models\CustomerSelection;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\B2bOrders\B2bStockLocator;
use App\Services\StaffCatalogWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StaffSelectionReservationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Mail::fake();
        Notification::fake();
        $manager = User::factory()->create(['role' => 'manager', 'is_crm_user' => true, 'is_active' => true]);
        $owner = User::factory()->create(['role' => 'client', 'is_active' => true]);
        $client = B2bClient::create(['user_id' => $owner->id, 'status' => 'approved', 'partner_type' => 'wholesale', 'company_name' => 'Компания для резерва', 'contact_name' => 'Клиент', 'contact_phone' => '123']);
        $category = Category::create(['slug' => 'pilot-reserve', 'name' => 'Насосы', 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Товар пилотной заявки', 'sku' => 'PILOT-1', 'slug' => 'pilot-reserve-product', 'price' => 100, 'is_active' => true, 'is_archived' => false]);
        $supplier = Supplier::create(['code' => 'pilot-supplier', 'name' => 'PRIVATE-SUPPLIER', 'is_active' => true]);
        $source = SupplierProduct::create(['supplier_id' => $supplier->id, 'product_id' => $product->id, 'supplier_article' => 'PILOT-1', 'supplier_name' => $product->name, 'price_byn' => 37.13, 'stock_quantity' => 5, 'in_stock' => true, 'stock_status' => 'in_stock', 'match_status' => 'matched', 'last_synced_at' => now(), 'last_stock_synced_at' => now()]);
        $offer = app(StaffCatalogWorkspace::class)->offers($product->fresh(), $manager)->first();
        $draft = CustomerSelection::create(['user_id' => $manager->id, 'name' => 'Подбор для партнёра', 'revision' => 1, 'meta' => ['staff_workspace' => true], 'items' => [$product->id => ['id' => $product->id, 'sku' => $product->sku, 'name' => $product->name, 'price' => 88.88, 'quantity' => 3, 'workspace_purchase' => $offer, 'workspace_note' => 'PRIVATE-NOTE', 'client_pricing' => ['base_price' => 100, 'discount_percent' => 11.12]]]]);
        $this->actingAs($manager);

        return [$manager, $owner->fresh(), $client, $product, $source, $draft];
    }

    private function publish(CustomerSelection $draft, B2bClient $client, array $data = [])
    {
        return $this->postJson(route('b2b.selections.reserve', $draft), array_replace(['revision' => 1, 'b2b_client_id' => $client->id, 'hold_days' => 3, 'client_message' => 'Предложение для вашей компании'], $data));
    }

    public function test_manager_publishes_agreed_prices_and_client_confirms_without_invoice_mail_or_1c(): void
    {
        [$manager, $owner, $client, $product, $source, $draft] = $this->fixture();
        $this->publish($draft, $client)->assertRedirect(route('b2b.selections.show', $draft));
        $order = B2bOrder::sole();
        $this->assertSame($client->id, $order->b2b_client_id);
        $this->assertSame($manager->id, $order->created_by);
        $this->assertSame('awaiting_client', $order->status);
        $this->assertEquals(266.64, $order->total);
        $line = $order->items()->sole();
        $this->assertEquals(88.88, $line->price);
        $this->assertEquals(37.13, $line->purchase_price);
        $this->assertSame(1, B2bOrderReservation::active()->count());
        $this->assertSame(3, $draft->fresh()->items[$product->id]['quantity']);
        $this->assertEquals(2, app(B2bStockLocator::class)->poolsForProduct($product->fresh())->firstWhere('key', 'supplier:'.$source->id)['free']);
        $this->actingAs($owner)->get(route('b2b.cabinet'))->assertOk()->assertViewHas('pendingStaffReservations', 1)->assertSee('Заявки от менеджера');
        $this->get(route('b2b.cabinet', ['section' => 'orders']))->assertOk()->assertSee($order->number)->assertSee('88,88')->assertSee('Подтвердить заявку и цены')->assertDontSee('37,13')->assertDontSee('PRIVATE-NOTE')->assertDontSee('PRIVATE-SUPPLIER');
        $this->post(route('b2b.cabinet.orders.confirm', $order))->assertRedirect();
        $this->post(route('b2b.cabinet.orders.confirm', $order))->assertRedirect();
        $this->assertSame('client_confirmed', $order->fresh()->status);
        $this->assertSame(1, $order->events()->where('event', 'client_confirmed')->count());
        $this->assertSame(0, CommercialDocument::count());
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Notification::assertNothingSent();
    }

    public function test_double_submit_is_idempotent_and_second_reserve_cannot_take_the_same_stock(): void
    {
        [, , $client, $product, , $draft] = $this->fixture();
        $this->publish($draft, $client)->assertRedirect();
        $this->publish($draft, $client)->assertRedirect();
        $this->assertSame(1, B2bOrder::count());
        $this->assertSame(1, B2bOrderReservation::count());
        $second = $draft->replicate();
        $second->meta = ['staff_workspace' => true];
        $second->revision = 1;
        $second->save();
        $this->publish($second, $client)->assertUnprocessable();
        $this->assertSame(1, B2bOrder::count());
        $this->assertSame(1, B2bOrderReservation::count());
        $this->assertArrayNotHasKey('published_b2b_order_id', $second->fresh()->meta);
    }

    public function test_expired_reserve_frees_stock_and_cannot_be_confirmed(): void
    {
        [, $owner, $client, $product, $source, $draft] = $this->fixture();
        $this->publish($draft, $client, ['hold_days' => 1])->assertRedirect();
        $order = B2bOrder::sole();
        $this->travel(2)->days();
        $source->update(['last_synced_at' => now(), 'last_stock_synced_at' => now()]);
        $this->assertSame(0, B2bOrderReservation::active()->count());
        $this->assertEquals(5, app(B2bStockLocator::class)->poolsForProduct($product->fresh())->firstWhere('key', 'supplier:'.$source->id)['free']);
        $this->artisan('b2b:expire-reservations')->assertSuccessful();
        $this->artisan('b2b:expire-reservations')->assertSuccessful();
        $this->assertSame('released', $order->reservations()->sole()->status);
        $this->assertSame(1, $order->events()->where('event', 'reservation_expired')->count());
        $this->actingAs($owner)->postJson(route('b2b.cabinet.orders.confirm', $order))->assertUnprocessable();
        $this->get(route('b2b.cabinet', ['section' => 'orders']))->assertOk()->assertSee('Срок резерва истёк')->assertDontSee('Подтвердить заявку и цены');
        $this->assertSame('awaiting_client', $order->fresh()->status);
    }

    public function test_manager_can_cancel_reserve_and_other_company_cannot_see_or_confirm_it(): void
    {
        [$manager, , $client, , , $draft] = $this->fixture();
        $this->publish($draft, $client)->assertRedirect();
        $order = B2bOrder::sole();
        $other = User::factory()->create(['role' => 'client', 'is_active' => true]);
        B2bClient::create(['user_id' => $other->id, 'status' => 'approved', 'partner_type' => 'wholesale', 'company_name' => 'Другая компания']);
        $this->flushSession();
        $this->actingAs($other->fresh())->get(route('b2b.cabinet', ['section' => 'orders']))->assertOk()->assertViewHas('orders', fn ($orders) => $orders->isEmpty())->assertDontSee($order->number);
        $this->post(route('b2b.cabinet.orders.confirm', $order))->assertNotFound();
        $this->post(route('b2b.selections.release-reserve', $draft))->assertForbidden();
        $this->actingAs($manager)->post(route('b2b.selections.release-reserve', $draft))->assertRedirect();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(0, B2bOrderReservation::active()->count());
    }

    public function test_unapproved_client_stale_stock_foreign_selection_and_stale_revision_are_rejected(): void
    {
        [$manager, , $client, , $source, $draft] = $this->fixture();
        $this->publish($draft, $client, ['revision' => 99])->assertConflict();
        $client->update(['status' => 'pending']);
        $this->publish($draft, $client)->assertNotFound();
        $client->update(['status' => 'approved']);
        $source->update(['last_synced_at' => now()->subDays(10), 'last_stock_synced_at' => now()->subDays(10)]);
        $this->publish($draft, $client)->assertUnprocessable();
        $other = User::factory()->create(['role' => 'manager', 'is_crm_user' => true, 'is_active' => true]);
        $this->actingAs($other);
        $this->publish($draft, $client)->assertNotFound();
        $this->assertSame(0, B2bOrder::count());
    }

    public function test_company_viewer_cannot_confirm_and_client_cannot_publish_manager_reservations(): void
    {
        [, $owner, $client, , , $draft] = $this->fixture();
        $this->publish($draft, $client)->assertRedirect();
        $viewer = User::factory()->create(['role' => 'client', 'client_type' => 'wholesale', 'b2b_approved' => true, 'is_active' => true]);
        B2bClientMember::create(['b2b_client_id' => $client->id, 'user_id' => $viewer->id, 'role' => 'viewer', 'is_active' => true]);
        $this->actingAs($viewer)->get(route('b2b.cabinet', ['section' => 'orders']))->assertOk()->assertSee(B2bOrder::sole()->number)->assertDontSee('Подтвердить заявку и цены');
        $this->post(route('b2b.cabinet.orders.confirm', B2bOrder::sole()))->assertNotFound();
        $this->actingAs($owner);
        $this->publish($draft, $client)->assertForbidden();
    }

    public function test_unchecked_unavailable_items_stay_in_draft_and_are_not_reserved(): void
    {
        [, , $client, , , $draft] = $this->fixture();
        $items = $draft->items;
        $items[999999] = ['id' => 999999, 'name' => 'Ожидаемый товар', 'price' => 0, 'quantity' => 8];
        $draft->update(['items' => $items, 'meta' => ['staff_workspace' => true, 'desk_excluded_ids' => [999999]]]);
        $this->publish($draft, $client)->assertRedirect();
        $this->assertSame(1, B2bOrder::sole()->items()->count());
        $this->assertCount(2, $draft->fresh()->items);
        $this->publish($draft, $client, ['b2b_client_id' => $client->id + 100])->assertUnprocessable();
        $this->assertSame(1, B2bOrder::count());
    }

    public function test_copy_of_published_selection_can_be_used_for_a_new_proposal(): void
    {
        [, , $client, , , $draft] = $this->fixture();
        $this->publish($draft, $client)->assertRedirect();
        $this->post(route('selections.duplicate', $draft))->assertRedirect();
        $copy = CustomerSelection::where('id', '!=', $draft->id)->sole();
        $this->assertArrayNotHasKey('published_b2b_order_id', $copy->meta);
        $this->assertSame($draft->items, $copy->items);
        $this->post(route('b2b.selections.release-reserve', $draft))->assertRedirect();
        $this->publish($copy, $client)->assertRedirect();
        $this->assertSame(2, B2bOrder::count());
        $this->assertSame(1, B2bOrderReservation::active()->count());
    }
}

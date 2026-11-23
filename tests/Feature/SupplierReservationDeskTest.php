<?php

namespace Tests\Feature;

use App\Filament\Pages\SupplierReservations;
use App\Models\Category;
use App\Models\CrmMailbox;
use App\Models\CrmMailThread;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierOrderRequest;
use App\Models\User;
use App\Services\Orders\SupplierReservationDesk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierReservationDeskTest extends TestCase
{
    use RefreshDatabase;

    public function test_desk_excludes_accounting_and_does_not_confuse_closed_mail_with_confirmation(): void
    {
        [$manager, $supplier, $order, $product, $mailbox] = $this->fixture();
        $request = SupplierOrderRequest::create(['order_id' => $order->id, 'supplier_id' => $supplier->id, 'created_by' => $manager->id,
            'supplier_name' => $supplier->name, 'number' => 'SR-DESK', 'route' => 'supplier_purchase', 'status' => 'sent', 'transfer_mode' => 'email', 'item_count' => 1]);
        $item = $order->items()->create(['product_id' => $product->id, 'product_name' => 'Насос для резерва', 'product_sku' => 'DESK-001', 'quantity' => 2, 'price' => 15, 'total' => 30]);
        $request->items()->create(['order_item_id' => $item->id, 'product_name' => 'Насос для резерва', 'product_sku' => 'DESK-001', 'quantity' => 2, 'purchase_price' => 10, 'purchase_total' => 20]);
        CrmMailThread::create(['participant_email' => 'supplier@example.test', 'mailbox_id' => $mailbox->id, 'supplier_order_request_id' => $request->id, 'status' => 'closed', 'subject' => 'Закрытая переписка', 'thread_key' => 'request']);
        $accounting = CrmMailThread::create(['participant_email' => 'accounting@example.test', 'mailbox_id' => $mailbox->id, 'order_id' => $order->id, 'status' => 'waiting_customer', 'subject' => 'Только бухгалтерия', 'thread_key' => 'accounting']);
        $accounting->messages()->create(['mailbox_id' => $mailbox->id, 'from_email' => 'supplier@example.test', 'direction' => 'draft', 'metadata' => ['source' => 'order_accounting_payment']]);
        $productThread = $this->productThread($manager, $supplier, $product, $mailbox);
        $desk = app(SupplierReservationDesk::class);
        $index = $desk->query($manager)->get();
        $this->assertCount(2, $index);
        $rows = $desk->rows($index, $manager)->keyBy('key');
        $this->assertSame('waiting', $rows['request-'.$request->id]['state']);
        $this->assertStringContainsString('подтверждение резерва не зафиксировано', $rows['request-'.$request->id]['next']);
        $this->assertSame(20, (int) $rows['request-'.$request->id]['total']);
        $this->assertSame('closed', $rows['product-'.$productThread->id]['state']);
        $this->assertNull($rows['product-'.$productThread->id]['total']);
        $this->assertCount(1, $desk->query($manager, 'DESK-001')->get());
        $this->get('/admin/supplier-reservations')->assertOk()->assertSeeText('Резервы поставщиков')->assertDontSeeText('Только бухгалтерия');
        Livewire::test(SupplierReservations::class)->set('state', 'archive')->assertSee('Закрыт без подтверждения')
            ->set('search', 'нет такого товара')->assertSee('Резервов в этой группе нет')->call('resetFilters')->assertSet('state', 'active');
        Livewire::test(SupplierReservations::class)->callAction('recordResponse', arguments: ['request' => $request->id], data: ['status' => 'acknowledged', 'note' => 'Подтверждено 2 шт., забрать завтра'])->assertHasNoActionErrors();
        $this->assertSame('confirmed', $desk->query($manager)->where('kind', 'request')->first()->state);
        $this->assertSame('closed', $productThread->fresh()->status);
    }

    public function test_personal_mailbox_and_customer_access_remain_restricted(): void
    {
        [$manager, $supplier, , $product, $mailbox] = $this->fixture();
        $other = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $mailbox->users()->attach($other);
        $this->productThread($manager, $supplier, $product, $mailbox);
        $this->assertSame(0, app(SupplierReservationDesk::class)->query($manager)->count());
        $this->assertSame(1, app(SupplierReservationDesk::class)->query($other)->count());
        $this->actingAs(User::factory()->create(['role' => 'client', 'is_active' => true]))->get('/admin/supplier-reservations')->assertForbidden();
    }

    private function fixture(): array
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $this->actingAs($manager);
        $supplier = Supplier::create(['code' => 'desk', 'name' => 'Поставщик резерва', 'is_active' => true]);
        $category = Category::create(['name' => 'Резервы', 'slug' => 'desk', 'parent_id' => 0, 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Товар без цены', 'slug' => 'desk', 'sku' => 'DESK-002', 'price' => 100, 'is_active' => true]);
        $order = Order::create(['number' => 'ORD-DESK', 'status' => 'new', 'customer_name' => 'Клиент', 'customer_phone' => '+375290000000', 'delivery_type' => 'pickup', 'payment_type' => 'cash', 'payment_status' => 'pending', 'subtotal' => 20, 'total' => 20]);
        $mailbox = CrmMailbox::create(['email' => 'order@kotlov.by', 'name' => 'Заказы', 'username' => 'order@kotlov.by', 'is_enabled' => true]);

        return [$manager, $supplier, $order, $product, $mailbox];
    }

    private function productThread($manager, $supplier, $product, $mailbox): CrmMailThread
    {
        $thread = CrmMailThread::create(['participant_email' => 'supplier@example.test', 'mailbox_id' => $mailbox->id, 'product_id' => $product->id, 'supplier_id' => $supplier->id, 'assigned_user_id' => $manager->id, 'status' => 'closed', 'subject' => 'Резерв товара', 'thread_key' => 'product']);
        $thread->messages()->create(['mailbox_id' => $mailbox->id, 'from_email' => 'supplier@example.test', 'direction' => 'outgoing', 'metadata' => ['source' => 'supplier_product_reserve', 'quantity' => 3, 'purchase_price' => null]]);

        return $thread;
    }
}

<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Category;
use App\Models\CrmMailbox;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierOrderRequest;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\Orders\OrderPortal;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderSupplierReserveQuickActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_reserve_mailbox_is_available_next_to_orders_in_navigation(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        CrmMailbox::query()->updateOrCreate(['email' => 'order@kotlov.by'], [
            'name' => 'Заказы KOTLOV',
            'username' => 'order@kotlov.by',
            'is_enabled' => true,
        ]);

        $this->actingAs($manager)
            ->get(OrderResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Резервы поставщиков');
    }

    public function test_order_row_offers_supplier_selection_for_a_reserve_request(): void
    {
        [$manager, $supplier, $order, $item] = $this->fixture();

        $this->actingAs($manager);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListOrders::class)
            ->assertTableActionVisible('requestSupplierReserve', $order)
            ->mountTableAction('requestSupplierReserve', $order)
            ->assertSee($item->product_name)
            ->assertSee($supplier->name)
            ->assertSee('reserve-order@example.com');
    }

    public function test_order_table_shows_reserve_response_status_immediately(): void
    {
        [$manager, $supplier, $order] = $this->fixture();

        $request = SupplierOrderRequest::query()->create([
            'order_id' => $order->id,
            'supplier_id' => $supplier->id,
            'created_by' => $manager->id,
            'number' => 'SR-QUICK-STATUS',
            'route' => 'supplier_purchase',
            'status' => 'sent',
            'supplier_name' => $supplier->name,
            'supplier_contact' => $supplier->contact,
            'item_count' => 1,
            'sent_at' => now(),
        ]);

        $order->load('supplierOrderRequests');
        $this->assertSame('Ждём ответа', OrdersTable::supplierReserveStatus($order));
        $this->assertSame('warning', OrdersTable::supplierReserveColor($order));

        $request->update(['status' => 'acknowledged', 'acknowledged_at' => now()]);
        $order->unsetRelation('supplierOrderRequests')->load('supplierOrderRequests');

        $this->assertSame('Резерв подтверждён', OrdersTable::supplierReserveStatus($order));
        $this->assertSame('success', OrdersTable::supplierReserveColor($order));
        $this->assertSame($supplier->name, OrdersTable::supplierReserveDescription($order));
    }

    public function test_order_row_action_opens_existing_online_order_link(): void
    {
        [$manager, , $order] = $this->fixture();
        $portal = app(OrderPortal::class);
        $link = $portal->publish($order, $manager);

        $this->actingAs($manager);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListOrders::class)
            ->assertTableActionVisible('openOrderOnline', $order)
            ->assertTableActionHasUrl('openOrderOnline', $portal->url($link), $order);
    }

    /** @return array{User, Supplier, Order, OrderItem} */
    private function fixture(): array
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $supplier = Supplier::query()->create([
            'code' => 'quick-order-reserve',
            'name' => 'Поставщик быстрого резерва',
            'contact' => 'reserve-order@example.com',
            'is_active' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Быстрый резерв заказа',
            'slug' => 'quick-order-reserve',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Товар для быстрого резерва',
            'slug' => 'quick-order-reserve-product',
            'sku' => 'QUICK-RESERVE-001',
            'price' => 150,
            'currency' => 'BYN',
            'is_active' => true,
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_article' => 'SUP-QUICK-001',
            'price_byn' => 100,
            'stock_quantity' => 2,
        ]);
        $order = Order::query()->create([
            'number' => 'ORD-QUICK-RESERVE',
            'status' => 'new',
            'customer_name' => 'Клиент резерва',
            'customer_phone' => '+375291112233',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 150,
            'total' => 150,
        ]);
        $item = OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'price' => 150,
            'quantity' => 1,
            'total' => 150,
        ]);

        return [$manager, $supplier, $order, $item];
    }
}

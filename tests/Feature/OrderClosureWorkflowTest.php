<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderClosureWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_close_a_confirmed_unpaid_order_with_a_structured_reason(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $order = $this->order('confirmed');

        Livewire::actingAs($manager)
            ->test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionVisible('closeAsIrrelevant')
            ->callAction('closeAsIrrelevant', [
                'reason_type' => 'customer_refusal',
                'reason' => 'Клиент купил товар в другом месте',
            ])
            ->assertHasNoActionErrors();

        $order->refresh();

        $this->assertSame('cancelled', $order->status);
        $this->assertSame('Отказ клиента: Клиент купил товар в другом месте', $order->archive_reason);
        $this->assertSame($manager->id, $order->archived_by);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'user_id' => $manager->id,
            'status_from' => 'confirmed',
            'status_to' => 'cancelled',
            'comment' => $order->archive_reason,
        ]);
    }

    public function test_edit_page_has_safe_closure_action_instead_of_deletion(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $order = $this->order('processing');

        Livewire::actingAs($manager)
            ->test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionVisible('closeAsIrrelevant')
            ->assertActionDoesNotExist('delete');
    }

    public function test_paid_or_already_shipped_orders_cannot_use_simple_refusal_flow(): void
    {
        $paid = $this->order('confirmed', 'paid');
        $shipped = $this->order('shipped');

        $this->assertFalse($paid->canBeClosedAsIrrelevant());
        $this->assertFalse($shipped->canBeClosedAsIrrelevant());
    }

    public function test_order_with_catalog_product_has_visible_public_site_action(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $category = Category::query()->create([
            'parent_id' => 0,
            'name' => 'Двери',
            'slug' => 'dveri-test',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Тестовая дверь',
            'slug' => 'testovaya-dver',
            'sku' => 'DOOR-TEST',
            'price' => 284,
            'is_active' => true,
        ]);
        $order = $this->order('processing');

        OrderItem::withoutEvents(fn () => OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'price' => 284,
            'quantity' => 1,
            'total' => 284,
        ]));

        Livewire::actingAs($manager)
            ->test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionVisible('viewProductOnSite')
            ->assertSee('Посмотреть на сайте')
            ->assertSee('Посмотреть');

        $item = $order->items()->firstOrFail();

        $this->assertSame(url('/dveri-test/testovaya-dver'), $item->publicProductUrl());
    }

    private function order(string $status, string $paymentStatus = 'pending'): Order
    {
        return Order::query()->create([
            'number' => 'ORD-CLOSE-'.strtoupper($status).'-'.uniqid(),
            'status' => $status,
            'customer_name' => 'Тестовый клиент',
            'customer_phone' => '+375291110000',
            'delivery_type' => 'courier',
            'payment_type' => 'cash',
            'payment_status' => $paymentStatus,
            'subtotal' => 100,
            'total' => 100,
        ]);
    }
}

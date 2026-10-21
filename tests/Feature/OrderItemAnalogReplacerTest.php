<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Orders\OrderItemAnalogReplacer;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderItemAnalogReplacerTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_replaces_problem_item_with_analog_and_order_totals_are_recalculated(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $category = Category::query()->create([
            'name' => 'Насосы',
            'slug' => 'analog-pumps',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $source = $this->product($category, 'Исходный насос', 'PUMP-OLD', 200);
        $analog = $this->product($category, 'Доступный аналог', 'PUMP-NEW', 250);
        $order = $this->order('checking');
        $item = OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $source->id,
            'product_name' => $source->name,
            'product_sku' => $source->sku,
            'price' => 200,
            'quantity' => 2,
            'total' => 400,
            'fulfillment_route' => 'supplier_purchase',
            'fulfillment_supplier_name' => 'Старый поставщик',
            'fulfillment_availability_status' => 'unavailable',
            'fulfillment_confirmed_by' => $manager->id,
            'fulfillment_confirmed_at' => now(),
        ]);

        $result = app(OrderItemAnalogReplacer::class)->replace($order, $item->id, $analog->id, $manager);

        $item->refresh();
        $order->refresh();

        $this->assertSame('Исходный насос', $result['old_name']);
        $this->assertSame('Доступный аналог', $result['new_name']);
        $this->assertSame($analog->id, $item->product_id);
        $this->assertSame(2, $item->quantity);
        $this->assertSame('250.00', $item->price);
        $this->assertSame('500.00', $item->total);
        $this->assertNull($item->fulfillment_confirmed_at);
        $this->assertNull($item->fulfillment_availability_status);
        $this->assertSame('500.00', $order->subtotal);
        $this->assertSame('500.00', $order->total);
        $this->assertNotNull($order->corrected_at);
        $this->assertSame($manager->id, $order->corrected_by);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'status_from' => 'checking',
            'status_to' => 'checking',
        ]);
    }

    public function test_analog_cannot_be_selected_after_availability_confirmation(): void
    {
        $category = Category::query()->create([
            'name' => 'Котлы',
            'slug' => 'analog-boilers',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $source = $this->product($category, 'Исходный котёл', 'BOILER-OLD', 1000);
        $analog = $this->product($category, 'Другой котёл', 'BOILER-NEW', 1100);
        $order = $this->order('confirmed');
        $item = OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $source->id,
            'product_name' => $source->name,
            'product_sku' => $source->sku,
            'price' => 1000,
            'quantity' => 1,
            'total' => 1000,
        ]);

        $this->expectException(DomainException::class);
        app(OrderItemAnalogReplacer::class)->replace($order, $item->id, $analog->id, null);
    }

    private function product(Category $category, string $name, string $sku, float $price): Product
    {
        return Product::query()->create([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => strtolower($sku),
            'sku' => $sku,
            'price' => $price,
            'is_active' => true,
            'is_archived' => false,
        ]);
    }

    private function order(string $status): Order
    {
        return Order::query()->create([
            'number' => 'ORD-ANALOG-'.strtoupper($status).'-'.uniqid(),
            'status' => $status,
            'customer_name' => 'Клиент',
            'customer_phone' => '+375290000000',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 0,
            'total' => 0,
        ]);
    }
}

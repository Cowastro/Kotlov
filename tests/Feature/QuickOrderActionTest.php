<?php

namespace Tests\Feature;

use App\Events\NewOrderCreated;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Orders\QuickOrderService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class QuickOrderActionTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $sku, float $price): Product
    {
        $category = Category::query()->firstOrCreate(['slug' => 'quick-order-test'], ['name' => 'Быстрый заказ', 'is_active' => true]);

        return Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Котёл '.$sku,
            'slug' => 'quick-order-'.strtolower($sku),
            'sku' => $sku,
            'price' => $price,
            'is_active' => true,
        ]);
    }

    public function test_service_creates_order_in_work_with_items_delivery_and_manager(): void
    {
        Event::fake([NewOrderCreated::class]);
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true, 'name' => 'Анна']);
        $boiler = $this->product('QO-1', 1500);
        $pump = $this->product('QO-2', 200);

        $order = app(QuickOrderService::class)->create([
            'customer_name' => ' Иван ',
            'customer_phone' => '+375291112233',
            'items' => [
                ['product_id' => $boiler->id, 'quantity' => 1, 'price' => 1450],
                ['product_id' => $pump->id, 'quantity' => 2, 'price' => 200],
            ],
            'delivery_type' => 'courier',
            'delivery_city' => 'Минск',
            'delivery_address' => 'ул. Ленина, 1',
            'delivery_price' => 30,
            'payment_type' => 'cash',
            'company_name' => 'Не нужна',
        ], $manager);

        $order->refresh();
        $this->assertSame('processing', $order->status);
        $this->assertSame('Иван', $order->customer_name);
        $this->assertSame(1850.0, (float) $order->subtotal);
        $this->assertSame(1880.0, (float) $order->total);
        $this->assertSame('Минск', $order->delivery_city);
        $this->assertNull($order->company_name);
        $this->assertSame($manager->id, $order->manager_id);
        $this->assertCount(2, $order->items);
        $this->assertSame(400.0, (float) $order->items->firstWhere('product_id', $pump->id)->total);
        $this->assertSame('QO-1', $order->items->firstWhere('product_id', $boiler->id)->product_sku);
        Event::assertDispatched(NewOrderCreated::class);
    }

    public function test_pickup_ignores_delivery_price_and_empty_items_are_rejected(): void
    {
        Event::fake([NewOrderCreated::class]);
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $product = $this->product('QO-3', 100);

        $order = app(QuickOrderService::class)->create([
            'customer_name' => 'Пётр', 'customer_phone' => '+375290000001',
            'items' => [['product_id' => $product->id, 'quantity' => 3, 'price' => 100]],
            'delivery_type' => 'pickup', 'delivery_price' => 50, 'delivery_address' => 'x',
            'payment_type' => 'invoice', 'company_name' => 'ООО Тепло', 'company_unp' => '123456789',
            'status' => 'confirmed',
        ], $manager);

        $this->assertSame(300.0, (float) $order->total);
        $this->assertNull($order->delivery_address);
        $this->assertSame('ООО Тепло', $order->company_name);
        $this->assertSame('confirmed', $order->status);

        $this->expectException(RuntimeException::class);
        app(QuickOrderService::class)->create([
            'customer_name' => 'Пётр', 'customer_phone' => '+375290000001', 'items' => [],
            'delivery_type' => 'pickup', 'payment_type' => 'cash',
        ], $manager);
    }

    public function test_manager_creates_quick_order_from_orders_list(): void
    {
        Event::fake([NewOrderCreated::class]);
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $product = $this->product('QO-4', 990);

        $this->actingAs($manager);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListOrders::class)
            ->assertActionVisible('quick_order')
            ->callAction('quick_order', [
                'customer_phone' => '+375297778899',
                'customer_name' => 'Ольга',
                'items' => [['product_id' => $product->id, 'quantity' => 2, 'price' => 990]],
                'delivery_type' => 'pickup',
                'payment_type' => 'card',
                'status' => 'processing',
            ])
            ->assertHasNoActionErrors();

        $order = Order::query()->where('customer_phone', '+375297778899')->firstOrFail();
        $this->assertSame(1980.0, (float) $order->total);
        $this->assertSame($manager->id, $order->manager_id);
        $this->assertSame(1, $order->items()->count());
    }
}

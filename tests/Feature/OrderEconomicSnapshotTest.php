<?php

namespace Tests\Feature;

use App\Events\NewOrderCreated;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderEconomicSnapshot;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Orders\OrderEconomicSnapshotRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use LogicException;
use Tests\TestCase;

class OrderEconomicSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_creates_an_incomplete_immutable_economic_snapshot_without_zeroing_unknown_costs(): void
    {
        Event::fake([NewOrderCreated::class]);
        $product = $this->product();

        $response = $this
            ->withHeader('User-Agent', 'Mozilla/5.0')
            ->withSession([
                'cart' => [
                    $product->id => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'price' => 150,
                        'quantity' => 1,
                    ],
                ],
            ])
            ->post(route('checkout.store'), [
                'order_token' => 'economic-snapshot-checkout',
                'customer_name' => 'Покупатель',
                'customer_phone' => '+375291112233',
                'delivery_type' => 'pickup',
                'payment_type' => 'cash',
            ]);

        $order = Order::query()->latest('id')->firstOrFail();
        $snapshot = $order->placedEconomicSnapshot()->firstOrFail();

        $response->assertRedirect(route('checkout.success', $order->number));
        $this->assertSame(OrderEconomicSnapshot::STATUS_INCOMPLETE, $snapshot->status);
        $this->assertSame('150.00', $snapshot->goods_sale_total);
        $this->assertSame('0.00', $snapshot->known_purchase_total);
        $this->assertNull($snapshot->purchase_total);
        $this->assertNull($snapshot->goods_margin_total);
        $this->assertNull($snapshot->delivery_cost);
        $this->assertNull($snapshot->payment_fee);
        $this->assertNull($snapshot->marketplace_commission);
        $this->assertNull($snapshot->net_profit);
        $this->assertSame(1, $snapshot->missing_purchase_price_count);
        $this->assertSame(1, $snapshot->missing_supplier_count);
        Event::assertDispatched(NewOrderCreated::class);
    }

    public function test_complete_snapshot_is_idempotent_and_preserves_goods_margin(): void
    {
        $order = $this->order(250, 240, 10);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_name' => 'Товар с известной закупкой',
            'product_sku' => 'KNOWN-1',
            'price' => 120,
            'quantity' => 2,
            'total' => 240,
            'supply_status' => 'own_stock',
            'supply_supplier_name' => 'Наш склад',
            'supply_purchase_price' => 96,
            'supply_price_tax_mode' => 'inclusive',
            'supply_is_available' => true,
            'supply_captured_at' => now(),
        ]);

        $recorder = app(OrderEconomicSnapshotRecorder::class);
        $first = $recorder->capturePlaced($order->fresh());
        $second = $recorder->capturePlaced($order->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('order_economic_snapshots', 1);
        $this->assertSame(OrderEconomicSnapshot::STATUS_COMPLETE, $first->status);
        $this->assertSame('192.00', $first->purchase_total);
        $this->assertSame('48.00', $first->goods_margin_total);
        $this->assertSame('20.00', $first->goods_margin_percent);
        $this->assertSame('10.00', $first->delivery_revenue);

        $this->expectException(LogicException::class);
        $first->update(['goods_margin_total' => 999]);
    }

    public function test_order_admin_shows_snapshot_and_quick_problem_tabs(): void
    {
        $order = $this->order(150, 150, 0);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_name' => 'Товар без поставщика',
            'product_sku' => 'UNKNOWN-1',
            'price' => 150,
            'quantity' => 1,
            'total' => 150,
            'supply_status' => 'unresolved',
            'supply_captured_at' => now(),
        ]);
        app(OrderEconomicSnapshotRecorder::class)->capturePlaced($order->fresh());

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(OrderResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Без входной цены')
            ->assertSeeText('Без поставщика')
            ->assertSeeText('Маржа под риском')
            ->assertSeeText('Снимок: товары 150.00 BYN · входная стоимость не определена · без цены: 1');

        $this->actingAs($admin)
            ->get(OrderResource::getUrl('view', ['record' => $order], panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Экономический снимок при оформлении')
            ->assertSeeText('Экономика заполнена частично')
            ->assertSeeText('Не рассчитана до подтверждения расходов');
    }

    public function test_quick_filters_also_find_historical_orders_without_a_snapshot(): void
    {
        $product = $this->product();
        $order = $this->order(150, 150, 0);
        $item = OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'price' => 150,
            'quantity' => 1,
            'total' => 150,
        ]);

        OrderItem::query()->whereKey($item->id)->update([
            'supply_status' => null,
            'supply_purchase_price' => null,
            'supply_captured_at' => null,
        ]);

        $this->assertTrue(Order::query()
            ->withOperationalProblem('missing_price')
            ->whereKey($order->id)
            ->exists());
        $this->assertTrue(Order::query()
            ->withOperationalProblem('missing_supplier')
            ->whereKey($order->id)
            ->exists());
    }

    private function product(): Product
    {
        $category = Category::query()->create([
            'name' => 'Экономический снимок',
            'slug' => 'economic-snapshot',
            'parent_id' => 0,
        ]);

        return Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Тестовый товар',
            'slug' => 'economic-snapshot-product',
            'sku' => 'ECON-1',
            'price' => 150,
        ]);
    }

    private function order(float $total, float $subtotal, float $delivery): Order
    {
        return Order::query()->create([
            'number' => 'ORD-ECON-'.str()->random(8),
            'status' => 'new',
            'customer_name' => 'Покупатель',
            'customer_phone' => '+375291112233',
            'delivery_type' => $delivery > 0 ? 'courier' : 'pickup',
            'delivery_price' => $delivery,
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => $subtotal,
            'discount' => 0,
            'total' => $total,
        ]);
    }
}

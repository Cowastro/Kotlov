<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Orders\OrderItemFulfillmentManager;
use App\Services\Orders\OrderSettlementManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderSettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_settlement_calculates_resale_commission_expenses_and_profit(): void
    {
        [$order, $ownItem, $directItem, $ownSupplier, $directSupplier, $manager] = $this->fixture();
        $order->update(['discount' => 20, 'total' => 290]);
        $fulfillment = app(OrderItemFulfillmentManager::class);
        $fulfillment->confirm($ownItem, 'supplier_purchase', $ownSupplier, $manager, 'Закупка', 60);
        $fulfillment->confirm($directItem, 'direct_supplier', $directSupplier, $manager, 'Прямая поставка');

        $settlement = app(OrderSettlementManager::class)->confirm(
            $order,
            deliveryCost: 15,
            paymentFee: 5,
            refundTotal: 10,
            user: $manager,
            note: 'Проверено менеджером.',
        );

        $this->assertSame(1, $settlement->version);
        $this->assertSame('300.00', $settlement->goods_sale_total);
        $this->assertSame('20.00', $settlement->discount_total);
        $this->assertSame('120.00', $settlement->cost_of_goods_total);
        $this->assertSame('210.00', $settlement->supplier_payable_total);
        $this->assertSame('10.00', $settlement->marketplace_commission_total);
        $this->assertSame('80.00', $settlement->reseller_margin_total);
        $this->assertSame('90.00', $settlement->platform_gross_margin_total);
        $this->assertSame('50.00', $settlement->net_profit);
        $this->assertCount(2, $settlement->lines);
        $this->assertTrue(app(OrderSettlementManager::class)->isCurrent($settlement, $order->fresh()));

        $this->actingAs($manager)
            ->get(OrderResource::getUrl('view', ['record' => $order], panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Подтверждённые взаиморасчёты')
            ->assertSeeText('К выплате поставщикам')
            ->assertSeeText('Комиссия маркетплейса')
            ->assertSeeText('50.00 BYN')
            ->assertSeeText('Актуальна');

        $this->actingAs($manager)
            ->get(SupplierResource::getUrl('edit', ['record' => $directSupplier], panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Комиссия прямой продажи')
            ->assertSeeText('Срок взаиморасчёта');
    }

    public function test_identical_confirmation_is_idempotent_but_route_change_creates_new_version(): void
    {
        [$order, $ownItem, $directItem, $ownSupplier, $directSupplier, $manager] = $this->fixture();
        $fulfillment = app(OrderItemFulfillmentManager::class);
        $fulfillment->confirm($ownItem, 'own_stock', null, $manager, null, 60);
        $fulfillment->confirm($directItem, 'direct_supplier', $directSupplier, $manager);
        $service = app(OrderSettlementManager::class);

        $first = $service->confirm($order, 0, 0, 0, $manager);
        $same = $service->confirm($order->fresh(), 0, 0, 0, $manager);

        $this->assertSame($first->id, $same->id);
        $this->assertDatabaseCount('order_settlements', 1);

        $fulfillment->confirm($directItem->fresh(), 'supplier_purchase', $ownSupplier, $manager, 'Изменили маршрут', 70);
        $this->assertFalse($service->isCurrent($first, $order->fresh()));

        $second = $service->confirm($order->fresh(), 0, 0, 0, $manager);
        $this->assertSame(2, $second->version);
        $this->assertNotSame($first->route_signature, $second->route_signature);
        $this->assertDatabaseCount('order_settlements', 2);
        $this->assertDatabaseHas('order_item_fulfillment_histories', [
            'order_item_id' => $directItem->id,
            'previous_route' => 'direct_supplier',
            'previous_supplier_id' => $directSupplier->id,
            'route' => 'supplier_purchase',
            'supplier_id' => $ownSupplier->id,
        ]);
    }

    public function test_unknown_purchase_price_or_commission_is_never_treated_as_zero(): void
    {
        [$order, $ownItem, $directItem, $ownSupplier, $directSupplier, $manager] = $this->fixture();
        app(OrderItemFulfillmentManager::class)->confirm($ownItem, 'own_stock', null, $manager);
        app(OrderItemFulfillmentManager::class)->confirm($directItem, 'direct_supplier', $directSupplier, $manager);

        $this->expectException(ValidationException::class);
        app(OrderSettlementManager::class)->confirm($order, 0, 0, 0, $manager);
    }

    public function test_direct_supplier_requires_configured_commission(): void
    {
        [$order, $ownItem, $directItem, $ownSupplier, $directSupplier, $manager] = $this->fixture();
        app(OrderItemFulfillmentManager::class)->confirm($ownItem, 'own_stock', null, $manager, null, 60);
        $directSupplier->update(['marketplace_commission_rate' => null]);
        app(OrderItemFulfillmentManager::class)->confirm($directItem, 'direct_supplier', $directSupplier, $manager);

        $this->expectException(ValidationException::class);
        app(OrderSettlementManager::class)->confirm($order, 0, 0, 0, $manager);
    }

    /** @return array{Order, OrderItem, OrderItem, Supplier, Supplier, User} */
    private function fixture(): array
    {
        $category = Category::query()->create([
            'name' => 'Взаиморасчёты',
            'slug' => 'settlements',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Товар взаиморасчёта',
            'slug' => 'settlement-product',
            'sku' => 'SETTLEMENT-1',
            'price' => 100,
        ]);
        $ownSupplier = Supplier::query()->create([
            'code' => 'own-settlement',
            'name' => 'Наш поставщик',
            'contact' => 'own@example.test',
        ]);
        $directSupplier = Supplier::query()->create([
            'code' => 'direct-settlement',
            'name' => 'Прямой поставщик',
            'contact' => 'direct@example.test',
            'marketplace_commission_rate' => 10,
        ]);
        $manager = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $order = Order::query()->create([
            'number' => 'ORD-SETTLEMENT-1',
            'status' => 'confirmed',
            'customer_name' => 'Тестовый клиент',
            'customer_phone' => '+375291111111',
            'delivery_type' => 'pickup',
            'delivery_price' => 10,
            'payment_type' => 'cash',
            'payment_status' => 'paid',
            'subtotal' => 300,
            'discount' => 0,
            'total' => 310,
        ]);
        $ownItem = OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Закупаемая позиция',
            'product_sku' => 'SETTLEMENT-OWN',
            'price' => 100,
            'quantity' => 2,
            'total' => 200,
        ]);
        $directItem = OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Прямая позиция',
            'product_sku' => 'SETTLEMENT-DIRECT',
            'price' => 100,
            'quantity' => 1,
            'total' => 100,
        ]);

        return [$order, $ownItem, $directItem, $ownSupplier, $directSupplier, $manager];
    }
}

<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\Orders\OrderItemFulfillmentManager;
use App\Services\Orders\OrderItemSupplyContextResolver;
use App\Services\Orders\SupplierOrderRequestBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderSupplyRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_item_captures_own_onec_stock_and_order_margin_without_extra_records(): void
    {
        $fixture = $this->fixture();
        $beforeItems = OrderItem::query()->count();
        $beforeLinks = IntegrationProduct::query()->count() + SupplierProduct::query()->count();

        $context = app(OrderItemSupplyContextResolver::class)->resolve($fixture['item']);

        $this->assertSame('own_stock', $context['status']);
        $this->assertSame('Наш склад / 1С', $context['route_label']);
        $this->assertSame('ООО «СанБизнесГруп»', $context['supplier_name']);
        $this->assertSame('+375 29 100-20-30', $context['supplier_contact']);
        $this->assertSame(96.0, $context['wholesale_price']);
        $this->assertSame(48.0, $context['margin_total']);
        $this->assertSame(20.0, $context['margin_percent']);
        $this->assertSame(2, $context['candidate_count']);
        $this->assertTrue($context['is_explicit']);
        $this->assertTrue($context['is_snapshot']);
        $this->assertFalse($context['is_current_recommendation']);
        $this->assertSame('exclusive', $context['price_tax_mode']);
        $this->assertSame(20.0, $context['vat_rate']);
        $this->assertSame(7.0, $context['stock_quantity']);
        $this->assertSame($beforeItems, OrderItem::query()->count());
        $this->assertSame($beforeLinks, IntegrationProduct::query()->count() + SupplierProduct::query()->count());

        $summary = $fixture['order']->fresh()->managementSummary();
        $this->assertSame(240.0, $summary['sale_total']);
        $this->assertSame(192.0, $summary['purchase_total']);
        $this->assertSame(48.0, $summary['margin_total']);
        $this->assertSame(20.0, $summary['margin_percent']);
        $this->assertSame(0, $summary['missing_price_count']);
        $this->assertSame(0, $summary['low_margin_count']);
        $this->assertSame('warning', $summary['severity']);
        $this->assertSame('Проверить', $summary['attention_label']);
        $this->assertTrue($summary['problems']->pluck('label')->contains('Не назначен менеджер'));
    }

    public function test_admin_order_pages_show_supplier_contact_wholesale_margin_and_route(): void
    {
        $fixture = $this->fixture();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(OrderResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Поставка')
            ->assertSeeText('ООО «СанБизнесГруп»')
            ->assertSeeText('Экономика')
            ->assertSeeText('Заказ 240.00 BYN')
            ->assertSeeText('Товары 240.00 BYN · Вход ≈ 192.00 BYN · Маржа ≈ 48.00 BYN / 20.0%')
            ->assertSeeText('Контроль')
            ->assertSeeText('Проверить · 1')
            ->assertSeeText('Не назначен менеджер');

        $this->actingAs($admin)
            ->get(OrderResource::getUrl('view', ['record' => $fixture['order']], panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Контакты поставщика')
            ->assertSeeText('+375 29 100-20-30')
            ->assertSeeText('Оптовая / закупочная')
            ->assertSeeText('96.00 BYN')
            ->assertSeeText('Расчётная маржа')
            ->assertSeeText('48.00 BYN / 20.0%')
            ->assertSeeText('Наш склад / 1С')
            ->assertSeeText('Снимок заказа')
            ->assertSeeText('Новые заказы сохраняют поставщика, входную цену, НДС, остаток и контакт на момент оформления');
    }

    public function test_unknown_purchase_price_is_reported_instead_of_being_treated_as_zero_cost(): void
    {
        $category = Category::query()->create([
            'name' => 'Категория без поставщика',
            'slug' => 'order-without-supplier',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Товар без входной цены',
            'slug' => 'order-product-without-cost',
            'sku' => 'NO-COST-1',
            'price' => 150,
        ]);
        $order = Order::query()->create([
            'number' => 'ORD-NO-COST-1',
            'status' => 'new',
            'customer_name' => 'Покупатель',
            'customer_phone' => '+375291110001',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 150,
            'total' => 150,
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'price' => 150,
            'quantity' => 1,
            'total' => 150,
        ]);

        $summary = $order->fresh()->managementSummary();

        $this->assertSame(0.0, $summary['purchase_total']);
        $this->assertSame(0.0, $summary['margin_total']);
        $this->assertNull($summary['margin_percent']);
        $this->assertSame(1, $summary['missing_price_count']);
        $this->assertSame('critical', $summary['severity']);
        $this->assertTrue($summary['problems']->pluck('label')->contains('Нет входной цены: 1'));
        $this->assertTrue($summary['problems']->pluck('label')->contains('Не определён поставщик: 1'));
    }

    public function test_low_margin_is_an_explicit_manager_warning(): void
    {
        $fixture = $this->fixture(100);

        $summary = $fixture['order']->fresh()->managementSummary();

        $this->assertSame(0.0, $summary['margin_total']);
        $this->assertSame(0.0, $summary['margin_percent']);
        $this->assertSame(1, $summary['low_margin_count']);
        $this->assertSame('warning', $summary['severity']);
        $this->assertTrue($summary['problems']->pluck('label')->contains('Маржа ниже 10%: 1'));
    }

    public function test_order_snapshot_does_not_change_when_supplier_offer_or_contact_changes(): void
    {
        $fixture = $this->fixture();

        IntegrationProduct::query()->where('external_id', 'route-onec-1')->firstOrFail()->update([
            'price' => 110,
            'stock_quantity' => 0,
        ]);
        Supplier::query()->where('code', 'sanbusinessgroup')->firstOrFail()->update([
            'name' => 'Новое имя поставщика',
            'contact' => '+375 00 000-00-00',
        ]);

        $context = app(OrderItemSupplyContextResolver::class)->resolve($fixture['item']->fresh());

        $this->assertSame('ООО «СанБизнесГруп»', $context['supplier_name']);
        $this->assertSame('+375 29 100-20-30', $context['supplier_contact']);
        $this->assertSame(96.0, $context['wholesale_price']);
        $this->assertSame(7.0, $context['stock_quantity']);
        $this->assertTrue($context['is_available']);
        $this->assertTrue($context['is_snapshot']);
    }

    public function test_orders_can_be_filtered_by_snapshot_problem(): void
    {
        $fixture = $this->fixture(100);
        $healthy = $this->fixture(80, 'HEALTHY');
        $healthy['order']->update([
            'manager_id' => User::factory()->create(['role' => 'admin'])->id,
            'assigned_to' => '@manager',
        ]);

        $this->assertTrue(Order::query()
            ->withOperationalProblem('low_margin')
            ->whereKey($fixture['order']->id)
            ->exists());
        $this->assertFalse(Order::query()
            ->withOperationalProblem('low_margin')
            ->whereKey($healthy['order']->id)
            ->exists());
        $this->assertTrue(Order::query()
            ->withOperationalProblem('needs_attention')
            ->whereKey($fixture['order']->id)
            ->exists());
    }

    public function test_manager_can_confirm_and_audit_item_fulfillment_without_changing_recommendation_snapshot(): void
    {
        $fixture = $this->fixture();
        $manager = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $supplier = Supplier::query()->where('code', 'legacy-route-supplier-1')->firstOrFail();

        app(OrderItemFulfillmentManager::class)->confirm(
            $fixture['item'],
            'direct_supplier',
            $supplier,
            $manager,
            'Поставщик доставляет клиенту напрямую.',
        );

        $item = $fixture['item']->fresh();

        $this->assertSame('direct_supplier', $item->fulfillment_route);
        $this->assertSame($supplier->id, $item->fulfillment_supplier_id);
        $this->assertSame('Внешний поставщик', $item->fulfillment_supplier_name);
        $this->assertSame('Передать поставщику напрямую', $item->fulfillmentRouteLabel());
        $this->assertNull($item->fulfillment_purchase_price);
        $this->assertSame('own_stock', $item->supply_status);
        $this->assertSame('ООО «СанБизнесГруп»', $item->supply_supplier_name);
        $this->assertDatabaseHas('order_item_fulfillment_histories', [
            'order_item_id' => $item->id,
            'user_id' => $manager->id,
            'route' => 'direct_supplier',
            'supplier_id' => $supplier->id,
        ]);

        $this->actingAs($manager)
            ->get(OrderResource::getUrl('view', ['record' => $fixture['order']], panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Подтвердить исполнение')
            ->assertSeeText('Решение менеджера')
            ->assertSeeText('Передать поставщику напрямую')
            ->assertSeeText('История решений по исполнению')
            ->assertSeeText('Поставщик доставляет клиенту напрямую.');
    }

    public function test_supplier_is_required_for_external_fulfillment_routes(): void
    {
        $fixture = $this->fixture();

        $this->expectException(ValidationException::class);

        app(OrderItemFulfillmentManager::class)->confirm(
            $fixture['item'],
            'supplier_purchase',
            null,
            null,
        );
    }

    public function test_confirmed_external_items_are_split_into_idempotent_supplier_request_drafts(): void
    {
        $fixture = $this->fixture();
        $manager = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $ownSupplier = Supplier::query()->where('code', 'sanbusinessgroup')->firstOrFail();
        $externalSupplier = Supplier::query()->where('code', 'legacy-route-supplier-1')->firstOrFail();

        app(OrderItemFulfillmentManager::class)->confirm(
            $fixture['item'],
            'supplier_purchase',
            $ownSupplier,
            $manager,
            'Забрать на наш склад.',
        );

        $secondItem = OrderItem::query()->create([
            'order_id' => $fixture['order']->id,
            'product_id' => $fixture['item']->product_id,
            'product_name' => 'Вторая позиция смешанного заказа',
            'product_sku' => 'ROUTE-SECOND',
            'price' => 100,
            'quantity' => 1,
            'total' => 100,
        ]);
        app(OrderItemFulfillmentManager::class)->confirm(
            $secondItem,
            'direct_supplier',
            $externalSupplier,
            $manager,
            'Прямая доставка.',
            70,
        );

        $firstBuild = app(SupplierOrderRequestBuilder::class)->buildDrafts($fixture['order']->fresh(), $manager);
        $secondBuild = app(SupplierOrderRequestBuilder::class)->buildDrafts($fixture['order']->fresh(), $manager);

        $this->assertCount(2, $firstBuild);
        $this->assertCount(2, $secondBuild);
        $this->assertDatabaseCount('supplier_order_requests', 2);
        $this->assertDatabaseCount('supplier_order_request_items', 2);
        $this->assertDatabaseHas('supplier_order_requests', [
            'order_id' => $fixture['order']->id,
            'supplier_id' => $ownSupplier->id,
            'route' => 'supplier_purchase',
            'status' => 'draft',
            'purchase_total' => 192,
        ]);
        $this->assertDatabaseHas('supplier_order_requests', [
            'order_id' => $fixture['order']->id,
            'supplier_id' => $externalSupplier->id,
            'route' => 'direct_supplier',
            'status' => 'draft',
            'purchase_total' => 70,
        ]);

        $this->actingAs($manager)
            ->get(OrderResource::getUrl('view', ['record' => $fixture['order']], panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Заявки поставщикам')
            ->assertSeeText('Черновик')
            ->assertSeeText('поставщику ничего не отправлено');
    }

    /** @return array{order: Order, item: OrderItem} */
    private function fixture(float $onecPrice = 80, string $suffix = '1'): array
    {
        $category = Category::query()->create([
            'name' => 'Тестовая категория поставки',
            'slug' => 'order-supply-routing-'.strtolower($suffix),
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Товар для маршрутизации',
            'slug' => 'order-supply-routing-product-'.strtolower($suffix),
            'sku' => 'ROUTE-'.$suffix,
            'price' => 120,
        ]);
        $ownSupplier = Supplier::query()->where('code', 'sanbusinessgroup')->firstOrFail();
        $ownSupplier->update([
            'name' => 'ООО «СанБизнесГруп»',
            'contact' => '+375 29 100-20-30',
        ]);
        $source = IntegrationSource::query()->where('code', 'onec')->firstOrFail();
        $source->update([
            'supplier_id' => $ownSupplier->id,
            'name' => '1С',
            'driver' => 'commerceml',
            'is_active' => true,
            'settings' => ['price_tax_mode' => 'exclusive', 'vat_rate' => 20],
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $product->id,
            'external_id' => 'route-onec-'.$suffix,
            'name' => $product->name,
            'price' => $onecPrice,
            'stock_quantity' => 7,
            'match_status' => 'matched',
        ]);
        $legacySupplier = Supplier::query()->create([
            'code' => 'legacy-route-supplier-'.strtolower($suffix),
            'name' => 'Внешний поставщик',
            'contact' => 'sales@example.test',
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $legacySupplier->id,
            'product_id' => $product->id,
            'supplier_article' => 'LEGACY-ROUTE-'.$suffix,
            'price_byn' => 70,
            'in_stock' => true,
        ]);
        $order = Order::query()->create([
            'number' => 'ORD-SUPPLY-'.$suffix,
            'status' => 'new',
            'customer_name' => 'Покупатель',
            'customer_phone' => '+375291110000',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 240,
            'total' => 240,
        ]);
        $item = OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'price' => 120,
            'quantity' => 2,
            'total' => 240,
        ]);

        return compact('order', 'item');
    }
}

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
use App\Services\Orders\OrderItemSupplyContextResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderSupplyRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_item_recommends_own_onec_stock_and_explains_current_margin_without_mutation(): void
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
        $this->assertFalse($context['is_explicit']);
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
            ->assertSeeText('Рекомендация · вариантов: 2')
            ->assertSeeText('Для старых заказов это рекомендация');
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
        $fixture = $this->fixture();
        $offer = IntegrationProduct::query()->where('external_id', 'route-onec-1')->firstOrFail();
        $offer->update(['price' => 100]);

        $summary = $fixture['order']->fresh()->managementSummary();

        $this->assertSame(0.0, $summary['margin_total']);
        $this->assertSame(0.0, $summary['margin_percent']);
        $this->assertSame(1, $summary['low_margin_count']);
        $this->assertSame('warning', $summary['severity']);
        $this->assertTrue($summary['problems']->pluck('label')->contains('Маржа ниже 10%: 1'));
    }

    /** @return array{order: Order, item: OrderItem} */
    private function fixture(): array
    {
        $category = Category::query()->create([
            'name' => 'Тестовая категория поставки',
            'slug' => 'order-supply-routing',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Товар для маршрутизации',
            'slug' => 'order-supply-routing-product',
            'sku' => 'ROUTE-1',
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
            'external_id' => 'route-onec-1',
            'name' => $product->name,
            'price' => 80,
            'stock_quantity' => 7,
            'match_status' => 'matched',
        ]);
        $legacySupplier = Supplier::query()->create([
            'code' => 'legacy-route-supplier',
            'name' => 'Внешний поставщик',
            'contact' => 'sales@example.test',
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $legacySupplier->id,
            'product_id' => $product->id,
            'supplier_article' => 'LEGACY-ROUTE-1',
            'price_byn' => 70,
            'in_stock' => true,
        ]);
        $order = Order::query()->create([
            'number' => 'ORD-SUPPLY-1',
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

<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\Orders\OrderItemAvailabilityChecker;
use App\Services\Orders\OrderItemSupplyContextResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderUnconfirmedSupplierStockTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(?int $stock = 130, int $age = 49, bool $inStock = true): array
    {
        $category = Category::create(['name' => 'Stock test', 'slug' => 'unconfirmed-stock-test']);
        $product = Product::create(['name' => 'Pipe stock fixture', 'sku' => 'STOCK-130', 'slug' => 'stock-130-fixture', 'category_id' => $category->id, 'price' => 2.5]);
        $supplier = Supplier::create(['code' => 'unconfirmed-stock-fixture', 'name' => 'Stock fixture supplier', 'is_active' => true]);
        $offer = SupplierProduct::create(['supplier_id' => $supplier->id, 'product_id' => $product->id, 'supplier_article' => '130-FIXTURE', 'price_byn' => 2.24, 'stock_quantity' => $stock, 'in_stock' => $inStock, 'match_status' => 'matched', 'stock_status' => $inStock ? 'in_stock' : 'out_of_stock', 'last_stock_synced_at' => now()->subHours($age), 'last_synced_at' => now()]);
        $order = Order::create(['number' => 'ORD-UNCONFIRMED-STOCK', 'customer_name' => 'Fixture', 'customer_phone' => '123', 'status' => 'checking', 'subtotal' => 325, 'total' => 325]);
        $item = OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'supplier_product_id' => $offer->id, 'product_name' => $product->name, 'product_sku' => $product->sku, 'price' => 2.5, 'quantity' => 130, 'total' => 325]);

        return [$order, $item, $offer];
    }

    public function test_stale_positive_stock_is_unknown_and_never_auto_confirms_order(): void
    {
        [$order,$item,$offer] = $this->fixture();
        $before = $item->fresh()->getAttributes();
        $offerBefore = $offer->fresh()->getAttributes();
        $check = app(OrderItemAvailabilityChecker::class)->check($item);
        $this->assertNull($check['is_available']);
        $this->assertSame(130.0, $check['stock_quantity']);
        $this->assertSame('По загрузке: 130 шт. · наличие требует уточнения', $check['stock_label']);
        $this->assertEquals($offerBefore, $offer->fresh()->getAttributes());
        $summary = app(OrderItemSupplyContextResolver::class)->summarize($order->fresh());
        $this->assertSame(0, $summary['unavailable_count']);
        $this->assertSame(1, $summary['unknown_availability_count']);
        $this->assertNull($item->fresh()->fulfillment_confirmed_at);
        $this->assertNull($order->fresh()->availability_confirmed_at);
        $this->assertSame('checking', $order->fresh()->status);
        foreach (['price', 'quantity', 'total', 'supply_purchase_price', 'supply_captured_at', 'supply_stock_quantity'] as $field) {
            $this->assertSame($before[$field], $item->fresh()->getAttributes()[$field]);
        }
    }

    public function test_fresh_positive_balance_is_source_availability_without_manager_confirmation(): void
    {
        [$order,$item] = $this->fixture(age: 1);
        $check = app(OrderItemAvailabilityChecker::class)->check($item);
        $this->assertTrue($check['is_available']);
        $this->assertSame(130.0, $check['stock_quantity']);
        $this->assertNull($item->fresh()->fulfillment_confirmed_at);
        $this->assertNull($order->fresh()->availability_confirmed_at);
    }

    public function test_fresh_explicit_zero_remains_unavailable(): void
    {
        [$order,$item] = $this->fixture(stock: 0, age: 1, inStock: false);
        $check = app(OrderItemAvailabilityChecker::class)->check($item);
        $this->assertFalse($check['is_available']);
        $this->assertSame('Сейчас нет в наличии', $check['stock_label']);
        $summary = app(OrderItemSupplyContextResolver::class)->summarize($order->fresh());
        $this->assertSame(1, $summary['unavailable_count']);
        $this->assertSame(0, $summary['unknown_availability_count']);
    }

    public function test_missing_quantity_and_old_zero_are_not_confirmed_absence(): void
    {
        [$order,$item,$offer] = $this->fixture(stock: null, age: 1);
        $this->assertNull(app(OrderItemAvailabilityChecker::class)->check($item)['is_available']);
        $offer->update(['stock_quantity' => 0, 'in_stock' => false, 'last_stock_synced_at' => now()->subDays(2)]);
        $this->assertNull(app(OrderItemAvailabilityChecker::class)->check($item)['is_available']);
    }

    public function test_historical_false_with_positive_quantity_is_displayed_as_unconfirmed_without_rewriting_history(): void
    {
        [$order,$item,$offer] = $this->fixture();
        DB::table('order_items')->where('id', $item->id)->update(['supply_is_available' => false,
            'current_availability' => json_encode(['is_available' => false, 'stock_quantity' => 130, 'stock_label' => 'Сейчас нет в наличии', 'source_updated_at' => $offer->last_stock_synced_at->toIso8601String()]), 'current_availability_checked_at' => now()]);
        $item = $item->fresh();
        $before = $item->getAttributes();
        $context = app(OrderItemSupplyContextResolver::class)->resolve($item);
        $this->assertNull($context['is_available']);
        $this->assertStringContainsString('наличие не подтверждено', $context['stock_label']);
        $this->assertNull($item->currentAvailabilityForDisplay()['is_available']);
        $this->assertSame(130, $item->currentAvailabilityForDisplay()['stock_quantity']);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->get(OrderResource::getUrl('view', ['record' => $order], panel: 'admin'))->assertOk()
            ->assertSeeText('наличие требует уточнения')->assertSeeText('Исполнение менеджером ещё не подтверждено')->assertSeeText('Проверено менеджером 0/1')
            ->assertDontSeeText('Не было в наличии при заказе')->assertDontSeeText('сейчас: нет в наличии');
        $this->assertSame($before, $item->fresh()->getAttributes());
        $this->assertFalse($item->fresh()->supply_is_available);
    }

    public function test_inactive_source_is_unknown_even_with_recent_positive_quantity(): void
    {
        [$order,$item,$offer] = $this->fixture(age: 1);
        $offer->supplier->update(['is_active' => false]);
        $this->assertNull(app(OrderItemAvailabilityChecker::class)->check($item)['is_available']);
    }
}

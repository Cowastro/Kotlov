<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\OneCItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Orders\OrderItemAvailabilityChecker;
use App\Services\Orders\OrderItemAvailabilityDisplay;
use App\Services\Orders\OrderItemFulfillmentPicker;
use App\Services\Orders\OrderItemSupplyContextResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class OrderOneCStockSourceTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Mail::fake();
        $supplier = Supplier::firstOrCreate(['code' => 'sanbusinessgroup'], ['name' => 'Наш склад', 'is_active' => true]);
        $source = IntegrationSource::updateOrCreate(['code' => 'onec'], ['name' => '1С', 'is_active' => true, 'supplier_id' => $supplier->id]);
        $priceSource = IntegrationSource::create(['code' => 'old-price', 'name' => 'Закупочный прайс', 'is_active' => true, 'supplier_id' => $supplier->id]);
        $category = Category::create(['name' => 'Счётчики', 'slug' => 'stock-source-test']);
        $product = Product::create(['name' => 'Счётчик', 'slug' => 'stock-source-meter', 'sku' => 'STOCK-SOURCE', 'price' => 150, 'category_id' => $category->id]);
        $link = IntegrationProduct::create(['integration_source_id' => $source->id, 'product_id' => $product->id,
            'external_id' => 'aaaaaaaa-0000-0000-0000-000000000001', 'name' => 'Счётчик 1С', 'match_status' => 'matched',
            'stock_quantity' => 99, 'stock_confirmed_at' => now()->subWeeks(4), 'price' => 0]);
        $old = IntegrationProduct::create(['integration_source_id' => $priceSource->id, 'product_id' => $product->id,
            'external_id' => 'old-meter', 'name' => 'Счётчик прайс', 'match_status' => 'matched', 'stock_quantity' => 99,
            'stock_confirmed_at' => now(), 'price' => 100, 'price_byn' => 100, 'price_tax_mode' => 'inclusive']);
        $stock = OneCItem::create(['ref_key' => $link->external_id, 'description' => 'Счётчик', 'is_folder' => false,
            'deletion_mark' => false, 'stock_on_hand' => 2, 'stock_reserved' => 1, 'stock_synced_at' => now()]);
        $order = Order::create(['number' => 'SOURCE-TEST', 'customer_name' => 'Fixture', 'customer_phone' => '1234567',
            'status' => 'checking', 'subtotal' => 150, 'total' => 150]);
        $item = $order->items()->create(['product_id' => $product->id, 'product_name' => 'Счётчик', 'quantity' => 1, 'price' => 150, 'total' => 150]);
        $item->forceFill(['supply_captured_at' => now()->subDay(), 'supply_channel' => 'integration',
            'supply_integration_source_id' => $priceSource->id, 'supply_supplier_id' => $supplier->id, 'supply_purchase_price' => 75])->save();

        return [$item, $stock, $link, $supplier];
    }

    public function test_current_stock_uses_one_c_while_historical_cost_and_manager_decision_are_preserved(): void
    {
        [$item, $stock, $link, $supplier] = $this->fixture();
        $resolver = app(OrderItemSupplyContextResolver::class);
        $this->assertSame(1.0, $resolver->resolveCurrent($item)['stock_quantity']);
        $this->assertSame('onec', $resolver->resolveCurrent($item)['stock_source']);
        $this->assertSame(75.0, $resolver->resolve($item)['wholesale_price']);
        $offers = app(OrderItemFulfillmentPicker::class)->offers($item);
        $this->assertSame(1.0, $offers[$supplier->id]['quantity']);
        $check = app(OrderItemAvailabilityChecker::class)->check($item);
        $this->assertStringContainsString('1С', $check['stock_label']);
        $this->assertNull($item->fresh()->fulfillment_confirmed_at);
        $this->assertSame('75.00', $item->fresh()->supply_purchase_price);
        $stock->update(['stock_on_hand' => 0, 'stock_reserved' => 0]);
        // The displayed check also follows a later import without trusting its old captured result.
        $this->assertSame(0.0, $item->fresh()->currentAvailabilityForDisplay()['stock_quantity']);
        $this->assertFalse($item->fresh()->currentAvailabilityForDisplay()['is_available']);
        Mail::assertNothingQueued();
    }

    public function test_stale_one_c_cannot_fall_back_to_positive_old_price_list(): void
    {
        [$item, $stock] = $this->fixture();
        $stock->update(['stock_synced_at' => now()->subDay()]);
        $context = app(OrderItemSupplyContextResolver::class)->resolveCurrent($item);
        $this->assertNull($context['stock_quantity']);
        $this->assertNull($context['is_available']);
        $this->assertStringContainsString('1С устарел', $context['stock_label']);
        $this->assertTrue($stock->stock_synced_at->equalTo($context['stock_updated_at']));
    }

    public function test_parent_balance_cannot_be_assigned_to_a_characteristic(): void
    {
        [$item, $stock, $link] = $this->fixture();
        $link->update(['external_id' => $link->external_id.'#characteristic']);
        $context = app(OrderItemSupplyContextResolver::class)->resolveCurrent($item->fresh());
        $this->assertNull($context['stock_quantity']);
        $this->assertNull($context['is_available']);
        $this->assertStringContainsString('неоднозначна', $context['stock_label']);
    }

    public function test_order_card_shows_current_stock_as_primary_and_keeps_history_separate(): void
    {
        [$item, $stock, , $supplier] = $this->fixture();
        $item->order->update(['delivery_type' => 'pickup', 'payment_type' => 'cash', 'payment_status' => 'pending']);
        $warehouse = $item->product->warehouseStocks()->create([
            'supplier_id' => $supplier->id, 'warehouse_code' => 'ecokamin-moscow',
            'warehouse_name' => 'Склад Москва', 'available_quantity' => 21,
            'as_of_date' => now()->toDateString(), 'last_synced_at' => now(),
        ]);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $page = Livewire::test(ViewOrder::class, ['record' => $item->order_id])
            ->assertSeeText('Свободно: 1 шт. · 1С, склад Основной')
            ->assertSeeText('ЭкоКамин, Москва — 21 шт.')
            ->assertSeeText('Исполнение менеджером ещё не подтверждено')
            ->assertDontSeeText('Наличие не было известно · сейчас:');
        $display = app(OrderItemAvailabilityDisplay::class);
        $this->assertStringNotContainsString('Наличие не было известно', $display->label($item));
        $this->assertStringStartsWith('При оформлении заказа:', $display->history($item));
        $this->assertNull($item->fresh()->fulfillment_confirmed_at);
        $stock->update(['stock_on_hand' => 0, 'stock_reserved' => 0]);
        $warehouse->update(['last_synced_at' => now()->subDays(15), 'as_of_date' => now()->subDays(15)->toDateString()]);
        $page->call('$refresh')->assertSeeText('Свободно: 0 шт. · 1С, склад Основной')
            ->assertSeeText('ЭкоКамин, Москва — наличие уточняется (последний остаток: 21 шт.');
        $this->assertSame('danger', $display->color($item->fresh()));
    }

    public function test_saved_manager_decision_cannot_override_current_stock_or_its_color(): void
    {
        [$item, $stock] = $this->fixture();
        $item->forceFill(['fulfillment_availability_status' => 'reserved', 'fulfillment_confirmed_at' => now()])->save();
        $stock->update(['stock_on_hand' => 0, 'stock_reserved' => 0]);
        $display = app(OrderItemAvailabilityDisplay::class);
        $this->assertSame('Свободно: 0 шт. · 1С, склад Основной', $display->label($item));
        $this->assertSame('danger', $display->color($item));
        $this->assertStringContainsString('Исполнение:', $display->details($item));
        $this->assertNotNull($item->fresh()->fulfillment_confirmed_at);
    }

    public function test_stale_stock_and_shortage_are_not_shown_as_ready(): void
    {
        [$item, $stock] = $this->fixture();
        $display = app(OrderItemAvailabilityDisplay::class);
        $item->update(['quantity' => 2]);
        $this->assertSame('warning', $display->color($item));
        $stock->update(['stock_synced_at' => now()->subDay()]);
        $this->assertStringContainsString('Остаток 1С устарел', $display->label($item));
        $this->assertSame('warning', $display->color($item));
        $this->assertStringNotContainsString('99 шт.', $display->label($item));
    }

    public function test_order_links_a_confirmed_own_stock_shortage_to_the_affected_item(): void
    {
        [$item, $stock, , $supplier] = $this->fixture();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $item->update(['fulfillment_route' => 'own_stock', 'fulfillment_confirmed_at' => now(),
            'fulfillment_availability_status' => 'available']);
        $item->product->warehouseStocks()->create([
            'supplier_id' => $supplier->id, 'warehouse_code' => 'ecokamin-moscow',
            'warehouse_name' => 'Склад Москва', 'available_quantity' => 21, 'last_synced_at' => now(),
        ]);
        $stock->update(['stock_on_hand' => 0, 'stock_reserved' => 0]);

        $item->order->items()->create([
            'product_name' => 'Позиция без поставщика', 'price' => 100, 'quantity' => 1, 'total' => 100,
            'fulfillment_route' => 'supplier_purchase',
        ]);
        $page = Livewire::test(ViewOrder::class, ['record' => $item->order_id])
            ->assertSee('На нашем складе свободно 0 шт. из 1 шт. по заказу.')
            ->assertSee('Проверить исполнение')
            ->assertSee('Позиций, требующих внимания: 2')
            ->assertDontSee('Для этой позиции нужно назначить поставщика.')
            ->call('openNextFulfillmentStep');
        $instance = $page->instance();
        $action = $instance->getMountedAction();
        $this->assertSame('assignItemSupplier', $action?->getName());
        $this->assertTrue($action->getRecord()->is($item));
        $schema = $instance->getSchema($instance->getMountedActionSchemaName());
        $this->assertSame('Свободно: 0 шт.', $schema->getComponent('review_own_stock')->getState());
        $this->assertStringContainsString('21 шт.', $schema->getComponent('review_moscow_stock')->getState());
        $this->assertStringContainsString('ЭкоКамин, склад Москва', (string) $action->getModalDescription());
        $page->assertSet('mountedActions.0.data.fulfillment_route', 'supplier_purchase')
            ->assertSet('mountedActions.0.data.supplier_id', $supplier->id)
            ->assertSet('mountedActions.0.data.availability_status', 'clarifying')
            ->assertSet('mountedActions.0.data.request_reserve_now', false);
        $page->call('unmountAction');
        $this->assertSame('own_stock', $item->fresh()->fulfillment_route);

        $moscow = $item->product->ecokaminMoscowStock;
        $moscow->update(['last_synced_at' => now()->subDays(15)]);
        Livewire::test(ViewOrder::class, ['record' => $item->order_id])
            ->call('openNextFulfillmentStep')
            ->assertSet('mountedActions.0.data.fulfillment_route', 'own_stock');

        $item->update(['fulfillment_availability_status' => 'reserved']);
        Livewire::test(ViewOrder::class, ['record' => $item->order_id])->assertDontSee('Проверить исполнение');

        $item->update(['fulfillment_availability_status' => 'available']);
        $stock->update(['stock_synced_at' => now()->subDay()]);
        Livewire::test(ViewOrder::class, ['record' => $item->order_id])->assertDontSee('Проверить исполнение');
    }
}

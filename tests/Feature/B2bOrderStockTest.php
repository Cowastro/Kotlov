<?php

namespace Tests\Feature;

use App\Models\B2bClient;
use App\Models\B2bOrder;
use App\Models\B2bOrderItem;
use App\Models\B2bPricingRule;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductWarehouseStock;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\B2bOrders\B2bOrderStockCheck;
use App\Services\B2bOrders\B2bOrderWorkflow;
use App\Services\B2bSupplyOptionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class B2bOrderStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_reserves_linked_stock_and_second_order_gets_only_the_rest(): void
    {
        [$supplier, $product, $offer] = $this->fixture(stock: 5);
        $first = $this->order($product, quantity: 3, supplierProduct: $offer);
        $second = $this->order($product, quantity: 4, supplierProduct: $offer);

        $summary = app(B2bOrderStockCheck::class)->check($first);
        $this->assertSame(1, $summary['available']);
        $line = $first->items()->sole();
        $this->assertSame('available', $line->availability_status);
        $this->assertSame(3, (int) $line->confirmed_quantity);
        $this->assertSame('supplier:'.$offer->id, $line->stock_key);
        $this->assertDatabaseHas('b2b_order_reservations', [
            'b2b_order_item_id' => $line->id, 'stock_key' => 'supplier:'.$offer->id, 'status' => 'active',
        ]);

        app(B2bOrderStockCheck::class)->check($second);
        $secondLine = $second->items()->sole();
        $this->assertSame('partial', $secondLine->availability_status);
        $this->assertSame(2, (int) $secondLine->confirmed_quantity);
        $this->assertSame(2.0, (float) $secondLine->stock_free_at_check);
        $this->assertSame(200.0, (float) $second->fresh()->subtotal);
    }

    public function test_empty_linked_pool_moves_line_to_warehouse_with_stock(): void
    {
        [$supplier, $product, $offer] = $this->fixture(stock: 0, status: 'out_of_stock');
        $warehouse = ProductWarehouseStock::query()->create([
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'warehouse_code' => 'minsk',
            'warehouse_name' => 'Склад Минск',
            'available_quantity' => 10,
            'delivery_days' => 2,
            'last_synced_at' => now(),
        ]);
        $order = $this->order($product, quantity: 4, supplierProduct: $offer);

        app(B2bOrderStockCheck::class)->check($order);

        $line = $order->items()->sole();
        $this->assertSame('available', $line->availability_status);
        $this->assertSame('warehouse:'.$warehouse->id, $line->stock_key);
        $this->assertSame($warehouse->id, $line->warehouse_stock_id);
        $this->assertNull($line->supplier_product_id);
        $this->assertSame('Склад Минск', $line->warehouse_name);
        $this->assertSame(100.0, (float) $line->purchase_price);
    }

    public function test_source_without_balance_is_left_for_manager_and_blocks_proposal(): void
    {
        [$supplier, $product, $offer] = $this->fixture(stock: null);
        $order = $this->order($product, quantity: 2, supplierProduct: $offer, status: 'checking');
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true, 'can_manage_b2b_orders' => true]);

        app(B2bOrderStockCheck::class)->check($order, $manager);
        $line = $order->items()->sole();
        $this->assertSame('pending', $line->availability_status);
        $this->assertStringContainsString('Подтвердить у поставщика', $line->availability_note);
        $this->assertDatabaseCount('b2b_order_reservations', 0);

        try {
            app(B2bOrderWorkflow::class)->transition($order->fresh(), 'proposal_ready', $manager);
            $this->fail('Позиция без подтверждения не должна уходить клиенту.');
        } catch (ValidationException) {
        }

        // The manager confirmed with the supplier by phone: one click, reserved.
        $line->forceFill(['availability_status' => 'available'])->save();
        app(B2bOrderStockCheck::class)->applyManagerDecision($line, $manager);
        $this->assertSame(2, (int) $line->fresh()->confirmed_quantity);
        $this->assertDatabaseHas('b2b_order_reservations', ['b2b_order_item_id' => $line->id, 'status' => 'active', 'reserved_by' => $manager->id]);

        // A recheck keeps the manager's decision.
        app(B2bOrderStockCheck::class)->check($order);
        $this->assertSame('available', $line->fresh()->availability_status);

        $this->assertSame('proposal_ready', app(B2bOrderWorkflow::class)->transition($order->fresh(), 'proposal_ready', $manager)->status);
    }

    public function test_cancel_releases_and_shipping_consumes_reservations(): void
    {
        [$supplier, $product, $offer] = $this->fixture(stock: 5);
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true, 'can_manage_b2b_orders' => true]);
        $cancelled = $this->order($product, quantity: 5, supplierProduct: $offer, status: 'checking');
        app(B2bOrderStockCheck::class)->check($cancelled);

        app(B2bOrderWorkflow::class)->transition($cancelled, 'cancelled', $manager, 'Клиент отказался');
        $this->assertDatabaseHas('b2b_order_reservations', ['b2b_order_id' => $cancelled->id, 'status' => 'released']);

        $shipped = $this->order($product, quantity: 5, supplierProduct: $offer, status: 'ready_to_ship');
        app(B2bOrderStockCheck::class)->check($shipped);
        $this->assertSame('available', $shipped->items()->sole()->availability_status);
        app(B2bOrderWorkflow::class)->transition($shipped, 'shipped', $manager);
        $this->assertDatabaseHas('b2b_order_reservations', ['b2b_order_id' => $shipped->id, 'status' => 'shipped']);
    }

    public function test_removed_line_with_reason_is_not_billed(): void
    {
        [$supplier, $product, $offer] = $this->fixture(stock: 5);
        $order = $this->order($product, quantity: 2, supplierProduct: $offer, status: 'checking');
        $missing = $order->items()->create([
            'product_name' => 'Позиция без карточки', 'price' => 50, 'quantity' => 1, 'total' => 50,
        ]);
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true, 'can_manage_b2b_orders' => true]);

        app(B2bOrderStockCheck::class)->check($order);
        $this->assertSame('pending', $missing->fresh()->availability_status);

        $missing->forceFill(['availability_status' => 'unavailable', 'manager_note' => 'Снято с производства'])->save();
        app(B2bOrderStockCheck::class)->applyManagerDecision($missing, $manager);

        $this->assertSame(200.0, (float) $order->fresh()->total);
        $this->assertSame('proposal_ready', app(B2bOrderWorkflow::class)->transition($order->fresh(), 'proposal_ready', $manager)->status);
    }

    public function test_partner_catalog_does_not_offer_reserved_units(): void
    {
        [$supplier, $product, $offer] = $this->fixture(stock: 7);
        $order = $this->order($product, quantity: 5, supplierProduct: $offer);
        app(B2bOrderStockCheck::class)->check($order);

        $partner = User::factory()->create(['client_type' => 'wholesale', 'b2b_approved' => true, 'is_active' => true]);
        B2bClient::query()->create([
            'user_id' => $partner->id, 'status' => 'approved', 'partner_type' => 'wholesale',
            'company_name' => 'ООО «Второй партнёр»', 'allowed_integration_source_ids' => [],
            'allowed_supplier_ids' => [$supplier->id],
        ]);

        $option = app(B2bSupplyOptionResolver::class)->preferredForProduct($product, $partner->fresh());
        $this->assertSame(2.0, (float) $option['stock']);
        $this->assertFalse(app(B2bSupplyOptionResolver::class)->canOrder($option, 3));
    }

    public function test_manager_sees_warehouses_and_checks_stock_from_order_card(): void
    {
        [$supplier, $product, $offer] = $this->fixture(stock: 5);
        $order = $this->order($product, quantity: 2, supplierProduct: $offer, status: 'checking');
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true, 'can_manage_b2b_orders' => true]);
        $this->actingAs($manager);

        Livewire::test(\App\Filament\Resources\B2bOrders\RelationManagers\ItemsRelationManager::class, [
            'ownerRecord' => $order,
            'pageClass' => \App\Filament\Resources\B2bOrders\Pages\EditB2bOrder::class,
        ])
            ->assertSuccessful()
            ->callTableAction('checkStock')
            ->assertSee('Партнёрский склад')
            ->assertSee('Подтверждено');

        $this->get('/admin/b2b-orders')->assertOk()->assertSee('1 из 1')->assertSee('Отправить клиенту');
    }

    /** @return array{Supplier, Product, SupplierProduct} */
    private function fixture(?int $stock, ?string $status = null): array
    {
        $supplier = Supplier::query()->create([
            'code' => 'stock-b2b-test',
            'name' => 'Склад партнёра',
            'is_active' => true,
            'b2b_partner_enabled' => true,
            'b2b_client_warehouse_label' => 'Партнёрский склад',
            'b2b_stock_max_age_hours' => 24,
            'b2b_delivery_days_min' => 1,
            'b2b_delivery_days_max' => 2,
            'b2b_priority' => 100,
            'b2b_require_positive_stock' => true,
        ]);
        $category = Category::query()->create(['name' => 'Насосы', 'slug' => 'stock-b2b-test', 'is_active' => true]);
        $product = Product::query()->create([
            'category_id' => $category->id, 'name' => 'Насос со склада', 'slug' => 'stock-b2b-pump',
            'sku' => 'STK-1', 'price' => 200, 'is_active' => true, 'is_archived' => false,
        ]);
        $offer = SupplierProduct::query()->create([
            'supplier_id' => $supplier->id, 'product_id' => $product->id, 'product_sku' => $product->sku,
            'supplier_article' => 'STK-1', 'supplier_name' => $product->name, 'price' => 100, 'price_byn' => 100,
            'currency' => 'BYN', 'in_stock' => (bool) $stock, 'stock_quantity' => $stock, 'stock_status' => $status,
            'match_status' => 'matched', 'last_stock_synced_at' => now(), 'last_synced_at' => now(),
        ]);
        $supplier->b2bPricingRules()->create([
            'name' => 'Маржа', 'scope_type' => B2bPricingRule::SCOPE_SUPPLIER,
            'calculation_type' => B2bPricingRule::TYPE_TARGET_MARGIN, 'value' => 20, 'minimum_profit_byn' => 0,
            'minimum_margin_percent' => 10, 'rounding_step_byn' => 1, 'minimum_retail_discount_percent' => 0,
            'priority' => 100, 'status' => B2bPricingRule::STATUS_ACTIVE,
        ]);

        return [$supplier, $product, $offer];
    }

    private function order(Product $product, int $quantity, SupplierProduct $supplierProduct, string $status = 'new'): B2bOrder
    {
        $partner = User::factory()->create();
        $client = B2bClient::query()->create([
            'user_id' => $partner->id, 'status' => 'approved', 'partner_type' => 'wholesale',
            'company_name' => 'ООО «Резерв»', 'contact_name' => $partner->name,
        ]);
        $order = B2bOrder::query()->create([
            'b2b_client_id' => $client->id, 'created_by' => $partner->id,
            'number' => B2bOrder::generateNumber(), 'status' => $status,
            'contact_name' => $partner->name, 'contact_phone' => '+375291112233',
            'company_name' => $client->company_name, 'subtotal' => 100 * $quantity, 'total' => 100 * $quantity,
        ]);
        B2bOrderItem::query()->create([
            'b2b_order_id' => $order->id, 'product_id' => $product->id,
            'supplier_product_id' => $supplierProduct->id, 'supplier_id' => $supplierProduct->supplier_id,
            'product_name' => $product->name, 'price' => 100, 'quantity' => $quantity, 'total' => 100 * $quantity,
        ]);

        return $order;
    }
}

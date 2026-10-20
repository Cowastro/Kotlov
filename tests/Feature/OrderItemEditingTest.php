<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderItemEditingTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_replace_and_add_order_items_and_totals_are_recalculated(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $category = Category::query()->create([
            'name' => 'Баки',
            'slug' => 'tanks',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $oldProduct = $this->product($category, 'Старый бак', 'OLD-TANK', 1000);
        $newProduct = $this->product($category, 'Новый бак', 'NEW-TANK', 1200);
        $extraProduct = $this->product($category, 'Комплект подключения', 'KIT-1', 150);
        $order = Order::query()->create([
            'number' => 'ORD-EDIT-001',
            'status' => 'confirmed',
            'customer_name' => 'Клиент',
            'customer_phone' => '+375291111111',
            'delivery_type' => 'courier',
            'delivery_price' => 30,
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'discount' => 50,
            'subtotal' => 1000,
            'total' => 980,
        ]);
        $order->items()->create([
            'product_id' => $oldProduct->id,
            'product_name' => $oldProduct->name,
            'product_sku' => $oldProduct->sku,
            'price' => 1000,
            'quantity' => 1,
            'total' => 1000,
        ]);

        $this->actingAs($manager);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->fillForm([
                'items' => [
                    [
                        'product_id' => $newProduct->id,
                        'product_name' => $newProduct->name,
                        'product_sku' => $newProduct->sku,
                        'price' => 1200,
                        'quantity' => 1,
                        'total' => 1,
                    ],
                    [
                        'product_id' => $extraProduct->id,
                        'product_name' => $extraProduct->name,
                        'product_sku' => $extraProduct->sku,
                        'price' => 150,
                        'quantity' => 2,
                        'total' => 1,
                    ],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $order->refresh();

        $this->assertCount(2, $order->items);
        $this->assertFalse($order->items->contains('product_id', $oldProduct->id));
        $this->assertSame('1200.00', $order->items->firstWhere('product_id', $newProduct->id)->total);
        $this->assertSame('300.00', $order->items->firstWhere('product_id', $extraProduct->id)->total);
        $this->assertSame('1500.00', $order->subtotal);
        $this->assertSame('1480.00', $order->total);
        $this->assertNotNull($order->corrected_at);
        $this->assertSame($manager->id, $order->corrected_by);
    }

    public function test_replacing_a_product_clears_the_old_supply_and_fulfillment_snapshot(): void
    {
        $category = Category::query()->create([
            'name' => 'Комплектующие',
            'slug' => 'order-edit-components',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $oldProduct = $this->product($category, 'Старый товар', 'OLD-ITEM', 100);
        $newProduct = $this->product($category, 'Новый товар', 'NEW-ITEM', 200);
        $order = Order::query()->create([
            'number' => 'ORD-EDIT-SNAPSHOT',
            'status' => 'confirmed',
            'customer_name' => 'Клиент',
            'customer_phone' => '+375291111112',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 100,
            'total' => 100,
        ]);
        $item = $order->items()->create([
            'product_id' => $oldProduct->id,
            'product_name' => $oldProduct->name,
            'product_sku' => $oldProduct->sku,
            'price' => 100,
            'quantity' => 1,
            'total' => 100,
        ]);
        $item->forceFill([
            'supply_status' => 'supplier_purchase',
            'supply_supplier_name' => 'Старый поставщик',
            'supply_purchase_price' => 70,
            'fulfillment_route' => 'supplier_purchase',
            'fulfillment_supplier_name' => 'Старый поставщик',
            'fulfillment_purchase_price' => 70,
            'fulfillment_confirmed_at' => now(),
        ])->save();

        $item->update([
            'product_id' => $newProduct->id,
            'product_name' => $newProduct->name,
            'product_sku' => $newProduct->sku,
            'price' => 200,
        ]);

        $item->refresh();
        $this->assertSame($newProduct->id, $item->product_id);
        $this->assertSame('unresolved', $item->supply_status);
        $this->assertNull($item->supply_supplier_name);
        $this->assertNull($item->supply_purchase_price);
        $this->assertNull($item->fulfillment_route);
        $this->assertNull($item->fulfillment_supplier_name);
        $this->assertNull($item->fulfillment_purchase_price);
        $this->assertNull($item->fulfillment_confirmed_at);
        $this->assertNotNull($item->supply_captured_at);
    }

    public function test_order_product_picker_uses_storefront_search_and_shows_retail_price_and_stock(): void
    {
        $category = Category::query()->create([
            'name' => 'Мембранные баки',
            'slug' => 'order-picker-tanks',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $product = $this->product(
            $category,
            'Расширительный вертикальный бак V 300',
            'KOTLOV-007689',
            1185,
        );
        $product->update([
            'manual_stock_management' => true,
            'availability_status' => Product::AVAILABILITY_IN_STOCK,
            'in_stock' => true,
            'stock_qty' => 3,
        ]);

        $options = OrderForm::searchProductOptions('бак 300 расширительный');

        $this->assertArrayHasKey($product->id, $options);
        $this->assertStringContainsString('розница 1 185,00 BYN', $options[$product->id]);
        $this->assertStringContainsString('В наличии: 3 шт.', $options[$product->id]);
    }

    public function test_order_product_picker_filters_by_supplier_warehouse_and_category_tree(): void
    {
        $root = Category::query()->create([
            'name' => 'Инженерная сантехника',
            'slug' => 'engineering-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $tanks = Category::query()->create([
            'name' => 'Мембранные баки',
            'slug' => 'supplier-tanks-test',
            'parent_id' => $root->id,
            'is_active' => true,
        ]);
        $pumps = Category::query()->create([
            'name' => 'Насосы',
            'slug' => 'supplier-pumps-test',
            'parent_id' => $root->id,
            'is_active' => true,
        ]);
        $sanbusiness = Supplier::query()->create([
            'code' => 'sbg-picker-test',
            'name' => 'Санбизнесгруп',
            'currency' => 'BYN',
            'currency_rate' => 1,
            'is_active' => true,
        ]);
        $otherSupplier = Supplier::query()->create([
            'code' => 'other-picker-test',
            'name' => 'Другой поставщик',
            'currency' => 'BYN',
            'currency_rate' => 1,
            'is_active' => true,
        ]);
        $tank = $this->product($tanks, 'Бак SBG 200', 'SBG-TANK-200', 420);
        $pump = $this->product($pumps, 'Насос другого склада', 'OTHER-PUMP', 900);

        $sanbusinessSource = IntegrationSource::query()->create([
            'supplier_id' => $sanbusiness->id,
            'code' => 'onec-sbg-picker-test',
            'name' => '1С Санбизнесгруп',
            'is_active' => true,
            'update_stock' => true,
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $sanbusinessSource->id,
            'product_id' => $tank->id,
            'external_id' => 'sbg-picker-tank-200',
            'external_sku' => 'SBG-200',
            'name' => $tank->name,
            'price' => 300,
            'price_byn' => 300,
            'stock_quantity' => 7,
            'match_status' => 'matched',
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $otherSupplier->id,
            'product_id' => $pump->id,
            'product_sku' => $pump->sku,
            'supplier_article' => 'OTHER-1',
            'supplier_name' => $pump->name,
            'price' => 700,
            'price_byn' => 700,
            'currency' => 'BYN',
            'in_stock' => true,
            'stock_quantity' => 2,
            'match_status' => 'matched',
        ]);

        $suppliers = OrderForm::supplierOptions();
        $categories = OrderForm::categoryOptions($sanbusiness->id);
        $options = OrderForm::searchProductOptions('', $sanbusiness->id, $root->id);
        $fallbackOptions = OrderForm::searchProductOptions('бак 200', $sanbusiness->id, $pumps->id);

        $this->assertSame('Санбизнесгруп', $suppliers[$sanbusiness->id]);
        $this->assertArrayHasKey($root->id, $categories);
        $this->assertSame('Инженерная сантехника → Мембранные баки', $categories[$tanks->id]);
        $this->assertArrayNotHasKey($pumps->id, $categories);
        $this->assertArrayHasKey($tank->id, $options);
        $this->assertArrayNotHasKey($pump->id, $options);
        $this->assertStringContainsString('Санбизнесгруп', $options[$tank->id]);
        $this->assertStringContainsString('склад 7 шт.', $options[$tank->id]);
        $this->assertStringContainsString('закупка 300,00 BYN', $options[$tank->id]);
        $this->assertStringContainsString('розница 420,00 BYN', $options[$tank->id]);
        $this->assertArrayHasKey($tank->id, $fallbackOptions);
        $this->assertArrayNotHasKey($pump->id, $fallbackOptions);
        $this->assertStringContainsString('другая категория: Мембранные баки', $fallbackOptions[$tank->id]);
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
}

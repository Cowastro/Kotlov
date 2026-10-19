<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
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

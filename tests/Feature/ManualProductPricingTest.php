<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Orders\OrderItemSupplyContextResolver;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManualProductPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_set_manual_purchase_and_retail_prices_when_product_has_no_supplier(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $product = $this->product();

        $this->actingAs($manager);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListProducts::class)
            ->assertTableActionVisible('manual_prices', $product)
            ->callTableAction('manual_prices', $product, [
                'manual_purchase_price_byn' => 700,
                'retail_price' => 990,
            ])
            ->assertHasNoTableActionErrors();

        $product->refresh();
        $this->assertSame('700.00', $product->manual_purchase_price_byn);
        $this->assertSame('990.00', $product->price);
        $this->assertSame($manager->id, $product->manual_price_updated_by);
        $this->assertNotNull($product->manual_price_updated_at);

        $item = new OrderItem(['product_id' => $product->id, 'price' => 990, 'quantity' => 2]);
        $item->setRelation('product', $product);
        $context = app(OrderItemSupplyContextResolver::class)->resolve($item);

        $this->assertSame('manual', $context['channel']);
        $this->assertSame(700.0, $context['wholesale_price']);
        $this->assertSame(290.0, $context['margin_unit']);
        $this->assertSame('Наличие уточняется вручную', $context['stock_label']);
    }

    private function product(): Product
    {
        $category = Category::query()->create([
            'name' => 'Ручные цены',
            'slug' => 'manual-pricing-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        return Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Товар без поставщика',
            'slug' => 'manual-pricing-product',
            'sku' => 'MANUAL-PRICE-001',
            'price' => 900,
            'currency' => 'BYN',
            'is_active' => true,
        ]);
    }
}

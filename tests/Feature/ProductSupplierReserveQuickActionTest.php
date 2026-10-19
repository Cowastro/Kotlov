<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Category;
use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductSupplierReserveQuickActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_email_opens_internal_reserve_form_from_quick_actions(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $counterparty = CrmCounterparty::query()->create([
            'name' => 'Тестовый поставщик',
            'classification' => 'supplier',
        ]);
        CrmContactPoint::query()->create([
            'counterparty_id' => $counterparty->id,
            'type' => 'email',
            'value' => 'reserve@supplier.test',
            'is_primary' => true,
            'is_active' => true,
        ]);
        $supplier = Supplier::query()->create([
            'code' => 'reserve-supplier',
            'name' => 'Поставщик резерва',
            'crm_counterparty_id' => $counterparty->id,
            'is_active' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Товары для резерва',
            'slug' => 'reserve-products',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Расширительный бак',
            'slug' => 'reserve-expansion-tank',
            'sku' => 'RESERVE-001',
            'price' => 1185,
            'currency' => 'BYN',
            'is_active' => true,
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_article' => 'SUP-001',
            'price_byn' => 913,
            'stock_quantity' => 1,
        ]);

        $this->actingAs($manager);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListProducts::class)
            ->assertTableActionVisible('request_supplier_reserve', $product)
            ->assertSeeHtml("mountTableAction('request_supplier_reserve', '{$product->id}')")
            ->assertDontSeeHtml('mailto:reserve@supplier.test')
            ->mountTableAction('request_supplier_reserve', $product)
            ->assertTableActionDataSet([
                'supplier_id' => $supplier->id,
                'recipient' => 'reserve@supplier.test',
                'quantity' => 1,
            ]);
    }
}

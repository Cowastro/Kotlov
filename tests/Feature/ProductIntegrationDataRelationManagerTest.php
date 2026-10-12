<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Products\RelationManagers\IntegrationProductsRelationManager;
use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductIntegrationDataRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_card_shows_supplier_source_prices_and_stock_from_one_c(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $supplier = Supplier::query()->create([
            'code' => 'sbg',
            'name' => 'ООО «СанБизнесГруп»',
            'is_active' => true,
        ]);
        $source = IntegrationSource::query()->create([
            'supplier_id' => $supplier->id,
            'code' => 'onec-sbg',
            'name' => '1С СанБизнесГруп',
            'driver' => 'commerceml',
            'price_currency' => 'BYN',
            'is_active' => true,
            'settings' => [
                'price_tax_mode' => IntegrationSource::PRICE_TAX_EXCLUSIVE,
                'vat_rate' => 20,
            ],
        ]);
        $category = Category::query()->create([
            'name' => 'Тестовая категория',
            'slug' => 'testovaya-kategoriya',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Карточка сайта',
            'slug' => 'kartochka-sayta',
            'sku' => 'KOTLOV-TEST',
            'price' => 15.90,
            'currency' => 'BYN',
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $product->id,
            'external_id' => 'onec-product-id',
            'external_code' => 'УТ-00000124',
            'name' => 'Позиция из 1С',
            'price' => 11.50,
            'price_currency' => 'BYN',
            'price_tax_mode' => IntegrationSource::PRICE_TAX_EXCLUSIVE,
            'price_vat_rate' => 20,
            'price_byn' => 13.80,
            'stock_quantity' => 30,
            'match_status' => 'matched',
            'last_seen_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(IntegrationProductsRelationManager::class, [
                'ownerRecord' => $product,
                'pageClass' => EditProduct::class,
            ])
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$item])
            ->assertSeeText('ООО «СанБизнесГруп»')
            ->assertSeeText('1С СанБизнесГруп')
            ->assertSeeText('УТ-00000124')
            ->assertSeeText('11,50 BYN')
            ->assertSeeText('13,80 BYN')
            ->assertSeeText('15,90 BYN')
            ->assertSeeText('30 шт.');

        $this->assertContains(
            IntegrationProductsRelationManager::class,
            ProductResource::getRelations(),
        );
    }
}

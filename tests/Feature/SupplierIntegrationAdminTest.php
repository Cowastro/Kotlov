<?php

namespace Tests\Feature;

use App\Filament\Resources\IntegrationSources\Pages\CreateIntegrationSource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Products\Tables\ProductsTable;
use App\Filament\Resources\Suppliers\Pages\ListSuppliers;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Models\Category;
use App\Models\IntegrationExchangeRun;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierIntegrationAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_filter_combines_legacy_links_and_confirmed_1c_links_without_creating_data(): void
    {
        $supplier = Supplier::query()->create([
            'code' => 'unified-supplier',
            'name' => 'Единый поставщик',
        ]);
        $source = IntegrationSource::query()->create([
            'supplier_id' => $supplier->id,
            'code' => 'unified-supplier-1c',
            'name' => '1С единого поставщика',
            'driver' => 'commerceml',
        ]);
        $category = Category::query()->create([
            'name' => 'Тестовая категория поставщика',
            'slug' => 'supplier-integration-test-category',
            'parent_id' => 0,
        ]);
        $legacyProduct = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'LEGACY-1',
            'name' => 'Товар старого канала',
            'slug' => 'legacy-channel-product',
        ]);
        $integrationProduct = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'ONEC-1',
            'name' => 'Товар из 1С',
            'slug' => 'onec-channel-product',
        ]);
        $suggestedProduct = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'SUGGESTED-1',
            'name' => 'Только предложенная карточка',
            'slug' => 'suggested-channel-product',
        ]);
        $unrelatedProduct = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'OTHER-1',
            'name' => 'Товар другого поставщика',
            'slug' => 'other-supplier-product',
        ]);

        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $legacyProduct->id,
            'supplier_article' => 'LEGACY-SUPPLIER-1',
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $integrationProduct->id,
            'external_id' => 'onec-confirmed-1',
            'match_status' => 'matched',
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $suggestedProduct->id,
            'external_id' => 'onec-suggested-1',
            'match_status' => 'suggested',
        ]);

        $beforeLegacyLinks = SupplierProduct::query()->count();
        $beforeIntegrationLinks = IntegrationProduct::query()->count();
        $ids = ProductsTable::applySupplierFilter(Product::query(), $supplier->id)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertSame([$legacyProduct->id, $integrationProduct->id], $ids);
        $this->assertNotContains($suggestedProduct->id, $ids);
        $this->assertNotContains($unrelatedProduct->id, $ids);
        $this->assertSame($beforeLegacyLinks, SupplierProduct::query()->count());
        $this->assertSame($beforeIntegrationLinks, IntegrationProduct::query()->count());
    }

    public function test_supplier_list_explains_mixed_channel_1c_health_and_link_counts_without_mutation(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $supplier = Supplier::query()->create([
            'code' => 'mixed-supplier',
            'name' => 'Поставщик со смешанным каналом',
        ]);
        $category = Category::query()->create([
            'name' => 'Категория смешанного канала',
            'slug' => 'mixed-channel-category',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'MIXED-1',
            'name' => 'Товар смешанного канала',
            'slug' => 'mixed-channel-product',
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_article' => 'MIXED-LEGACY-1',
        ]);
        $source = IntegrationSource::query()->create([
            'supplier_id' => $supplier->id,
            'code' => 'mixed-supplier-1c',
            'name' => '1С смешанного поставщика',
            'driver' => 'commerceml',
            'is_active' => true,
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $product->id,
            'external_id' => 'mixed-onec-1',
            'match_status' => 'matched',
        ]);
        IntegrationExchangeRun::query()->create([
            'integration_source_id' => $source->id,
            'direction' => 'inbound',
            'operation' => 'catalog',
            'status' => 'success',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);

        $beforeLegacyLinks = SupplierProduct::query()->count();
        $beforeIntegrationLinks = IntegrationProduct::query()->count();

        $this->actingAs($admin)
            ->get(SupplierResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Канал данных')
            ->assertSeeText('Статус 1С');

        Livewire::actingAs($admin)
            ->test(ListSuppliers::class)
            ->searchTable('Поставщик со смешанным каналом')
            ->assertSeeText('Поставщик со смешанным каналом')
            ->assertSeeText('Смешанный')
            ->assertSeeText('Работает')
            ->assertSeeText('Старые: 1')
            ->assertSeeText('1С: 1')
            ->assertSeeText('Настроить интеграцию');

        $this->assertSame($beforeLegacyLinks, SupplierProduct::query()->count());
        $this->assertSame($beforeIntegrationLinks, IntegrationProduct::query()->count());
    }

    public function test_product_list_renders_mixed_supplier_channels_without_treating_labels_as_models(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $supplier = Supplier::query()->create([
            'code' => 'mixed-product-list-supplier',
            'name' => 'Поставщик смешанного товара',
        ]);
        $category = Category::query()->create([
            'name' => 'Категория списка товаров',
            'slug' => 'mixed-product-list-category',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'MIXED-LIST-1',
            'name' => 'Товар с двумя каналами',
            'slug' => 'mixed-list-product',
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_article' => 'LEGACY-MIXED-LIST-1',
        ]);
        $source = IntegrationSource::query()->create([
            'supplier_id' => $supplier->id,
            'code' => 'mixed-product-list-1c',
            'name' => '1С смешанного товара',
            'driver' => 'commerceml',
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $product->id,
            'external_id' => 'mixed-product-list-external',
            'match_status' => 'matched',
        ]);

        $this->actingAs($admin)
            ->get(ProductResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Товар с двумя каналами')
            ->assertSeeText('Поставщик смешанного товара')
            ->assertSeeText('1С + Старый канал');
    }

    public function test_connect_1c_action_can_prefill_supplier_without_saving_anything(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $supplier = Supplier::query()->create([
            'code' => 'supplier-awaiting-onec',
            'name' => 'Поставщик для подключения 1С',
        ]);

        Livewire::withQueryParams(['supplier_id' => $supplier->id])
            ->actingAs($admin)
            ->test(CreateIntegrationSource::class)
            ->assertSet('data.supplier_id', $supplier->id);

        $this->assertSame(0, IntegrationSource::query()
            ->where('supplier_id', $supplier->id)
            ->count());
    }
}

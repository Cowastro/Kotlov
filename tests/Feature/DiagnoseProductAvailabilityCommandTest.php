<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiagnoseProductAvailabilityCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_the_evidence_used_by_the_storefront(): void
    {
        $category = Category::query()->create([
            'name' => 'Диагностика наличия',
            'slug' => 'availability-diagnostics',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $supplier = Supplier::query()->create([
            'code' => 'availability-diagnostics',
            'name' => 'Поставщик диагностики',
        ]);
        $source = IntegrationSource::query()->create([
            'supplier_id' => $supplier->id,
            'code' => 'availability-diagnostics-onec',
            'name' => '1С диагностики',
            'driver' => 'commerceml',
            'is_active' => true,
            'settings' => ['stock_validity_minutes' => 1440],
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Товар диагностики',
            'slug' => 'availability-diagnostic-product',
            'sku' => 'DIAG-001',
            'price' => 49,
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => true,
            'availability_status' => Product::AVAILABILITY_IN_STOCK,
            'requires_price_confirmation' => false,
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $product->id,
            'external_id' => 'diag-item',
            'name' => 'Позиция диагностики',
            'stock_quantity' => 7,
            'stock_confirmed_at' => now(),
            'match_status' => 'matched',
        ]);

        $this->artisan('catalog:diagnose-availability', ['product' => 'DIAG-001'])
            ->expectsOutput('product_found=1')
            ->expectsOutput('product_id='.$product->id)
            ->expectsOutput('effective_status=in_stock')
            ->expectsOutput('can_be_purchased=1')
            ->expectsOutput('integration_channels=1')
            ->expectsOutput('integration.1.source_code=availability-diagnostics-onec')
            ->expectsOutput('integration.1.source_driver=commerceml')
            ->expectsOutput('integration.1.stock_quantity=7.000')
            ->expectsOutput('integration.1.validity_minutes=1440')
            ->expectsOutput('integration.1.fresh_positive=1')
            ->assertSuccessful();

        $this->artisan('catalog:diagnose-availability', ['product' => (string) $product->id])
            ->expectsOutput('product_found=1')
            ->expectsOutput('product_id='.$product->id)
            ->expectsOutput('effective_status=in_stock')
            ->assertSuccessful();
    }
}

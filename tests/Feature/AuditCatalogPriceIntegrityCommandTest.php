<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AuditCatalogPriceIntegrityCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_price_and_margin_anomalies_without_changing_catalog_data(): void
    {
        $report = storage_path('app/testing/price-integrity.csv');
        $researchReport = storage_path('app/testing/price-integrity-research.csv');
        $category = Category::query()->create([
            'name' => 'Ценовой аудит',
            'slug' => 'price-audit',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Товар с отрицательной маржой',
            'slug' => 'negative-margin-product',
            'sku' => 'PRICE-AUDIT-1',
            'price' => 90,
            'price_old' => 80,
            'currency' => 'BYN',
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => true,
            'availability_status' => Product::AVAILABILITY_IN_STOCK,
        ]);
        $supplier = Supplier::query()->create([
            'code' => 'price-audit-supplier',
            'name' => 'Поставщик ценового аудита',
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_article' => 'PRICE-AUDIT-1',
            'price_byn' => 100,
            'stock_quantity' => 2,
            'in_stock' => true,
            'last_stock_synced_at' => now(),
        ]);
        $reviewedProduct = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Проверенный товар без открытой цены',
            'slug' => 'reviewed-zero-price-product',
            'sku' => 'PRICE-AUDIT-REVIEWED',
            'price' => 0,
            'currency' => 'BYN',
            'is_active' => true,
            'is_archived' => false,
            'requires_price_confirmation' => true,
        ]);
        ActivityLog::query()->create([
            'action' => 'market_price_confirmation_required',
            'model_type' => Product::class,
            'model_id' => $reviewedProduct->id,
            'new_values' => ['batch' => 'reviewed-test-batch'],
            'user_agent' => 'catalog:apply-market-price-batch',
        ]);

        $this->artisan('catalog:audit-price-integrity', [
            '--stale-hours' => 24,
            '--output' => $report,
            '--research-output' => $researchReport,
        ])
            ->expectsOutputToContain('metric.invalid_old_price=1')
            ->expectsOutputToContain('metric.retail_below_supplier_cost=1')
            ->expectsOutputToContain('metric.retail_equals_supplier_cost=0')
            ->expectsOutputToContain('metric.retail_margin_below_minimum=1')
            ->expectsOutputToContain('metric.report_rows=')
            ->expectsOutputToContain('metric.research_rows=')
            ->expectsOutputToContain('metric.research_reviewed_rows=')
            ->expectsOutputToContain('metric.research_unreviewed_rows=')
            ->assertSuccessful();

        $this->assertSame('90.00', $product->fresh()->price);
        $this->assertStringContainsString('PRICE-AUDIT-1', File::get($report));
        $research = File::get($researchReport);
        $this->assertStringContainsString('PRICE-AUDIT-1', $research);
        $this->assertStringContainsString('PRICE-AUDIT-REVIEWED', $research);
        $this->assertStringContainsString('reviewed;market_price_confirmation_required', $research);
        $this->assertStringContainsString('unreviewed', $research);
        $this->assertStringContainsString('retail_below_supplier_cost|retail_margin_below_minimum|invalid_old_price', $research);
        $this->assertStringNotContainsString('minimum_supplier_cost', $research);
        $this->assertStringNotContainsString('margin_percent', $research);
        File::delete($report);
        File::delete($researchReport);
    }

    public function test_research_report_keeps_a_raw_margin_just_below_the_threshold(): void
    {
        $researchReport = storage_path('app/testing/price-integrity-rounding-research.csv');
        $category = Category::query()->create([
            'name' => 'Пограничная маржа',
            'slug' => 'raw-margin-boundary-category',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Товар на границе маржи',
            'slug' => 'raw-margin-boundary-product',
            'sku' => 'PRICE-AUDIT-BOUNDARY',
            'price' => 1000,
            'currency' => 'BYN',
            'is_active' => true,
            'is_archived' => false,
        ]);
        $supplier = Supplier::query()->create([
            'code' => 'price-audit-boundary-supplier',
            'name' => 'Поставщик пограничной цены',
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_article' => 'PRICE-AUDIT-BOUNDARY',
            'price_byn' => 900.01,
            'stock_quantity' => 1,
            'in_stock' => true,
            'last_stock_synced_at' => now(),
        ]);

        $this->artisan('catalog:audit-price-integrity', [
            '--research-output' => $researchReport,
        ])
            ->expectsOutputToContain('metric.retail_margin_below_minimum=1')
            ->assertSuccessful();

        $research = File::get($researchReport);
        $this->assertStringContainsString('PRICE-AUDIT-BOUNDARY', $research);
        $this->assertStringContainsString('retail_margin_below_minimum', $research);
        File::delete($researchReport);
    }
}

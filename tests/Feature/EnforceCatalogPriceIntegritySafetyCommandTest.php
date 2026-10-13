<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EnforceCatalogPriceIntegritySafetyCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dry_runs_then_idempotently_protects_every_server_cost_anomaly(): void
    {
        Product::query()->update(['is_active' => false]);
        User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $category = Category::query()->create([
            'name' => 'Защита цен',
            'slug' => 'price-safety',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $supplier = Supplier::query()->create([
            'code' => 'price-safety-supplier',
            'name' => 'Поставщик проверки цены',
        ]);

        $belowCost = $this->product($category->id, 'SAFETY-BELOW-COST', 90, 80);
        $lowMargin = $this->product($category->id, 'SAFETY-LOW-MARGIN', 105, null);
        $extremeMarkup = $this->product($category->id, 'SAFETY-EXTREME', 500, null);
        $safe = $this->product($category->id, 'SAFETY-OK', 120, 150);
        $zero = $this->product($category->id, 'SAFETY-ZERO', 0, null);

        foreach ([$belowCost, $lowMargin, $extremeMarkup, $safe, $zero] as $product) {
            SupplierProduct::query()->create([
                'supplier_id' => $supplier->id,
                'product_id' => $product->id,
                'supplier_article' => 'SUP-'.$product->sku,
                'price_byn' => 100,
                'stock_quantity' => 2,
                'in_stock' => true,
                'last_stock_synced_at' => now(),
            ]);
        }

        $this->assertSame(0, Artisan::call('catalog:enforce-price-integrity-safety'));
        $dryRunOutput = Artisan::output();
        $this->assertStringContainsString('metric.status=dry_run', $dryRunOutput);
        $this->assertStringContainsString('metric.unsafe_price_candidates=3', $dryRunOutput);
        $this->assertStringContainsString('metric.holds_applied=0', $dryRunOutput);
        $this->assertStringContainsString('metric.invalid_old_price_candidates=1', $dryRunOutput);

        $this->assertFalse($belowCost->fresh()->requires_price_confirmation);
        $this->assertSame('80.00', $belowCost->fresh()->price_old);
        $this->assertSame('150.00', $safe->fresh()->price_old);

        $this->artisan('catalog:enforce-price-integrity-safety', ['--apply' => true])
            ->expectsOutputToContain('metric.status=applied')
            ->expectsOutputToContain('metric.unsafe_price_candidates=3')
            ->expectsOutputToContain('metric.holds_applied=3')
            ->expectsOutputToContain('metric.old_prices_cleared=1')
            ->assertSuccessful();

        foreach ([$belowCost, $lowMargin, $extremeMarkup] as $product) {
            $this->assertTrue($product->fresh()->requires_price_confirmation);
            $this->assertFalse($product->fresh()->canBePurchased());
        }
        $this->assertFalse($safe->fresh()->requires_price_confirmation);
        $this->assertFalse($zero->fresh()->requires_price_confirmation);
        $this->assertNull($belowCost->fresh()->price_old);
        $this->assertSame('150.00', $safe->fresh()->price_old);
        $this->assertSame(3, ActivityLog::query()->where('action', 'catalog_price_safety_hold_applied')->count());
        $this->assertSame(1, ActivityLog::query()->where('action', 'catalog_invalid_old_price_cleared')->count());

        $this->artisan('catalog:enforce-price-integrity-safety', ['--apply' => true])
            ->expectsOutputToContain('metric.already_held=3')
            ->expectsOutputToContain('metric.holds_applied=0')
            ->expectsOutputToContain('metric.invalid_old_price_candidates=0')
            ->expectsOutputToContain('metric.old_prices_cleared=0')
            ->assertSuccessful();

        $this->assertSame(4, ActivityLog::query()
            ->whereIn('action', ['catalog_price_safety_hold_applied', 'catalog_invalid_old_price_cleared'])
            ->count());
    }

    private function product(int $categoryId, string $sku, float $price, ?float $oldPrice): Product
    {
        return Product::query()->create([
            'category_id' => $categoryId,
            'name' => 'Товар '.$sku,
            'slug' => strtolower($sku),
            'sku' => $sku,
            'price' => $price,
            'price_old' => $oldPrice,
            'currency' => 'BYN',
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => true,
            'availability_status' => Product::AVAILABILITY_IN_STOCK,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\MarketPriceObservation;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplyMarketPriceBatchCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_priority_batch_uses_server_costs_and_holds_unconfirmed_prices_out_of_checkout(): void
    {
        CarbonImmutable::setTestNow('2026-10-13 14:00:00');
        User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $category = Category::query()->create([
            'name' => 'Проверка рыночной цены',
            'slug' => 'market-batch-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $supplier = Supplier::query()->create([
            'code' => 'market-batch-supplier',
            'name' => 'Поставщик для проверки',
        ]);

        $fixtures = [
            'PS-007.208' => [2037, 1900, 1847],
            'KOTLOV-004246' => [1162, 1200, null],
            'KOTLOV-004560' => [1125, 1100, null],
            'KOTLOV-003099' => [28, 30, null],
            'KOTLOV-003102' => [28, 30, null],
        ];

        foreach ($fixtures as $sku => [$price, $cost, $oldPrice]) {
            $product = Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Тестовая точная модель '.$sku,
                'slug' => 'market-batch-'.strtolower(str_replace('.', '-', $sku)),
                'sku' => $sku,
                'price' => $price,
                'price_old' => $oldPrice,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
                'in_stock' => true,
                'availability_status' => Product::AVAILABILITY_IN_STOCK,
            ]);
            SupplierProduct::query()->create([
                'supplier_id' => $supplier->id,
                'product_id' => $product->id,
                'supplier_article' => 'SUP-'.$sku,
                'price_byn' => $cost,
                'in_stock' => true,
                'stock_quantity' => 2,
                'last_stock_synced_at' => now(),
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-13-below-cost-priority',
            '--apply' => true,
        ])
            ->expectsOutputToContain('metric.status=applied')
            ->expectsOutputToContain('metric.prices_applied=1')
            ->expectsOutputToContain('metric.consultation_holds=4')
            ->expectsOutputToContain('metric.old_prices_cleared=1')
            ->assertSuccessful();

        $locomotive = Product::query()->where('sku', 'KOTLOV-004560')->firstOrFail();
        $this->assertSame('1235.63', $locomotive->price);
        $this->assertFalse($locomotive->requires_price_confirmation);
        $this->assertTrue($locomotive->canBePurchased());

        $held = Product::query()->whereIn('sku', [
            'PS-007.208', 'KOTLOV-004246', 'KOTLOV-003099', 'KOTLOV-003102',
        ])->get();
        $this->assertCount(4, $held);
        foreach ($held as $product) {
            $this->assertTrue($product->requires_price_confirmation);
            $this->assertSame(Product::AVAILABILITY_CHECK, $product->effectiveAvailabilityStatus());
            $this->assertFalse($product->canBePurchased());
        }

        $this->assertNull(Product::query()->where('sku', 'PS-007.208')->value('price_old'));
        $this->assertSame(14, MarketPriceObservation::query()->count());
        $this->assertSame(4, ActivityLog::query()
            ->where('action', 'market_price_confirmation_required')
            ->count());
        $this->assertSame(1, ActivityLog::query()
            ->where('action', 'market_price_recommendation_applied')
            ->count());
    }

    public function test_extreme_markup_batch_is_valid_and_matches_exact_active_server_skus(): void
    {
        $category = Category::query()->create([
            'name' => 'Проверка ценовых выбросов',
            'slug' => 'extreme-price-batch-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        foreach ([
            'KOTLOV-004490',
            'KOTLOV-004404',
            'KOTLOV-004558',
            'KOTLOV-004406',
            'KOTLOV-004324',
        ] as $index => $sku) {
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Тестовая точная модель '.$sku,
                'slug' => 'extreme-price-batch-'.$index,
                'sku' => $sku,
                'price' => 1005,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-13-extreme-markup',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=5')
            ->expectsOutputToContain('metric.observations=11')
            ->assertSuccessful();
    }

    public function test_vezuviy_doors_margin_batch_is_valid_and_matches_exact_active_server_skus(): void
    {
        $category = Category::query()->create([
            'name' => 'Проверка цен дверей Везувий',
            'slug' => 'vezuviy-doors-batch-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        foreach ([
            'PS-010.785',
            'PS-010.786',
            'PS-011.142',
            'PS-011.144',
            'PS-011.150',
            'PS-011.151',
            'PS-011.420',
            'PS-011.438',
            'PS-011.439',
            'PS-011.828',
            'PS-011.830',
            'PS-011.920',
            'KOTLOV-004333',
            'KOTLOV-004334',
            'KOTLOV-004338',
            'KOTLOV-004349',
            'KOTLOV-004351',
            'KOTLOV-004353',
            'KOTLOV-004354',
            'KOTLOV-004357',
            'KOTLOV-004395',
        ] as $index => $sku) {
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Тестовая точная модель '.$sku,
                'slug' => 'vezuviy-doors-batch-'.$index,
                'sku' => $sku,
                'price' => 1005,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-13-vezuviy-doors-margin',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=21')
            ->expectsOutputToContain('metric.observations=63')
            ->assertSuccessful();
    }

    public function test_remaining_margin_evidence_batch_is_valid_and_matches_exact_active_server_skus(): void
    {
        $category = Category::query()->create([
            'name' => 'Проверка оставшихся маржинальных цен',
            'slug' => 'remaining-margin-batch-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        foreach ([
            'KOTLOV-004709',
            'KOTLOV-005891',
            'KOTLOV-005892',
            'KOTLOV-005893',
            'KOTLOV-005894',
            'KOTLOV-004488',
            'KOTLOV-004526',
            'KOTLOV-004569',
            'KOTLOV-004570',
            'KOTLOV-004571',
            'KOTLOV-004417',
        ] as $index => $sku) {
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Тестовая точная модель '.$sku,
                'slug' => 'remaining-margin-batch-'.$index,
                'sku' => $sku,
                'price' => 1005,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-13-remaining-margin-evidence',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=11')
            ->expectsOutputToContain('metric.observations=22')
            ->assertSuccessful();
    }
}

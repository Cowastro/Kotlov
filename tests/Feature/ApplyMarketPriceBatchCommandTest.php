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
use Illuminate\Support\Facades\File;
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

    public function test_reviewed_batch_can_force_a_consultation_hold_without_market_observations(): void
    {
        User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $category = Category::query()->create([
            'name' => 'Неподтверждённая рыночная цена',
            'slug' => 'forced-market-hold-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Редкая точная модель без открытой цены',
            'slug' => 'forced-market-hold-product',
            'sku' => 'TEST-FORCED-HOLD',
            'price' => 0,
            'currency' => 'BYN',
            'is_active' => true,
            'is_archived' => false,
            'requires_price_confirmation' => false,
        ]);
        $batch = 'test-force-consultation-hold';
        $path = database_path('data/market-price-batches/'.$batch.'.json');

        File::put($path, json_encode([
            'code' => $batch,
            'observed_at' => '2026-10-13T18:00:00+02:00',
            'sources' => [],
            'products' => [[
                'sku' => $product->sku,
                'review_reason' => 'Три независимых точных предложения не найдены.',
                'force_consultation_hold' => true,
                'observations' => [],
            ]],
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        try {
            $this->artisan('catalog:apply-market-price-batch', [
                'batch' => $batch,
                '--apply' => true,
            ])
                ->expectsOutputToContain('metric.status=applied')
                ->expectsOutputToContain('metric.consultation_holds=1')
                ->assertSuccessful();

            $this->assertTrue($product->fresh()->requires_price_confirmation);
            $this->assertDatabaseHas('activity_logs', [
                'model_type' => Product::class,
                'model_id' => $product->id,
                'action' => 'market_price_confirmation_required',
            ]);
            $this->assertSame(0, MarketPriceObservation::query()->where('product_id', $product->id)->count());
        } finally {
            File::delete($path);
        }
    }

    public function test_first_technolit_zero_price_batch_holds_exact_production_skus(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест нулевых цен ТехноЛит',
            'slug' => 'technolit-zero-price-batch-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        foreach (['KOTLOV-000126', 'KOTLOV-000127', 'KOTLOV-000128'] as $index => $sku) {
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'technolit-zero-price-batch-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-13-technolit-zero-001',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=3')
            ->expectsOutputToContain('metric.observations=0')
            ->assertSuccessful();
    }

    public function test_second_technolit_zero_price_batch_uses_exact_production_skus(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест второго пакета нулевых цен ТехноЛит',
            'slug' => 'technolit-zero-price-batch-two-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        foreach (range(129, 133) as $index => $number) {
            $sku = sprintf('KOTLOV-%06d', $number);
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'technolit-zero-price-batch-two-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-13-technolit-zero-002',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=5')
            ->expectsOutputToContain('metric.observations=7')
            ->assertSuccessful();
    }

    public function test_third_technolit_zero_price_batch_uses_exact_production_skus(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест третьего пакета нулевых цен ТехноЛит',
            'slug' => 'technolit-zero-price-batch-three-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        foreach (range(134, 138) as $index => $number) {
            $sku = sprintf('KOTLOV-%06d', $number);
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'technolit-zero-price-batch-three-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-13-technolit-zero-003',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=5')
            ->expectsOutputToContain('metric.observations=8')
            ->assertSuccessful();
    }

    public function test_fourth_technolit_zero_price_batch_uses_exact_production_skus(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест четвёртого пакета нулевых цен ТехноЛит',
            'slug' => 'technolit-zero-price-batch-four-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        foreach (range(139, 143) as $index => $number) {
            $sku = sprintf('KOTLOV-%06d', $number);
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'technolit-zero-price-batch-four-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-13-technolit-zero-004',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=5')
            ->expectsOutputToContain('metric.observations=4')
            ->assertSuccessful();
    }

    public function test_fifth_technolit_zero_price_batch_uses_exact_production_skus(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест пятого пакета нулевых цен ТехноЛит',
            'slug' => 'technolit-zero-price-batch-five-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        foreach (range(144, 148) as $index => $number) {
            $sku = sprintf('KOTLOV-%06d', $number);
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'technolit-zero-price-batch-five-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-13-technolit-zero-005',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=5')
            ->expectsOutputToContain('metric.observations=1')
            ->assertSuccessful();
    }

    public function test_sixth_technolit_zero_price_batch_uses_exact_production_skus(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест шестого пакета нулевых цен ТехноЛит',
            'slug' => 'technolit-zero-price-batch-six-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        foreach (range(149, 153) as $index => $number) {
            $sku = sprintf('KOTLOV-%06d', $number);
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'technolit-zero-price-batch-six-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-13-technolit-zero-006',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=5')
            ->expectsOutputToContain('metric.observations=3')
            ->assertSuccessful();
    }

    public function test_seventh_technolit_zero_price_batch_uses_exact_production_skus(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест седьмого пакета нулевых цен ТехноЛит',
            'slug' => 'technolit-zero-price-batch-seven-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        foreach (range(154, 158) as $index => $number) {
            $sku = sprintf('KOTLOV-%06d', $number);
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'technolit-zero-price-batch-seven-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-13-technolit-zero-007',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=5')
            ->expectsOutputToContain('metric.observations=5')
            ->assertSuccessful();
    }

    public function test_eighth_technolit_zero_price_batch_uses_exact_production_skus(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест восьмого пакета нулевых цен ТехноЛит',
            'slug' => 'technolit-zero-price-batch-eight-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        foreach (range(159, 163) as $index => $number) {
            $sku = sprintf('KOTLOV-%06d', $number);
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'technolit-zero-price-batch-eight-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-13-technolit-zero-008',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=5')
            ->expectsOutputToContain('metric.observations=6')
            ->assertSuccessful();
    }

    public function test_ninth_technolit_zero_price_batch_uses_exact_production_skus(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест девятого пакета нулевых цен ТехноЛит',
            'slug' => 'technolit-zero-price-batch-nine-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        foreach (range(164, 168) as $index => $number) {
            $sku = sprintf('KOTLOV-%06d', $number);
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'technolit-zero-price-batch-nine-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-13-technolit-zero-009',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=5')
            ->expectsOutputToContain('metric.observations=8')
            ->assertSuccessful();
    }

    public function test_tenth_technolit_zero_price_batch_uses_exact_production_skus(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест десятого пакета нулевых цен ТехноЛит',
            'slug' => 'technolit-zero-price-batch-ten-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        foreach (range(169, 173) as $index => $number) {
            $sku = sprintf('KOTLOV-%06d', $number);
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'technolit-zero-price-batch-ten-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-13-technolit-zero-010',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=5')
            ->expectsOutputToContain('metric.observations=10')
            ->assertSuccessful();
    }

    public function test_eleventh_technolit_zero_price_batch_uses_exact_production_skus(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест одиннадцатого пакета нулевых цен ТехноЛит',
            'slug' => 'technolit-zero-price-batch-eleven-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        foreach (range(174, 178) as $index => $number) {
            $sku = sprintf('KOTLOV-%06d', $number);
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'technolit-zero-price-batch-eleven-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-13-technolit-zero-011',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=5')
            ->expectsOutputToContain('metric.observations=3')
            ->assertSuccessful();
    }

    public function test_twelfth_technolit_zero_price_batch_uses_exact_production_skus(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест двенадцатого пакета нулевых цен ТехноЛит',
            'slug' => 'technolit-zero-price-batch-twelve-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        foreach (range(179, 183) as $index => $number) {
            $sku = sprintf('KOTLOV-%06d', $number);
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'technolit-zero-price-batch-twelve-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-13-technolit-zero-012',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=5')
            ->expectsOutputToContain('metric.observations=2')
            ->assertSuccessful();
    }

    public function test_thirteenth_technolit_zero_price_batch_uses_exact_production_skus(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест тринадцатого пакета нулевых цен ТехноЛит',
            'slug' => 'technolit-zero-price-batch-thirteen-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        foreach (range(184, 188) as $index => $number) {
            $sku = sprintf('KOTLOV-%06d', $number);
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'technolit-zero-price-batch-thirteen-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-13-technolit-zero-013',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=5')
            ->expectsOutputToContain('metric.observations=4')
            ->assertSuccessful();
    }

    public function test_fourteenth_technolit_zero_price_batch_uses_exact_production_skus(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест четырнадцатого пакета нулевых цен ТехноЛит',
            'slug' => 'technolit-zero-price-batch-fourteen-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        foreach (range(189, 193) as $index => $number) {
            $sku = sprintf('KOTLOV-%06d', $number);
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'technolit-zero-price-batch-fourteen-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-13-technolit-zero-014',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=5')
            ->expectsOutputToContain('metric.observations=5')
            ->assertSuccessful();
    }

    public function test_fifteenth_technolit_zero_price_batch_uses_exact_production_skus(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест пятнадцатого пакета нулевых цен ТехноЛит',
            'slug' => 'technolit-zero-price-batch-fifteen-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        foreach (range(194, 198) as $index => $number) {
            $sku = sprintf('KOTLOV-%06d', $number);
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'technolit-zero-price-batch-fifteen-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-13-technolit-zero-015',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=5')
            ->expectsOutputToContain('metric.observations=4')
            ->assertSuccessful();
    }

    public function test_sixteenth_technolit_zero_price_batch_uses_exact_production_skus(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест шестнадцатого пакета нулевых цен ТехноЛит',
            'slug' => 'technolit-zero-price-batch-sixteen-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        foreach ([199, 200, 201, 203, 204] as $index => $number) {
            $sku = sprintf('KOTLOV-%06d', $number);
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'technolit-zero-price-batch-sixteen-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-13-technolit-zero-016',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=5')
            ->expectsOutputToContain('metric.observations=8')
            ->assertSuccessful();
    }

    public function test_seventeenth_technolit_zero_price_batch_uses_exact_production_skus(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест семнадцатого пакета нулевых цен ТехноЛит',
            'slug' => 'technolit-zero-price-batch-seventeen-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        foreach (range(205, 209) as $index => $number) {
            $sku = sprintf('KOTLOV-%06d', $number);
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'technolit-zero-price-batch-seventeen-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-13-technolit-zero-017',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=5')
            ->expectsOutputToContain('metric.observations=4')
            ->assertSuccessful();
    }

    public function test_eighteenth_technolit_zero_price_batch_uses_exact_production_skus(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест восемнадцатого пакета нулевых цен ТехноЛит',
            'slug' => 'technolit-zero-price-batch-eighteen-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        foreach (range(210, 214) as $index => $number) {
            $sku = sprintf('KOTLOV-%06d', $number);
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'technolit-zero-price-batch-eighteen-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-13-technolit-zero-018',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=5')
            ->expectsOutputToContain('metric.observations=1')
            ->assertSuccessful();
    }

    public function test_nineteenth_technolit_zero_price_batch_uses_exact_production_skus(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест девятнадцатого пакета нулевых цен ТехноЛит',
            'slug' => 'technolit-zero-price-batch-nineteen-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        foreach (range(215, 219) as $index => $number) {
            $sku = sprintf('KOTLOV-%06d', $number);
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'technolit-zero-price-batch-nineteen-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-13-technolit-zero-019',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=5')
            ->expectsOutputToContain('metric.observations=3')
            ->assertSuccessful();
    }

    public function test_twentieth_technolit_zero_price_batch_uses_exact_production_sku(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест двадцатого пакета нулевых цен ТехноЛит',
            'slug' => 'technolit-zero-price-batch-twenty-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Точная production-модель KOTLOV-000220',
            'slug' => 'technolit-zero-price-batch-twenty-0',
            'sku' => 'KOTLOV-000220',
            'price' => 0,
            'currency' => 'BYN',
            'is_active' => true,
            'is_archived' => false,
        ]);

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-14-technolit-zero-020',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=1')
            ->expectsOutputToContain('metric.observations=0')
            ->assertSuccessful();
    }

    public function test_twenty_first_catalog_zero_price_batch_uses_exact_production_skus(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест двадцать первого пакета нулевых цен каталога',
            'slug' => 'catalog-zero-price-batch-twenty-one-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        foreach (['PS-000.638', 'PS-000.693', 'PS-000.701', 'PS-000.707', 'PS-000.709'] as $index => $sku) {
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'catalog-zero-price-batch-twenty-one-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-14-catalog-zero-021',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=5')
            ->expectsOutputToContain('metric.observations=0')
            ->assertSuccessful();
    }

    public function test_twenty_second_catalog_zero_price_batch_uses_exact_production_skus(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест двадцать второго пакета нулевых цен каталога',
            'slug' => 'catalog-zero-price-batch-twenty-two-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        foreach (['PS-000.900', 'PS-001.163', 'PS-001.164', 'PS-001.165', 'PS-001.166'] as $index => $sku) {
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'catalog-zero-price-batch-twenty-two-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-14-catalog-zero-022',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=5')
            ->expectsOutputToContain('metric.observations=0')
            ->assertSuccessful();
    }

    public function test_twenty_third_catalog_zero_price_batch_uses_exact_production_skus(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест двадцать третьего пакета нулевых цен каталога',
            'slug' => 'catalog-zero-price-batch-twenty-three-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        foreach (['PS-001.167', 'PS-001.169', 'PS-001.170', 'PS-001.171', 'PS-001.172'] as $index => $sku) {
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'catalog-zero-price-batch-twenty-three-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-14-catalog-zero-023',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=5')
            ->expectsOutputToContain('metric.observations=0')
            ->assertSuccessful();
    }

    public function test_twenty_fourth_catalog_zero_price_batch_uses_exact_production_skus(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест двадцать четвёртого пакета нулевых цен каталога',
            'slug' => 'catalog-zero-price-batch-twenty-four-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        foreach (['PS-002.029', 'PS-002.030', 'PS-002.031', 'PS-002.032', 'PS-002.033'] as $index => $sku) {
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'catalog-zero-price-batch-twenty-four-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-14-catalog-zero-024',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=5')
            ->expectsOutputToContain('metric.observations=0')
            ->assertSuccessful();
    }

    public function test_twenty_fifth_catalog_zero_price_batch_uses_exact_production_skus(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест двадцать пятого пакета нулевых цен каталога',
            'slug' => 'catalog-zero-price-batch-twenty-five-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        foreach (['PS-002.034', 'PS-002.035', 'PS-002.036', 'PS-002.037', 'PS-002.038'] as $index => $sku) {
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'catalog-zero-price-batch-twenty-five-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-14-catalog-zero-025',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=5')
            ->expectsOutputToContain('metric.observations=0')
            ->assertSuccessful();
    }

    public function test_twenty_sixth_catalog_zero_price_batch_uses_exact_production_skus(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест двадцать шестого пакета нулевых цен каталога',
            'slug' => 'catalog-zero-price-batch-twenty-six-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        foreach (['PS-002.039', 'PS-002.040', 'PS-002.041', 'PS-002.042', 'PS-002.043'] as $index => $sku) {
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'catalog-zero-price-batch-twenty-six-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-14-catalog-zero-026',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=5')
            ->expectsOutputToContain('metric.observations=0')
            ->assertSuccessful();
    }

    public function test_twenty_seventh_catalog_zero_price_batch_uses_exact_production_skus(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест двадцать седьмого пакета нулевых цен каталога',
            'slug' => 'catalog-zero-price-batch-twenty-seven-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        foreach (['PS-002.044', 'PS-002.045', 'PS-002.046', 'PS-002.367', 'PS-002.480'] as $index => $sku) {
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'catalog-zero-price-batch-twenty-seven-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-14-catalog-zero-027',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=5')
            ->expectsOutputToContain('metric.observations=0')
            ->assertSuccessful();
    }

    public function test_twenty_eighth_catalog_zero_price_batch_uses_exact_production_skus(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест двадцать восьмого пакета нулевых цен каталога',
            'slug' => 'catalog-zero-price-batch-twenty-eight-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        foreach (['PS-002.612', 'PS-002.619', 'PS-002.622', 'PS-002.623', 'PS-002.633'] as $index => $sku) {
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'catalog-zero-price-batch-twenty-eight-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-14-catalog-zero-028',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=5')
            ->expectsOutputToContain('metric.observations=0')
            ->assertSuccessful();
    }

    public function test_twenty_ninth_catalog_zero_price_batch_uses_exact_production_skus(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест двадцать девятого пакета нулевых цен каталога',
            'slug' => 'catalog-zero-price-batch-twenty-nine-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        foreach (['PS-002.638', 'PS-002.639', 'PS-002.640', 'PS-002.642', 'PS-002.643'] as $index => $sku) {
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'catalog-zero-price-batch-twenty-nine-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-14-catalog-zero-029',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=5')
            ->expectsOutputToContain('metric.observations=0')
            ->assertSuccessful();
    }

    public function test_thirtieth_catalog_zero_price_batch_preserves_single_exact_observation_without_pricing(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест тридцатого пакета нулевых цен каталога',
            'slug' => 'catalog-zero-price-batch-thirty-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        foreach (['PS-002.655', 'PS-002.657', 'PS-002.659', 'PS-002.663', 'PS-002.723'] as $index => $sku) {
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'catalog-zero-price-batch-thirty-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-14-catalog-zero-030',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=5')
            ->expectsOutputToContain('metric.observations=1')
            ->expectsOutputToContain('metric.prices_applied=0')
            ->assertSuccessful();
    }

    public function test_thirty_first_catalog_zero_price_batch_prices_only_models_with_three_independent_offers(): void
    {
        User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $category = Category::query()->create([
            'name' => 'Тест тридцать первого пакета нулевых цен каталога',
            'slug' => 'catalog-zero-price-batch-thirty-one-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $supplier = Supplier::query()->create([
            'code' => 'catalog-zero-price-batch-thirty-one-supplier',
            'name' => 'Поставщик для тридцать первого пакета',
        ]);

        $costs = [
            'PS-002.724' => 800,
            'PS-002.725' => 700,
            'PS-002.726' => 800,
            'PS-002.727' => 600,
            'PS-002.729' => 650,
        ];

        foreach ($costs as $index => $cost) {
            $product = Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$index,
                'slug' => 'catalog-zero-price-batch-thirty-one-'.strtolower(str_replace('.', '-', $index)),
                'sku' => $index,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
            SupplierProduct::query()->create([
                'supplier_id' => $supplier->id,
                'product_id' => $product->id,
                'supplier_article' => 'SUP-'.$index,
                'price_byn' => $cost,
                'in_stock' => true,
                'stock_quantity' => 1,
                'last_stock_synced_at' => now(),
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-14-catalog-zero-031',
            '--apply' => true,
        ])
            ->expectsOutputToContain('metric.status=applied')
            ->expectsOutputToContain('metric.products=5')
            ->expectsOutputToContain('metric.observations=12')
            ->expectsOutputToContain('metric.prices_applied=2')
            ->expectsOutputToContain('metric.consultation_holds=3')
            ->assertSuccessful();

        $this->assertSame('1224.00', Product::query()->where('sku', 'PS-002.724')->value('price'));
        $this->assertSame('926.00', Product::query()->where('sku', 'PS-002.729')->value('price'));
        $this->assertFalse((bool) Product::query()->where('sku', 'PS-002.724')->value('requires_price_confirmation'));
        $this->assertFalse((bool) Product::query()->where('sku', 'PS-002.729')->value('requires_price_confirmation'));

        foreach (['PS-002.725', 'PS-002.726', 'PS-002.727'] as $sku) {
            $this->assertSame('0.00', Product::query()->where('sku', $sku)->value('price'));
            $this->assertTrue((bool) Product::query()->where('sku', $sku)->value('requires_price_confirmation'));
        }
    }

    public function test_thirty_second_catalog_zero_price_batch_prices_only_three_independently_confirmed_models(): void
    {
        User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $category = Category::query()->create([
            'name' => 'Тест тридцать второго пакета нулевых цен каталога',
            'slug' => 'catalog-zero-price-batch-thirty-two-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $supplier = Supplier::query()->create([
            'code' => 'catalog-zero-price-batch-thirty-two-supplier',
            'name' => 'Поставщик для тридцать второго пакета',
        ]);
        $costs = [
            'PS-002.731' => 800,
            'PS-002.733' => 800,
            'PS-002.735' => 1000,
            'PS-002.736' => 1000,
            'PS-002.738' => 1500,
        ];

        foreach ($costs as $sku => $cost) {
            $product = Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'catalog-zero-price-batch-thirty-two-'.strtolower(str_replace('.', '-', $sku)),
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
            SupplierProduct::query()->create([
                'supplier_id' => $supplier->id,
                'product_id' => $product->id,
                'supplier_article' => 'SUP-'.$sku,
                'price_byn' => $cost,
                'in_stock' => true,
                'stock_quantity' => 1,
                'last_stock_synced_at' => now(),
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-14-catalog-zero-032',
            '--apply' => true,
        ])
            ->expectsOutputToContain('metric.status=applied')
            ->expectsOutputToContain('metric.products=5')
            ->expectsOutputToContain('metric.observations=10')
            ->expectsOutputToContain('metric.prices_applied=3')
            ->expectsOutputToContain('metric.consultation_holds=2')
            ->assertSuccessful();

        $this->assertSame('1055.00', Product::query()->where('sku', 'PS-002.731')->value('price'));
        $this->assertSame('1155.00', Product::query()->where('sku', 'PS-002.733')->value('price'));
        $this->assertSame('1423.10', Product::query()->where('sku', 'PS-002.735')->value('price'));
        foreach (['PS-002.736', 'PS-002.738'] as $sku) {
            $this->assertSame('0.00', Product::query()->where('sku', $sku)->value('price'));
            $this->assertTrue((bool) Product::query()->where('sku', $sku)->value('requires_price_confirmation'));
        }
    }

    public function test_thirty_third_catalog_zero_price_batch_preserves_single_observation_and_holds_all_products(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест тридцать третьего пакета нулевых цен каталога',
            'slug' => 'catalog-zero-price-batch-thirty-three-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        foreach (['PS-002.741', 'PS-002.958', 'PS-004.168', 'PS-005.823', 'PS-003.814'] as $index => $sku) {
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'catalog-zero-price-batch-thirty-three-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-14-catalog-zero-033',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=5')
            ->expectsOutputToContain('metric.observations=1')
            ->expectsOutputToContain('metric.prices_applied=0')
            ->assertSuccessful();
    }

    public function test_thirty_fourth_catalog_zero_price_batch_preserves_evidence_and_holds_all_twenty_five_products(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест тридцать четвертого пакета нулевых цен каталога',
            'slug' => 'catalog-zero-price-batch-thirty-four-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        $skus = [
            'PS-011.858', 'PS-011.429', 'PS-008.829', 'PS-010.835', 'PS-012.028',
            'PS-008.635', 'PS-012.165', 'PS-008.476', 'PS-008.497', 'PS-008.541',
            'PS-008.542', 'PS-008.545', 'PS-012.173', 'PS-000.17524', 'PS-000.17374',
            'PS-000.17375', 'PS-000.17376', 'PS-000.17525', 'PS-000.17369', 'PS-000.17370',
            'PS-000.17379', 'PS-000.17528', 'PS-000.17367', 'PS-000.17377', 'PS-000.17526',
        ];

        foreach ($skus as $index => $sku) {
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'catalog-zero-price-batch-thirty-four-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-14-catalog-zero-034',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=25')
            ->expectsOutputToContain('metric.observations=11')
            ->expectsOutputToContain('metric.prices_applied=0')
            ->assertSuccessful();
    }

    public function test_thirty_fifth_catalog_zero_price_batch_preserves_evidence_and_holds_all_twenty_five_products(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест тридцать пятого пакета нулевых цен каталога',
            'slug' => 'catalog-zero-price-batch-thirty-five-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        $skus = [
            'PS-000.17529', 'PS-004.503', 'KOTLOV-002092', 'KOTLOV-006529', 'KOTLOV-006531',
            'KOTLOV-006536', 'KOTLOV-006537', 'KOTLOV-006542', 'KOTLOV-006543', 'KOTLOV-006544',
            'KOTLOV-006555', 'KOTLOV-006556', 'KOTLOV-006557', 'KOTLOV-004934', 'Терморегулятор Ecoster 200',
            'PS-010.754', 'PS-010.755', 'PS-010.756', 'PS-010.757', 'PS-010.758',
            'PS-010.759', 'PS-010.760', 'PS-010.761', 'PS-010.762', 'PS-010.764',
        ];

        foreach ($skus as $index => $sku) {
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'catalog-zero-price-batch-thirty-five-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-14-catalog-zero-035',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=25')
            ->expectsOutputToContain('metric.observations=3')
            ->expectsOutputToContain('metric.prices_applied=0')
            ->assertSuccessful();
    }

    public function test_thirty_sixth_catalog_zero_price_batch_preserves_evidence_and_holds_all_twenty_five_products(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест тридцать шестого пакета нулевых цен каталога',
            'slug' => 'catalog-zero-price-batch-thirty-six-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        $skus = [
            'PS-010.765', 'PS-010.768', 'PS-010.775', 'PS-010.776', 'PS-010.777',
            'PS-010.778', 'PS-010.779', 'PS-010.782', 'PS-010.783', 'PS-010.788',
            'PS-010.796', 'PS-010.798', 'PS-010.804', 'PS-010.805', 'PS-011.963',
            'PS-011.172', 'PS-011.173', 'PS-011.174', 'PS-011.428', 'PS-005.262',
            'PS-005.263', 'PS-005.264', 'PS-005.265', 'PS-005.278', 'PS-005.279',
        ];

        foreach ($skus as $index => $sku) {
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'catalog-zero-price-batch-thirty-six-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-14-catalog-zero-036',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=25')
            ->expectsOutputToContain('metric.observations=1')
            ->expectsOutputToContain('metric.prices_applied=0')
            ->assertSuccessful();
    }

    public function test_thirty_seventh_catalog_zero_price_batch_holds_all_twenty_five_unconfirmed_products(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест тридцать седьмого пакета нулевых цен каталога',
            'slug' => 'catalog-zero-price-batch-thirty-seven-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        $skus = [
            'PS-005.296', 'PS-005.299', 'PS-005.305', 'PS-005.308', 'PS-005.309',
            'PS-005.310', 'PS-005.311', 'PS-005.333', 'PS-005.334', 'PS-005.354',
            'PS-005.455', 'PS-005.456', 'PS-005.477', 'PS-005.478', 'PS-005.479',
            'PS-005.480', 'PS-005.481', 'PS-005.482', 'PS-005.504', 'PS-005.505',
            'PS-005.560', 'PS-005.847', 'PS-005.848', 'PS-005.849', 'PS-005.851',
        ];

        foreach ($skus as $index => $sku) {
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'catalog-zero-price-batch-thirty-seven-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-14-catalog-zero-037',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=25')
            ->expectsOutputToContain('metric.observations=0')
            ->expectsOutputToContain('metric.prices_applied=0')
            ->assertSuccessful();
    }

    public function test_thirty_eighth_catalog_zero_price_batch_preserves_evidence_and_holds_all_twenty_five_products(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест тридцать восьмого пакета нулевых цен каталога',
            'slug' => 'catalog-zero-price-batch-thirty-eight-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        $skus = [
            'PS-005.852', 'PS-005.853', 'PS-005.854', 'PS-005.886', 'PS-005.901',
            'PS-006.100', 'PS-004.613', 'PS-005.617', 'PS-005.618', 'PS-005.619',
            'PS-005.620', 'PS-005.651', 'PS-005.683', 'PS-005.769', 'PS-005.897',
            'PS-008.585', 'PS-008.580', 'PS-009.999', 'PS-010.000', 'PS-010.678',
            'PS-010.679', 'PS-010.680', 'PS-010.681', 'PS-010.682', 'PS-011.609',
        ];

        foreach ($skus as $index => $sku) {
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'catalog-zero-price-batch-thirty-eight-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-14-catalog-zero-038',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=25')
            ->expectsOutputToContain('metric.observations=10')
            ->expectsOutputToContain('metric.prices_applied=0')
            ->assertSuccessful();
    }

    public function test_thirty_ninth_catalog_zero_price_batch_preserves_evidence_and_holds_all_twenty_five_products(): void
    {
        $category = Category::query()->create([
            'name' => 'Тест тридцать девятого пакета нулевых цен каталога',
            'slug' => 'catalog-zero-price-batch-thirty-nine-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        $skus = [
            'PS-011.611', 'PS-010.458', 'PS-010.459', 'PS-010.462', 'PS-010.466',
            'PS-010.474', 'PS-010.475', 'PS-010.476', 'PS-010.480', 'PS-010.481',
            'PS-010.482', 'PS-010.483', 'PS-010.486', 'PS-010.489', 'PS-010.490',
            'PS-010.491', 'PS-010.493', 'PS-010.637', 'PS-011.708', 'PS-011.713',
            'PS-011.714', 'PS-011.715', 'PS-011.716', 'PS-011.717', 'PS-011.718',
        ];

        foreach ($skus as $index => $sku) {
            Product::query()->create([
                'category_id' => $category->id,
                'name' => 'Точная production-модель '.$sku,
                'slug' => 'catalog-zero-price-batch-thirty-nine-'.$index,
                'sku' => $sku,
                'price' => 0,
                'currency' => 'BYN',
                'is_active' => true,
                'is_archived' => false,
            ]);
        }

        $this->artisan('catalog:apply-market-price-batch', [
            'batch' => '2026-10-14-catalog-zero-039',
        ])
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.products=25')
            ->expectsOutputToContain('metric.observations=20')
            ->expectsOutputToContain('metric.prices_applied=0')
            ->assertSuccessful();
    }
}

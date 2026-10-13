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
}

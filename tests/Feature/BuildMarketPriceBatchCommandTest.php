<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Market\MarketWebResearchService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Mockery\MockInterface;
use Tests\TestCase;

class BuildMarketPriceBatchCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $output = 'storage/app/private/market-price-research/test-market-price-batch.json';

    private string $contexts = 'storage/app/private/market-price-research/test-market-price-contexts.json';

    private string $queue = 'storage/app/private/market-price-research/test-production-queue.csv';

    protected function tearDown(): void
    {
        File::delete(base_path($this->output));
        File::delete(base_path($this->contexts));
        File::delete(base_path($this->queue));
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_it_researches_only_unreviewed_zero_price_rows_from_a_production_queue(): void
    {
        File::ensureDirectoryExists(dirname(base_path($this->queue)));
        File::put(base_path($this->queue), implode("\n", [
            "\xEF\xBB\xBFpriority;issues;product_id;sku;name;brand;category;path;retail_price;old_price;currency;availability_status;updated_at;review_state;review_action;reviewed_at",
            '40;zero_or_missing_price;200;QUEUE-REVIEWED;Уже проверенная печь;ТехноЛит;Печи;/reviewed;;;;check;;reviewed;market_price_confirmation_required;2026-10-13 18:00:00',
            '40;zero_or_missing_price;201;QUEUE-NEW;Новая печь;ТехноЛит;Печи;/new;;;;check;;unreviewed;;',
        ]));

        $this->mock(MarketWebResearchService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('research')
                ->once()
                ->withArgs(fn (Product $product): bool => $product->id === 201 && $product->sku === 'QUEUE-NEW')
                ->andReturn($this->readyReport([]));
        });

        $this->artisan('catalog:build-market-price-batch', [
            'batch' => '2026-10-13-production-queue-test',
            '--brand' => 'ТехноЛит',
            '--input-queue' => $this->queue,
            '--limit' => 10,
            '--output' => $this->output,
        ])
            ->expectsOutputToContain('metric.selected_products=1')
            ->expectsOutputToContain('metric.last_product_id=201')
            ->assertSuccessful();

        $batch = json_decode(File::get(base_path($this->output)), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['QUEUE-NEW'], array_column($batch['products'], 'sku'));
        $this->assertSame($this->queue, $batch['selection']['source_queue']);
    }

    public function test_it_exports_production_contexts_and_researches_them_without_a_database_query(): void
    {
        $brand = Brand::query()->create([
            'name' => 'ТехноЛит',
            'slug' => 'technolit-context-export-test',
            'is_active' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Печи для контекстного теста',
            'slug' => 'market-context-export-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name' => 'Печь ТехноЛит GFS 18 для экспорта',
            'slug' => 'technolit-gfs-18-context-test',
            'sku' => 'TEST-CONTEXT-001',
            'price' => 0,
            'currency' => 'BYN',
            'specs' => [['name' => 'Мощность', 'value' => '18 кВт']],
            'is_active' => true,
            'is_archived' => false,
        ]);

        $this->mock(MarketWebResearchService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('research')
                ->once()
                ->withArgs(fn (Product $product, float $price): bool => $product->sku === 'TEST-CONTEXT-001'
                    && $product->brand?->name === 'ТехноЛит'
                    && $product->category?->name === 'Печи для контекстного теста'
                    && $product->specs[0]['value'] === '18 кВт'
                    && $price === 0.0)
                ->andReturn($this->readyReport([]));
        });

        $this->artisan('catalog:build-market-price-batch', [
            'batch' => '2026-10-13-context-export-test',
            '--sku' => [$product->sku],
            '--export-contexts' => $this->contexts,
        ])
            ->expectsOutputToContain('metric.status=contexts_exported')
            ->expectsOutputToContain('metric.selected_products=1')
            ->assertSuccessful();

        $context = json_decode(File::get(base_path($this->contexts)), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('TEST-CONTEXT-001', $context['products'][0]['sku']);
        $this->assertSame('ТехноЛит', $context['products'][0]['brand']);

        $product->delete();
        $this->artisan('catalog:build-market-price-batch', [
            'batch' => '2026-10-13-context-import-test',
            '--input-contexts' => $this->contexts,
            '--output' => $this->output,
        ])
            ->expectsOutputToContain('metric.status=review_required')
            ->expectsOutputToContain('metric.selected_products=1')
            ->expectsOutputToContain('metric.researched_products=1')
            ->expectsOutputToContain('metric.forced_consultation_holds=1')
            ->assertSuccessful();

        $batch = json_decode(File::get(base_path($this->output)), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('TEST-CONTEXT-001', $batch['products'][0]['sku']);
        $this->assertTrue($batch['products'][0]['force_consultation_hold']);
    }

    public function test_it_can_export_an_explicitly_selected_priced_product_for_market_review(): void
    {
        $category = Category::query()->create([
            'name' => 'Рыночная проверка оценённых товаров',
            'slug' => 'priced-market-review-category',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Товар с действующей розничной ценой',
            'slug' => 'priced-market-review-test',
            'sku' => 'PRICED-REVIEW-001',
            'price' => 321.45,
            'currency' => 'BYN',
            'is_active' => true,
            'is_archived' => false,
        ]);

        $this->artisan('catalog:build-market-price-batch', [
            'batch' => '2026-10-18-priced-context-test',
            '--sku' => [$product->sku],
            '--include-priced' => true,
            '--export-contexts' => $this->contexts,
        ])
            ->expectsOutputToContain('metric.status=contexts_exported')
            ->expectsOutputToContain('metric.selected_products=1')
            ->assertSuccessful();

        $context = json_decode(File::get(base_path($this->contexts)), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(321.45, $context['products'][0]['price']);
        $this->assertFalse($context['selection']['unpriced_only']);
    }

    public function test_it_builds_a_review_only_batch_and_forces_a_hold_without_three_exact_sources(): void
    {
        CarbonImmutable::setTestNow('2026-10-13 18:00:00');
        $brand = Brand::query()->create([
            'name' => 'ТехноЛит',
            'slug' => 'technolit-market-batch-test',
            'is_active' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Печи для теста live-web',
            'slug' => 'live-web-market-batch-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        $products = collect([
            ['sku' => 'TEST-WEB-001', 'name' => 'ТехноЛит GFS 18'],
            ['sku' => 'TEST-WEB-002', 'name' => 'ТехноЛит GFS 25'],
        ])->map(fn (array $row): Product => Product::query()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name' => $row['name'],
            'slug' => strtolower($row['sku']),
            'sku' => $row['sku'],
            'price' => 0,
            'currency' => 'BYN',
            'is_active' => true,
            'is_archived' => false,
        ]));

        $reports = [
            $products[0]->id => $this->readyReport([
                $this->offer('Собственный магазин', 'https://kotlov.by/product', 4000, 'exact'),
                $this->offer('Первый продавец', 'https://one.example/product', 4100, 'exact'),
                $this->offer('Второй продавец', 'https://two.example/product', 4200, 'exact'),
                $this->offer('Третий продавец', 'https://three.example/product', 4300, 'exact'),
                $this->offer('Похожая печь', 'https://similar.example/product', 2500, 'probable'),
            ]),
            $products[1]->id => $this->readyReport([], [[
                'title' => 'GFS 25 в другой комплектации',
                'url' => 'https://rejected.example/product',
                'reason' => 'Не совпадает комплектация.',
            ]]),
        ];

        $this->mock(MarketWebResearchService::class, function (MockInterface $mock) use ($reports): void {
            $mock->shouldReceive('research')
                ->twice()
                ->andReturnUsing(fn (Product $product): array => $reports[$product->id]);
        });

        $this->artisan('catalog:build-market-price-batch', [
            'batch' => '2026-10-13-technolit-test',
            '--brand' => 'ТехноЛит',
            '--limit' => 2,
            '--output' => $this->output,
        ])
            ->expectsOutputToContain('metric.status=review_required')
            ->expectsOutputToContain('metric.selected_products=2')
            ->expectsOutputToContain('metric.researched_products=2')
            ->expectsOutputToContain('metric.exact_offers=3')
            ->expectsOutputToContain('metric.forced_consultation_holds=1')
            ->assertSuccessful();

        $batch = json_decode(File::get(base_path($this->output)), true, flags: JSON_THROW_ON_ERROR);

        $this->assertTrue($batch['review_required']);
        $this->assertCount(3, $batch['sources']);
        $this->assertCount(2, $batch['products']);
        $this->assertFalse($batch['products'][0]['force_consultation_hold']);
        $this->assertCount(3, $batch['products'][0]['observations']);
        $this->assertSame('Собственный домен KOTLOV не является независимым рыночным источником.', $batch['products'][0]['research']['excluded_offers'][0]['reason']);
        $this->assertSame(['one.example', 'two.example', 'three.example'], collect($batch['products'][0]['observations'])
            ->map(fn (array $observation): string => parse_url($observation['url'], PHP_URL_HOST))
            ->all());
        $this->assertTrue($batch['products'][1]['force_consultation_hold']);
        $this->assertSame([], $batch['products'][1]['observations']);
        $this->assertSame('Не совпадает комплектация.', $batch['products'][1]['research']['rejected_candidates'][0]['reason']);
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     * @param  list<array<string, mixed>>  $rejected
     * @return array<string, mixed>
     */
    private function readyReport(array $offers, array $rejected = []): array
    {
        return [
            'status' => 'ready',
            'summary' => 'Проверены открытые карточки продавцов.',
            'verdict' => 'Требуется ручная проверка.',
            'confidence' => $offers === [] ? 'low' : 'high',
            'search_queries' => ['точная модель цена'],
            'offers' => $offers,
            'rejected_candidates' => $rejected,
            'model' => 'gpt-6.1-sol',
            'searched_at' => now(),
        ];
    }

    /** @return array<string, mixed> */
    private function offer(string $seller, string $url, float $price, string $match): array
    {
        return [
            'seller' => $seller,
            'title' => 'ТехноЛит GFS 18',
            'url' => $url,
            'price' => $price,
            'currency' => 'BYN',
            'price_byn' => $price,
            'availability' => 'в наличии',
            'vat' => 'с НДС',
            'package' => 'печь',
            'match' => $match,
            'evidence' => 'Совпадают модель и комплектация.',
        ];
    }
}
